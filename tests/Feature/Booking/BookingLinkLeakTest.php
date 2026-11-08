<?php

use App\Enums\MessageStatus;
use App\Livewire\ConversationThread;
use App\Models\BookingLink;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\BookingLinkTokens;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * The leak test gap D-12 asks for (08-VERIFICATION.md): a booking link is sent through the real chokepoint, then every
 * table, the business folder, the log and the queued jobs are searched for the working token. D-12 says a copy of the
 * database or of the business folder holds no working link, and the customer's phone still gets one.
 *
 * The tables are listed by the database itself: Schema::getTables() reads pragma_table_list on SQLite (SQLite's own
 * sqlite_ tables left out) and pg_class on Postgres (every schema except information_schema and the pg_ ones), and each
 * table is read through its schema_qualified_name (main.x on SQLite, public.x on Postgres), so a table a later phase adds
 * is searched without a change here.
 */
const BOOKING_LEAK_CUSTOMER_PHONE = '+12025550123';

const BOOKING_LEAK_OWNER_PHONE = '+12025550199';

/**
 * Dana's scheduling question answered by the front desk's scripted scheduling reply, sent with the owner's link line.
 * Returns the working token as it reached Dana's phone.
 */
function bookingLeakSchedulingReplySent(): string
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone(BOOKING_LEAK_OWNER_PHONE)->create();

    frontDeskCustomerTexts('Can someone come look at my AC this week?');
    scriptFrontDeskReplies([
        'reply' => 'We can get a tech out this week.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ]);
    runQueuedFrontDeskJobs();

    $token = bookingLeakTokenIn(textsSentToCustomers()[0]['body'] ?? '');

    expect($token)->not->toBeNull();

    return $token;
}

/**
 * A held scheduling draft (its words promise a day, so the send rule holds them) that the owner approves with the box
 * for the booking link left on (D-45). Returns the working token as it reached Dana's phone.
 */
function bookingLeakApprovedDraftSent(): string
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    $owner = User::factory()->withPhone(BOOKING_LEAK_OWNER_PHONE)->create();

    frontDeskCustomerTexts('Can you come out Thursday morning?');
    scriptFrontDeskReplies([
        'reply' => 'We guarantee 9 am Thursday. Does that work?',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ]);
    runQueuedFrontDeskJobs();
    test()->actingAs($owner);

    expect(Message::query()->where('status', MessageStatus::Draft->value)->sole()->body)->not->toContain('/book/');

    Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()])
        ->assertSet('addBookingLink', true)
        ->call('approveDraft')
        ->assertHasNoErrors()
        ->assertNotified(Notification::make()->title('Draft sent')->success());

    $received = array_values(array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] !== BOOKING_LEAK_OWNER_PHONE));
    $token = bookingLeakTokenIn($received[1]['body'] ?? '');

    expect($token)->not->toBeNull();

    return $token;
}

/**
 * The token in the one booking address a text carries, or null.
 */
function bookingLeakTokenIn(string $body): ?string
{
    return preg_match('~/book/([0-9a-f]{64})~', $body, $match) === 1 ? $match[1] : null;
}

/**
 * The name of every table the database lists.
 *
 * @return list<string>
 */
function bookingLeakTables(): array
{
    return array_values(array_map(fn (array $table): string => $table['name'], Schema::getTables()));
}

/**
 * Where the needle is held: every column of every table the database lists, read raw and, when the value is valid
 * base64, decoded (the database session driver stores its payload that way), then every file under the business folder.
 * A table's column is named 'table.column' and a file by its path inside the folder.
 *
 * @return list<string>
 */
function bookingLeakPlacesHolding(string $needle): array
{
    $places = [];

    foreach (Schema::getTables() as $table) {
        foreach (DB::table($table['schema_qualified_name'])->get() as $row) {
            foreach ((array) $row as $column => $value) {
                $text = match (true) {
                    is_resource($value) => (string) stream_get_contents($value),
                    $value === null => '',
                    default => (string) $value,
                };

                $decoded = base64_decode($text, true);

                if (str_contains($text, $needle) || ($decoded !== false && str_contains($decoded, $needle))) {
                    $places[] = $table['name'].'.'.$column;
                }
            }
        }
    }

    $folder = rtrim(config('icm.path'), '/');

    foreach (File::allFiles($folder, true) as $file) {
        if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
            $places[] = substr($file->getPathname(), strlen($folder) + 1);
        }
    }

    $places = array_values(array_unique($places));
    sort($places);

    return $places;
}

/**
 * The texts and their hidden forms the hiding rule is held to, named for the dataset. Built when a case runs, because
 * route() needs the booted app.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function bookingLeakHideCases(): array
{
    $token = str_repeat('ab12', 16);
    $other = str_repeat('f0', 32);

    return [
        'a booking address' => [route('booking.show', $token), str_replace($token, '[link]', route('booking.show', $token))],
        'a booking address with the token in upper case' => [route('booking.show', strtoupper($token)), str_replace(strtoupper($token), '[link]', route('booking.show', strtoupper($token)))],
        'a run of 70 hex characters' => ['/book/'.str_repeat('a', 70), '/book/[link]'],
        'two addresses in one sentence' => ['Try '.route('booking.show', $token).' or '.route('booking.show', $other).' please', 'Try '.str_replace($token, '[link]', route('booking.show', $token)).' or '.str_replace($other, '[link]', route('booking.show', $other)).' please'],
        'a path in upper case' => ['/BOOK/'.str_repeat('A', 64), '/BOOK/[link]'],
        '63 hex characters' => ['/book/'.str_repeat('a', 63), '/book/'.str_repeat('a', 63)],
        'a bare 64-hex string' => [$token, $token],
        'an empty string' => ['', ''],
        'an already hidden address' => ['https://x.test/book/[link]', 'https://x.test/book/[link]'],
    ];
}

it('hides a booking address and nothing else', function (string $case) {
    [$text, $hidden] = bookingLeakHideCases()[$case];

    expect(BookingLinkTokens::hide($text))->toBe($hidden);
})->with([
    'a booking address',
    'a booking address with the token in upper case',
    'a run of 70 hex characters',
    'two addresses in one sentence',
    'a path in upper case',
    '63 hex characters',
    'a bare 64-hex string',
    'an empty string',
    'an already hidden address',
]);

it('gives the customer a working link and stores the scheduling reply with it hidden (D-12)', function () {
    $token = bookingLeakSchedulingReplySent();
    $link = BookingLink::sole();
    $received = textsSentToCustomers()[0]['body'];

    expect(hash('sha256', $token))->toBe($link->token_hash);

    $this->get(route('booking.show', $token))->assertOk()->assertSee('Book AC repair');

    $stored = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect(bookingLeakPlacesHolding($token))->toBe([])
        ->and($stored->body)->toBe(BookingLinkTokens::hide($received))
        ->and($stored->body)->toContain('/book/[link]')
        ->and($stored->meta['parts'])->toBe(['model_words', 'booking_link']);

    $cards = collect(File::allFiles(config('icm.path').'/records/customers'))
        ->filter(fn ($file): bool => str_contains((string) file_get_contents($file->getPathname()), '/book/'));

    expect($cards)->not->toBeEmpty();
});

it('stores an approved scheduling draft with its link hidden (D-12, D-45)', function () {
    $token = bookingLeakApprovedDraftSent();
    $link = BookingLink::sole();

    expect(hash('sha256', $token))->toBe($link->token_hash);

    $this->get(route('booking.show', $token))->assertOk()->assertSee('Book AC repair');

    $received = collect(textsSentToCustomers())->first(fn (array $text): bool => str_contains($text['body'], $token))['body'];
    $stored = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect(bookingLeakPlacesHolding($token))->toBe([])
        ->and($stored->body)->toBe(BookingLinkTokens::hide($received))
        ->and($stored->body)->toContain('/book/[link]');
});

it('lists every table and finds a planted token, so the search is not vacuous', function () {
    expect(bookingLeakTables())->toContain('messages', 'activities', 'booking_links', 'owner_alerts', 'jobs', 'failed_jobs', 'sessions', 'cache');

    $planted = bin2hex(random_bytes(32));
    $hiddenInCache = bin2hex(random_bytes(32));

    frontDeskCustomerTexts('Hello there');

    $activity = DB::table('activities')->first();

    expect($activity)->not->toBeNull();

    DB::table('activities')->where('id', $activity->id)->update(['summary' => 'Pasted '.route('booking.show', $planted)]);
    DB::table('cache')->insert(['key' => 'planted', 'value' => base64_encode('serialized '.route('booking.show', $hiddenInCache)), 'expiration' => now()->addDay()->timestamp]);

    expect(bookingLeakPlacesHolding($planted))->toContain('activities.summary')
        ->and(bookingLeakPlacesHolding($hiddenInCache))->toContain('cache.value');
});
