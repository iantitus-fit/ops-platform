<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Livewire\BookingPage;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('opens the picker with the earliest day open', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $response = $this->get(route('booking.show', $token));

    $response->assertOk();

    $html = $response->getContent();
    $xpath = bookingPageXpath($html);

    expect(bookingPageText($html))
        ->toContain('Book AC repair')
        ->toContain('Pick a time that works for you. All times are in Central Time.')
        ->and($xpath->query('(//details)[1]/@open')->length)->toBe(1)
        ->and($xpath->query('(//details)[1]/summary')->item(0)->textContent)->toContain('Tuesday, November 3')
        ->and($xpath->query('(//details)[1]//button[contains(., "10:00")]')->length)->toBe(1)
        ->and($xpath->query('(//details)[1]//button[contains(., "10:00")]//*[contains(., "Tuesday, November 3")]')->length)->toBeGreaterThan(0);
});

it('books after a check', function () {
    [$contact, $conversation] = bookingPageSetUp();
    [$link, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String())
        ->assertSee('Check your time')
        ->assertSee('Service')
        ->assertSee('AC repair')
        ->assertSee('Tuesday, November 3, 2026')
        ->assertSee('Book this time')
        ->assertSee('Choose a different time');

    expect(Appointment::query()->count())->toBe(0);

    $page->call('book')->assertSee('You are booked');

    expect(bookingPageText($page->html()))
        ->toContain('Need to change this visit? Text Example Heating & Air at (312) 555-0147.');

    $appointment = Appointment::query()->sole();

    expect($appointment->state)->toBe(AppointmentState::Booked)
        ->and($appointment->booked_by)->toBe(AppointmentActor::Customer)
        ->and($appointment->contact_id)->toBe($contact->id)
        ->and($appointment->booking_link_id)->toBe($link->id)
        ->and($appointment->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-03 16:00');
});

it('goes back to the picker', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String());

    $checking = bookingPageXpath($page->html());

    expect($checking->query('//*[@id="booking-picker"]/@hidden')->length)->toBe(1);

    $page->call('back');

    $picking = bookingPageXpath($page->html());

    expect($picking->query('//*[@id="booking-picker"]/@hidden')->length)->toBe(0)
        ->and($picking->query('//h2[normalize-space(.)="Check your time"]')->length)->toBe(0)
        ->and(bookingPageText($page->html()))->toContain('Pick a time that works for you.');
});

it('shows each dead end with one h1 and one line', function (string $case, string $heading, string $line) {
    [, $conversation] = bookingPageSetUp();
    $token = str_repeat('a', 64);

    switch ($case) {
        case 'unknown':
            break;
        case 'revoked':
            [$link, $token] = bookingPageMint($conversation);
            app(MintBookingLink::class)->revoke($link);

            break;
        case 'superseded':
            [, $token] = bookingPageMint($conversation);
            bookingPageMint($conversation);

            break;
        case 'expired':
            [$link, $token] = bookingPageMint($conversation);
            $this->travelTo($link->expires_at->toImmutable()->addMinute());
            app()->forgetScopedInstances();

            break;
        case 'not bookable online':
            [, $token] = bookingPageMint($conversation);
            bookingPageResync(fn () => breakFile('business/services/ac-repair.md', fn (string $file): string => str_replace('book_online: true', 'book_online: false', $file)));

            break;
        case 'room off':
            [, $token] = bookingPageMint($conversation);
            bookingPageResync(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

            break;
        case 'no times':
            [, $token] = bookingPageMint($conversation);
            ScheduleBlock::factory()->at(bookingPageChicago('2026-11-03 00:00'), 60 * 24 * 17)->create();

            break;
    }

    $response = $this->get(route('booking.show', $token));

    $response->assertOk();

    $html = $response->getContent();
    $xpath = bookingPageXpath($html);

    expect($xpath->query('//h1')->length)->toBe(1)
        ->and(trim($xpath->query('//h1')->item(0)->textContent))->toBe($heading)
        ->and(bookingPageText($html))->toContain($line)
        ->and($xpath->query('//main//a[@href="sms:+13125550147"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//button')->length)->toBe(0);
})->with([
    'an unknown token' => ['unknown', 'This link does not work', 'Text Example Heating & Air at (312) 555-0147 and we will help.'],
    'a revoked link' => ['revoked', 'This link does not work', 'Text Example Heating & Air at (312) 555-0147 and we will help.'],
    'a replaced link' => ['superseded', 'This link was replaced', 'A newer link was sent to you. Open the latest text from Example Heating & Air, or text us at (312) 555-0147.'],
    'an expired link' => ['expired', 'This link has expired', 'Text Example Heating & Air at (312) 555-0147 to get a new one.'],
    'a service not bookable online' => ['not bookable online', 'We cannot book this online', 'Text Example Heating & Air at (312) 555-0147 and we will help.'],
    'the room off' => ['room off', 'We cannot book this online', 'Text Example Heating & Air at (312) 555-0147 and we will help.'],
    'every day blocked' => ['no times', 'No times are open', 'We have no open times right now. Text Example Heating & Air at (312) 555-0147 and we will help you book.'],
]);

it('renders an unknown token and a revoked one the same (T-08-26)', function () {
    [, $conversation] = bookingPageSetUp();
    [$link, $revoked] = bookingPageMint($conversation);
    app(MintBookingLink::class)->revoke($link);

    $shown = fn (string $html): array => (function (DOMXPath $xpath): array {
        return [
            $xpath->query('//title')->item(0)->textContent,
            bookingPageWithoutLivewireIds($xpath->document->saveHTML($xpath->query('//main')->item(0))),
        ];
    })(bookingPageXpath($html));

    $unknown = $shown($this->get(route('booking.show', str_repeat('b', 64)))->getContent());
    $revoked = $shown($this->get(route('booking.show', $revoked))->getContent());

    expect($unknown[1])->toContain('This link does not work')
        ->and($unknown)->toBe($revoked);
});

it('shows the visit after booking, never times (U-14)', function () {
    [, $conversation] = bookingPageSetUp();
    [$link, $token] = bookingPageMint($conversation);
    $appointment = app(BookAppointment::class)->fromLink($link, bookingPageChicago('2026-11-03 10:00'), CarbonImmutable::now());

    $booked = $this->get(route('booking.show', $token))->assertOk()->getContent();

    expect(bookingPageText($booked))->toContain('You are booked')
        ->and(bookingPageXpath($booked)->query('//button')->length)->toBe(0);

    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();
    $cancelled = $this->get(route('booking.show', $token))->assertOk()->getContent();

    expect(bookingPageText($cancelled))
        ->toContain('This visit was cancelled')
        ->toContain('To book another time, text Example Heating & Air at (312) 555-0147.')
        ->and(bookingPageXpath($cancelled)->query('//button')->length)->toBe(0);

    $appointment->forceFill(['state' => AppointmentState::Done])->save();
    $done = $this->get(route('booking.show', $token))->assertOk()->getContent();

    expect(bookingPageText($done))
        ->toContain('This visit is done')
        ->toContain('Thank you for choosing Example Heating & Air.')
        ->and(bookingPageXpath($done)->query('//button')->length)->toBe(0);
});

it('offers fresh times when the time was just taken (D-07)', function () {
    [$contact, $conversation, $job] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String());

    app(BookAppointment::class)->byHand($contact, User::factory()->create(), 'ac-repair', bookingPageChicago('2026-11-03 10:00'), $job->id, null);

    $page->call('book');

    $xpath = bookingPageXpath($page->html());
    $error = $xpath->query('//*[@id="booking-error"]');

    expect(Appointment::query()->count())->toBe(1)
        ->and($xpath->query('//*[@id="booking-picker"]/@hidden')->length)->toBe(0)
        ->and($error->length)->toBe(1)
        ->and($error->item(0)->getAttribute('tabindex'))->toBe('-1')
        ->and(bookingPageText($error->item(0)->textContent))->toBe('That time was just taken. These times are open now.')
        ->and($xpath->query('//ul[@aria-describedby="booking-error"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//button[contains(., "10:00") and contains(., "Tuesday, November 3")]')->length)->toBe(0)
        ->and($xpath->query('//h2[normalize-space(.)="Check your time"]')->length)->toBe(0);
});

it('keeps the check step when booking fails another way (T-08-37)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);
    Exceptions::fake();

    $this->mock(BookAppointment::class, fn ($mock) => $mock->shouldReceive('fromLink')->andThrow(new RuntimeException('connection refused at db-7')));

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String())
        ->call('book');

    $html = $page->html();
    $xpath = bookingPageXpath($html);
    $error = $xpath->query('//*[@id="booking-error"]');

    expect(bookingPageText($html))
        ->toContain('Your time was not booked. Try again, or text Example Heating & Air at (312) 555-0147.')
        ->toContain('Book this time')
        ->not->toContain('connection refused')
        ->and($error->length)->toBe(1)
        ->and($xpath->query('//button[contains(., "Book this time")]/@aria-describedby')->item(0)->value)->toBe('booking-error')
        ->and($xpath->query('//*[@id="booking-error"]/following::button[contains(., "Book this time")]')->length)->toBe(1)
        ->and(Appointment::query()->count())->toBe(0);

    Exceptions::assertReported(RuntimeException::class);
});

it('refuses a time the server did not offer (T-08-32)', function (string $tapped) {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', $tapped === 'a minute off' ? bookingPageChicago('2026-11-03 10:01')->toIso8601String() : ($tapped === 'in the past' ? bookingPageChicago('2026-11-02 09:00')->toIso8601String() : 'tomorrow'))
        ->assertSet('chosen', null);

    expect(bookingPageText($page->html()))->toContain('That time was just taken. These times are open now.')
        ->not->toContain('Check your time');
})->with(['a minute off', 'in the past', 'not a time']);

it('refuses a browser that changes what the server set (Locked)', function (string $property, mixed $value) {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token]);

    expect(fn () => $page->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'the chosen time' => ['chosen', '2026-11-03T16:00:00Z'],
    'the token' => ['token', 'another'],
    'the booked flag' => ['justBooked', true],
    'the error' => ['error', 'failed'],
    'the picker version' => ['pickerVersion', 9],
]);

it('never shows the customer\'s name or phone in any state (D-13)', function () {
    [, $conversation] = bookingPageSetUp();
    [$link, $token] = bookingPageMint($conversation);

    $pages = [
        'picker' => $this->get(route('booking.show', $token))->getContent(),
        'unknown' => $this->get(route('booking.show', str_repeat('c', 64)))->getContent(),
        'check' => Livewire::test(BookingPage::class, ['token' => $token])
            ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String())->html(),
    ];

    $appointment = app(BookAppointment::class)->fromLink($link, bookingPageChicago('2026-11-03 10:00'), CarbonImmutable::now());
    $pages['booked'] = $this->get(route('booking.show', $token))->getContent();

    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();
    $pages['cancelled'] = $this->get(route('booking.show', $token))->getContent();

    $appointment->forceFill(['state' => AppointmentState::Done])->save();
    $pages['done'] = $this->get(route('booking.show', $token))->getContent();

    foreach ($pages as $state => $html) {
        foreach (['Dana', 'Whitfield', '2025550123', '202) 555-0123', '202-555-0123'] as $private) {
            expect(str_contains($html, $private))->toBeFalse("The {$state} page shows {$private}");
        }
    }
});

it('sends the private headers and the no-script line (U-16, U-17)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $response = $this->get(route('booking.show', $token));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $html = $response->getContent();
    $xpath = bookingPageXpath($html);

    expect($xpath->query('//meta[@name="robots"]/@content')->item(0)->value)->toBe('noindex, nofollow')
        ->and($xpath->query('//meta[@name="referrer"]/@content')->item(0)->value)->toBe('no-referrer')
        ->and(bookingPageText($xpath->query('//noscript')->item(0)->textContent))
        ->toBe('This page needs JavaScript to show times. Text Example Heating & Air at (312) 555-0147 to book.');
});

it('puts the Book button in a busy state while a booking is in flight (U-20)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $html = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String())
        ->html();

    $xpath = bookingPageXpath($html);
    $button = $xpath->query('//button[.//span[normalize-space(.)="Book this time"]]')->item(0);

    expect($button)->not->toBeNull()
        ->and($button->getAttribute('wire:loading.attr'))->toBe('aria-disabled')
        ->and($button->hasAttribute('disabled'))->toBeFalse()
        ->and($xpath->query('.//span[@*[name()="wire:loading"] and normalize-space(.)="Booking your time"]', $button)->length)->toBe(1);
});

it('stores one appointment when Book is called twice and shows the booked view', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token])
        ->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String())
        ->call('book')
        ->call('book');

    expect(Appointment::query()->count())->toBe(1)
        ->and(bookingPageText($page->html()))->toContain('You are booked');
});

it('answers 404 when no folder version is live', function () {
    $this->get(route('booking.show', str_repeat('d', 64)))->assertNotFound();
});
