<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Events\CustomerRecordChanged;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Records\CustomerCard;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

/**
 * Gap D-12's one-time migration, run on rows and a card file written the way they were before plan 08-17 hid the
 * booking address (08-VERIFICATION.md): every booking link that has not booked is revoked, so a copy of the database or
 * the folder made before the fix holds no working link, and the stored texts are left as they were.
 */
const LINKS_BEFORE_FIX_MIGRATION = 'database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php';

/**
 * Run the migration's up() on whatever rows are there.
 */
function linksBeforeFixRunMigration(): void
{
    (require base_path(LINKS_BEFORE_FIX_MIGRATION))->up();
}

/**
 * A contact with a phone of their own and an open conversation.
 *
 * @return array{0: Contact, 1: Conversation}
 */
function linksBeforeFixCustomer(string $name, string $phone): array
{
    $contact = Contact::factory()->create(['name' => $name, 'phone_e164' => $phone]);

    return [$contact, Conversation::factory()->for($contact)->create()];
}

/**
 * A booking text the way 08-17 changed it: sent, carrying the link, and attached to the link.
 */
function linksBeforeFixCarryingText(BookingLink $link, Conversation $conversation, string $body = 'Here is the link to book.'): Message
{
    $message = Message::factory()->for($conversation)->outbound()->create([
        'body' => $body,
        'meta' => ['booking_link_id' => $link->id],
    ]);

    app(MintBookingLink::class)->attachMessage($link, $message);

    return $message;
}

/**
 * The state the migration meets: the live example folder, the clock at 2026-11-03 11:00 in the shop's zone, and one link
 * in each state a link can be in, with texts and a card file written the old way (the whole address, token and all).
 *
 * @return array<string, mixed>
 */
function linksBeforeFixSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();

    $mint = app(MintBookingLink::class);
    $now = CarbonImmutable::now();

    [$contactA, $conversationA] = linksBeforeFixCustomer('Dana Whitfield', '+12025550123');
    [$contactB, $conversationB] = linksBeforeFixCustomer('Ben Ortiz', '+12025550124');
    [$contactC, $conversationC] = linksBeforeFixCustomer('Cara Lindqvist', '+12025550125');
    [$contactD, $conversationD] = linksBeforeFixCustomer('Dev Patel', '+12025550126');
    [$contactE, $conversationE] = linksBeforeFixCustomer('Eli Moreau', '+12025550127');
    [$contactF, $conversationF] = linksBeforeFixCustomer('Fay Nakamura', '+12025550128');

    [$open, $openToken] = $mint->handle($conversationA, 'ac-repair', $now);
    [$first, $firstToken] = $mint->handle($conversationB, 'ac-repair', $now);
    [$second, $secondToken] = $mint->handle($conversationB, 'ac-repair', $now);
    [$booked, $bookedToken] = $mint->handle($conversationC, 'ac-repair', $now);
    [$expired, $expiredToken] = $mint->handle($conversationD, 'ac-repair', $now);
    [$earlier, $earlierToken] = $mint->handle($conversationE, 'ac-repair', $now);

    linksBeforeFixCarryingText($booked, $conversationC);
    app(BookAppointment::class)->fromLink($booked->fresh(), CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), $now);

    $expired->forceFill(['expires_at' => $now->subHour()])->save();
    $mint->revoke($earlier);

    $openBody = 'Pick a time that works for you here: '.route('booking.show', $openToken);
    $expiredBody = 'Here is that link again: '.route('booking.show', $expiredToken);

    $openText = Message::factory()->for($conversationA)->outbound()->create(['body' => $openBody]);
    $expiredText = Message::factory()->for($conversationF)->create(['body' => $expiredBody]);

    $filename = app(CustomerCard::class)->filename(...);
    File::ensureDirectoryExists(icmPath('records/customers'));
    File::put(icmPath('records/customers/'.$filename($contactA)), "# Dana Whitfield\n\n- {$openBody}\n");
    File::put(icmPath('records/customers/'.$filename($contactF)), "# Fay Nakamura\n\n- {$expiredBody}\n");
    File::put(icmPath('records/open-leads.md'), "# Open leads\n\n- Fay Nakamura: {$expiredBody}\n");

    test()->travelTo(CarbonImmutable::parse('2026-11-03 13:00', 'America/Chicago'));

    return [
        'contacts' => ['A' => $contactA, 'F' => $contactF],
        'links' => ['open' => $open, 'first' => $first, 'second' => $second, 'booked' => $booked, 'expired' => $expired, 'earlier' => $earlier],
        'tokens' => ['open' => $openToken, 'first' => $firstToken, 'second' => $secondToken, 'booked' => $bookedToken, 'expired' => $expiredToken, 'earlier' => $earlierToken],
        'texts' => ['open' => $openText, 'expired' => $expiredText],
        'bodies' => ['open' => $openBody, 'expired' => $expiredBody],
        'earlierRevokedAt' => $earlier->fresh()->revoked_at->toIso8601String(),
    ];
}

/**
 * The files under the business folder that hold the needle, each by its path inside the folder.
 *
 * @return list<string>
 */
function linksBeforeFixFilesHolding(string $needle): array
{
    $folder = rtrim(config('icm.path'), '/');
    $files = [];

    foreach (File::allFiles($folder, true) as $file) {
        if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
            $files[] = substr($file->getPathname(), strlen($folder) + 1);
        }
    }

    sort($files);

    return $files;
}

it('revokes every link sent before the fix that has not booked (D-12)', function () {
    $state = linksBeforeFixSetUp();

    linksBeforeFixRunMigration();

    foreach (['open', 'first', 'second', 'expired'] as $name) {
        expect($state['links'][$name]->fresh()->revoked_at?->toIso8601String())->toBe(now()->toIso8601String(), "link {$name}");
    }

    $this->get(route('booking.show', $state['tokens']['open']))->assertOk()->assertSee('This link does not work');

    expect(fn () => app(BookAppointment::class)->fromLink(
        $state['links']['open']->fresh(),
        CarbonImmutable::parse('2026-11-06 10:00', 'America/Chicago'),
        CarbonImmutable::now(),
    ))->toThrow(BookingRefused::class, 'This link cannot book a time now.');
});

it('leaves a booked link and an earlier revocation as they were (D-12, U-14)', function () {
    $state = linksBeforeFixSetUp();

    linksBeforeFixRunMigration();

    expect($state['links']['booked']->fresh()->revoked_at)->toBeNull()
        ->and($state['links']['earlier']->fresh()->revoked_at->toIso8601String())->toBe($state['earlierRevokedAt'])
        ->and($state['earlierRevokedAt'])->not->toBe(now()->toIso8601String());

    $this->get(route('booking.show', $state['tokens']['booked']))->assertOk()->assertSee('You are booked');
});

it('rewrites no stored text (D-12)', function () {
    $state = linksBeforeFixSetUp();

    linksBeforeFixRunMigration();

    expect($state['texts']['open']->fresh()->body)->toBe($state['bodies']['open'])
        ->and($state['texts']['expired']->fresh()->body)->toBe($state['bodies']['expired'])
        ->and($state['bodies']['open'])->toContain($state['tokens']['open'])
        ->and($state['bodies']['expired'])->toContain($state['tokens']['expired']);
});

it('redraws the folder so no file holds a token from before the fix (D-12)', function () {
    $state = linksBeforeFixSetUp();

    expect(linksBeforeFixFilesHolding($state['tokens']['open']))->not->toBeEmpty()
        ->and(linksBeforeFixFilesHolding($state['tokens']['expired']))->not->toBeEmpty();

    linksBeforeFixRunMigration();

    foreach ($state['tokens'] as $name => $token) {
        expect(linksBeforeFixFilesHolding($token))->toBe([], "token {$name}");
    }

    $card = icmPath('records/customers/'.app(CustomerCard::class)->filename($state['contacts']['A']));

    expect(File::get($card))->toContain('/book/');
});

it('logs the ids it revoked and changes nothing when it runs again', function () {
    $state = linksBeforeFixSetUp();
    $logged = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        if (isset($event->context['booking_link_ids'])) {
            $logged[] = $event->context['booking_link_ids'];
        }
    });

    linksBeforeFixRunMigration();

    $revokedIds = collect(['open', 'first', 'second', 'expired'])->map(fn (string $name): int => $state['links'][$name]->id)->sort()->values()->all();
    $firstRun = $state['links']['open']->fresh()->revoked_at->toIso8601String();

    expect($logged)->toHaveCount(1)
        ->and(collect($logged[0])->sort()->values()->all())->toBe($revokedIds);

    $this->travel(2)->hours();
    linksBeforeFixRunMigration();

    expect($state['links']['open']->fresh()->revoked_at->toIso8601String())->toBe($firstRun)
        ->and($state['links']['booked']->fresh()->revoked_at)->toBeNull()
        ->and($logged)->toHaveCount(1);
});

it('never fails on a card redraw that throws', function () {
    $state = linksBeforeFixSetUp();
    Exceptions::fake();
    Event::listen(CustomerRecordChanged::class, function (): void {
        throw new RuntimeException('The business folder is not mounted.');
    });

    linksBeforeFixRunMigration();

    expect($state['links']['open']->fresh()->revoked_at)->not->toBeNull()
        ->and($state['links']['first']->fresh()->revoked_at)->not->toBeNull();

    Exceptions::assertReported(RuntimeException::class);
});
