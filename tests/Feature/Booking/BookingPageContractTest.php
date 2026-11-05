<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Actions\BookAppointment;
use App\Enums\AppointmentState;
use App\Livewire\BookingPage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Every customer view the contract covers, by path: the layout, the page, the rate-limited view and each booking
 * component.
 *
 * @return array<string, string>
 */
function bookingContractViews(): array
{
    $paths = [
        resource_path('views/layouts/customer.blade.php'),
        resource_path('views/livewire/booking-page.blade.php'),
        ...glob(resource_path('views/booking/*.blade.php')),
        ...array_map(fn (SplFileInfo $file): string => $file->getPathname(), iterator_to_array(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views/components/booking'), FilesystemIterator::SKIP_DOTS)),
            false,
        )),
    ];

    $views = [];

    foreach ($paths as $path) {
        $views[str_replace(base_path().'/', '', $path)] = (string) file_get_contents($path);
    }

    return $views;
}

/**
 * Every use of a pattern in a source, as the matched text.
 *
 * @return list<string>
 */
function bookingContractMatches(string $pattern, string $source): array
{
    preg_match_all($pattern, $source, $found);

    return $found[0];
}

/**
 * The rules, each a function from a view's source to the list of things in it that break the rule (UI-SPEC, "Contract
 * checks").
 *
 * @return array<string, Closure(string): list<string>>
 */
function bookingContractRules(): array
{
    $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
    $sizes = ['body', 'heading', 'display'];
    $textColors = ['ink', 'ink-muted', 'accent', 'accent-strong', 'on-accent', 'danger'];
    $alignments = ['center', 'left', 'right', 'start', 'end'];

    return [
        'style' => fn (string $source): array => bookingContractMatches('/\sstyle\s*=|<style\b/i', $source),
        'color' => fn (string $source): array => [
            ...bookingContractMatches('/(?<![&\w])#[0-9a-fA-F]{3,8}\b/', $source),
            ...bookingContractMatches('/(?<![\w-])(?:bg|text|border|ring|fill|stroke|outline|divide|decoration|from|via|to|shadow)-(?:'.$palette.')-\d{2,3}\b/', $source),
            ...bookingContractMatches('/(?<![\w-])(?:bg|text|border|ring|fill|stroke)-(?:white|black)\b/', $source),
        ],
        'type' => function (string $source) use ($sizes, $textColors, $alignments): array {
            $broken = [];

            foreach (bookingContractMatches('/(?<![\w-])text-[a-z0-9-]+/', $source) as $class) {
                if (! in_array(substr($class, 5), [...$sizes, ...$textColors, ...$alignments], true)) {
                    $broken[] = $class;
                }
            }

            foreach (bookingContractMatches('/(?<![\w-])font-[a-z0-9-]+/', $source) as $class) {
                if (! in_array($class, ['font-normal', 'font-semibold', 'font-sans'], true)) {
                    $broken[] = $class;
                }
            }

            return $broken;
        },
        'arbitrary' => fn (string $source): array => bookingContractMatches('/(?<![\w])[a-z0-9:-]+-\[[^\]\s]*\]/', $source),
        'heroicon' => fn (string $source): array => bookingContractMatches('/<x-heroicon/', $source),
        'phone' => function (string $source): array {
            $withoutDrawings = preg_replace('#<svg\b.*?</svg>#s', '', $source) ?? $source;

            return [
                ...bookingContractMatches('/\(?\d{3}\)?[\s.-]\d{3}[\s.-]\d{4}/', $withoutDrawings),
                ...bookingContractMatches('/\+\d{7,}|(?<![\d.])\d{10,}(?![\d.])/', $withoutDrawings),
            ];
        },
    ];
}

/**
 * The words a customer reads, scripts and styles out and tags stripped but the no-script line kept.
 */
function bookingContractReadable(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html) ?? $html;

    return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The page in every state a customer can reach, as HTML by state name. Side effects pile up in a fixed order: the
 * failed booking first (its stand-in is dropped straight after), then the taken time, the booked link's three lives,
 * a replaced link, and last one link seen with no times, with the service off, expired, and the rate limit tripped.
 *
 * @return array<string, string>
 */
function bookingContractEveryState(): array
{
    [$contact, $conversation, $job] = bookingPageSetUp();
    [$link, $token] = bookingPageMint($conversation);
    $tuesday = bookingPageChicago('2026-11-03 10:00')->toIso8601String();
    $pages = [];

    $pages['picker'] = test()->get(route('booking.show', $token))->getContent();
    $pages['check'] = Livewire::test(BookingPage::class, ['token' => $token])->call('choose', $tuesday)->html();

    test()->mock(BookAppointment::class, fn ($mock) => $mock->shouldReceive('fromLink')->andThrow(new RuntimeException('broken')));
    $pages['failed'] = Livewire::test(BookingPage::class, ['token' => $token])->call('choose', $tuesday)->call('book')->html();
    app()->forgetInstance(BookAppointment::class);

    $taken = Livewire::test(BookingPage::class, ['token' => $token])->call('choose', $tuesday);
    app(BookAppointment::class)->byHand($contact, User::factory()->create(), 'ac-repair', bookingPageChicago('2026-11-03 10:00'), $job->id, null);
    $pages['taken'] = $taken->call('book')->html();

    $appointment = app(BookAppointment::class)->fromLink($link, bookingPageChicago('2026-11-04 10:00'), CarbonImmutable::now());
    $pages['booked'] = test()->get(route('booking.show', $token))->getContent();

    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();
    $pages['cancelled'] = test()->get(route('booking.show', $token))->getContent();

    $appointment->forceFill(['state' => AppointmentState::Done])->save();
    $pages['done'] = test()->get(route('booking.show', $token))->getContent();

    $pages['does not work'] = test()->get(route('booking.show', str_repeat('e', 64)))->getContent();

    $other = Contact::factory()->create(['name' => 'Sam Rivera', 'phone_e164' => '+12025550188']);
    $otherConversation = Conversation::factory()->for($other)->create();
    Message::factory()->for($otherConversation)->create(['body' => 'My furnace is loud']);
    [, $replacedToken] = bookingPageMint($otherConversation);
    bookingPageMint($otherConversation);
    $pages['replaced'] = test()->get(route('booking.show', $replacedToken))->getContent();

    $third = Contact::factory()->create(['name' => 'Lee Ortiz', 'phone_e164' => '+12025550177']);
    $thirdConversation = Conversation::factory()->for($third)->create();
    Message::factory()->for($thirdConversation)->create(['body' => 'My heat pump is off']);
    [$lastLink, $lastToken] = bookingPageMint($thirdConversation);

    ScheduleBlock::factory()->at(bookingPageChicago('2026-11-03 00:00'), 60 * 24 * 17)->create();
    $pages['no times'] = test()->get(route('booking.show', $lastToken))->getContent();

    bookingPageResync(fn () => breakFile('business/services/ac-repair.md', fn (string $file): string => str_replace('book_online: true', 'book_online: false', $file)));
    $pages['service unavailable'] = test()->get(route('booking.show', $lastToken))->getContent();

    test()->travelTo($lastLink->expires_at->toImmutable()->addMinute());
    app()->forgetScopedInstances();
    $pages['expired'] = test()->get(route('booking.show', $lastToken))->getContent();

    foreach (range(1, 30) as $try) {
        test()->get(route('booking.show', $lastToken));
    }

    $pages['rate limited'] = test()->get(route('booking.show', $lastToken))->assertStatus(429)->getContent();

    return $pages;
}

it('covers every customer view', function () {
    $views = array_keys(bookingContractViews());

    expect($views)->toContain('resources/views/layouts/customer.blade.php')
        ->toContain('resources/views/livewire/booking-page.blade.php')
        ->toContain('resources/views/booking/rate-limited.blade.php')
        ->toContain('resources/views/components/booking/day.blade.php')
        ->toContain('resources/views/components/booking/icon/exclamation-circle.blade.php')
        ->and(count($views))->toBeGreaterThanOrEqual(13);
});

it('uses no style attribute or style element', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['style']($source))->toBe([], $path);
    }
});

it('uses no hex color and no raw palette class', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['color']($source))->toBe([], $path);
    }
});

it('uses only the three sizes and two weights', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['type']($source))->toBe([], $path);
    }
});

it('uses no arbitrary values', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['arbitrary']($source))->toBe([], $path);
    }
});

it('uses no heroicon component', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['heroicon']($source))->toBe([], $path);
    }
});

it('writes no exclamation point or em dash a customer reads', function () {
    foreach (bookingContractEveryState() as $state => $html) {
        $text = bookingContractReadable($html);

        expect(trim($text))->not->toBe('')
            ->and(str_contains($text, '!'))->toBeFalse("The {$state} page has an exclamation point")
            ->and(str_contains($text, "\u{2014}"))->toBeFalse("The {$state} page has an em dash");
    }
});

it('reads the texting number from the folder', function () {
    foreach (bookingContractViews() as $path => $source) {
        expect(bookingContractRules()['phone']($source))->toBe([], $path);
    }

    activateExampleFolder();
    $dead = fn (): string => $this->get(route('booking.show', str_repeat('f', 64)))->getContent();
    $link = fn (string $html): string => bookingPageXpath($html)->query('//main//a[starts-with(@href, "sms:")]')->item(0)->getAttribute('href');

    expect($link($dead()))->toBe('sms:+13125550147')
        ->and(bookingPageText($dead()))->toContain('(312) 555-0147');

    bookingPageResync(fn () => breakFile('business/profile.md', fn (string $file): string => str_replace('+13125550147', '+13125550188', $file)));

    expect($link($dead()))->toBe('sms:+13125550188')
        ->and(bookingPageText($dead()))->toContain('(312) 555-0188')
        ->not->toContain('555-0147');
});

it('is checked against bad snippets, so no rule is vacuous', function (string $rule, string $snippet) {
    expect(bookingContractRules()[$rule]($snippet))->not->toBe([]);
})->with([
    'a style attribute' => ['style', '<p style="color: red">Hi</p>'],
    'a style element' => ['style', '<style>p { margin: 0 }</style>'],
    'a hex color' => ['color', '<p class="x" data-c="#a1b2c3">Hi</p>'],
    'a raw palette class' => ['color', '<p class="bg-amber-800">Hi</p>'],
    'a raw palette class behind a variant' => ['color', '<p class="hover:text-stone-900">Hi</p>'],
    'a white class' => ['color', '<p class="text-white">Hi</p>'],
    'a size outside the three' => ['type', '<p class="text-sm">Hi</p>'],
    'a large size' => ['type', '<p class="md:text-2xl">Hi</p>'],
    'a weight outside the two' => ['type', '<p class="font-bold">Hi</p>'],
    'an arbitrary spacing value' => ['arbitrary', '<p class="p-[13px]">Hi</p>'],
    'an arbitrary color' => ['arbitrary', '<p class="bg-[var(--x)]">Hi</p>'],
    'a heroicon component' => ['heroicon', '<x-heroicon-o-check class="size-6" />'],
    'a national phone number' => ['phone', '<p>Text us at (312) 555-0147</p>'],
    'an international phone number' => ['phone', '<a href="sms:+13125550147">Text</a>'],
]);
