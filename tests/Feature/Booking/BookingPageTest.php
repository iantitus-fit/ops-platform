<?php

use App\Actions\MintBookingLink;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Livewire\BookingPage;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * A moment on the example business's wall clock (America/Chicago), as the customer taps it.
 */
function bookingPageChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Monday 2026-11-02 22:00 in the shop's zone (so the first open time is Tuesday
 * 10:00: the shop opens at 08:00 and the lead time is two hours), an owner with a phone, and Dana Whitfield, who
 * texted: her open conversation and Job 1 tied to it.
 *
 * @return array{0: Contact, 1: Conversation, 2: Project}
 */
function bookingPageSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(bookingPageChicago('2026-11-02 22:00'));

    User::factory()->withPhone('+12025550199')->create();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);
    $job = Project::factory()->create(['contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    return [$contact, $conversation, $job];
}

/**
 * Mint a link for the conversation and give it the outbound text that carried it, the way the send step does.
 *
 * @return array{0: BookingLink, 1: string}
 */
function bookingPageMint(Conversation $conversation, string $service = 'ac-repair'): array
{
    [$link, $token] = app(MintBookingLink::class)->handle($conversation, $service, CarbonImmutable::now());

    $message = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Here is the link to book.',
        'meta' => ['booking_link_id' => $link->id],
    ]);

    app(MintBookingLink::class)->attachMessage($link, $message);

    return [$link->fresh(), $token];
}

/**
 * The words a customer reads in a page's HTML: tags gone, entities decoded, every run of white space (a non-breaking
 * space too) one plain space.
 */
function bookingPageText(string $html): string
{
    $html = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
}

/**
 * A page's HTML as something to ask structural questions of.
 */
function bookingPageXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

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
