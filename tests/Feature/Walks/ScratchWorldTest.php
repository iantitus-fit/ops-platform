<?php

use App\Actions\DispatchInbound;
use App\Ai\RunFrontDeskAgent;
use App\Channels\FakeTextChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\Availability;
use App\SendRule\Classifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk that names its start (a Tuesday morning, while the shop is open), so every case runs at the same moment of the
 * business's day whatever the real date is.
 */
function scratchWalk(string $extraCheck = 'sent on its own'): WalkFile
{
    return walkFileFrom(str_replace(
        ["channel: sms\n", 'expect: [sent on its own]'],
        ["channel: sms\nstart: \"2026-12-01 10:00\"\n", "expect: [{$extraCheck}]"],
        minimalWalkFile(),
    ));
}

/**
 * A tape for one customer turn that gets a sendable intake question.
 */
function scratchSendableTape(): WalkTape
{
    return walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);
}

/**
 * Every global a walk swaps, as it reads now, so a test can compare before and after.
 *
 * @return array<string, mixed>
 */
function scratchGlobals(): array
{
    return [
        'database' => config('database.default'),
        'walkConnection' => config('database.connections.walk'),
        'walkConnectionOpen' => array_key_exists('walk', DB::getConnections()),
        'icmPath' => config('icm.path'),
        'driver' => config('channels.text.driver'),
        'queue' => config('queue.default'),
        'cache' => Cache::getDefaultDriver(),
        'walkCacheStore' => config('cache.stores.walk'),
        'stray' => Http::preventingStrayRequests(),
        'testNow' => Carbon::getTestNow()?->toIso8601String(),
        'classifier' => spl_object_id(app(Classifier::class)),
        'channel' => spl_object_id(app(FakeTextChannel::class)),
        'modelBound' => app()->bound(RunFrontDeskAgent::class),
        'dispatchBound' => app()->bound(DispatchInbound::class),
    ];
}

it('sends nothing real with Twilio configured', function () {
    config([
        'channels.text.driver' => 'twilio',
        'services.twilio.account_sid' => 'ACwalkworldtest',
        'services.twilio.auth_token' => 'not-a-real-token',
        'services.twilio.messaging_service_sid' => 'MGwalkworldtest',
    ]);

    $compiled = activateExampleFolder()->compiled;
    $inside = [];

    app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function (ScratchWorld $world) use (&$inside): void {
        $inside = [
            'driver' => config('channels.text.driver'),
            'statuses' => Message::query()->where('direction', MessageDirection::Out)->pluck('status')->all(),
            'texts' => $world->textChannel()->sent(),
        ];
    });

    expect($inside['driver'])->toBe('fake')
        ->and($inside['statuses'])->toBe([MessageStatus::Sent])
        ->and($inside['texts'])->toHaveCount(1)
        ->and(config('channels.text.driver'))->toBe('twilio')
        ->and(textsSentToCustomers())->toBe([]);
});

it('shuts HTTP for the whole walk and puts it back', function () {
    $compiled = activateExampleFolder()->compiled;
    Http::fake();
    Http::preventStrayRequests(false);

    $inside = null;

    app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function () use (&$inside): void {
        $inside = Http::preventingStrayRequests();
    });

    expect($inside)->toBeTrue()
        ->and(Http::preventingStrayRequests())->toBeFalse();

    Http::assertNothingSent();
});

it('never reads a live row', function () {
    $compiled = activateExampleFolder()->compiled;
    $liveContact = Contact::factory()->create(['name' => 'Live Lucy', 'phone_e164' => WalkRunner::PERSONA_PHONE]);
    $liveAppointment = Appointment::factory()->at(CarbonImmutable::parse('2026-12-01 10:00', 'America/Chicago'), 90)->create(['contact_id' => $liveContact->id]);
    $now = CarbonImmutable::parse('2026-12-01 07:00', 'America/Chicago');
    $startsOf = fn (array $days): array => array_map(fn (CarbonImmutable $start): string => $start->setTimezone('America/Chicago')->format('H:i'), $days['2026-12-01'] ?? []);

    $liveStarts = $startsOf(app(Availability::class)->openStarts(app(ActiveConfig::class)->service('ac-repair'), $now));
    $counts = liveRowCounts();
    $inside = [];

    app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function () use (&$inside, $startsOf, $now): void {
        $inside = [
            'contacts' => Contact::query()->pluck('name')->all(),
            'appointments' => Appointment::query()->count(),
            'starts' => $startsOf(app(Availability::class)->openStarts(app(ActiveConfig::class)->service('ac-repair'), $now)),
        ];
    });

    expect($liveStarts)->not->toContain('10:00')
        ->and($inside['contacts'])->toHaveCount(1)
        ->and($inside['contacts'])->not->toContain('Live Lucy')
        ->and($inside['appointments'])->toBe(0)
        ->and($inside['starts'])->toContain('10:00')
        ->and(liveRowCounts())->toBe($counts)
        ->and($liveAppointment->fresh()->contact_id)->toBe($liveContact->id);
});

it('alerts no real owner', function () {
    $compiled = activateExampleFolder()->compiled;
    User::factory()->create(['phone' => '+13125550111']);

    $tape = walkTape([1 => ['readings' => [walkScores('intake', 0.97, ['asks_for_person' => 0.95])]]]);
    $counts = liveRowCounts();
    $inside = [];

    $result = app(WalkRunner::class)->replay(scratchWalk('owner alerted'), $compiled, $tape, function (ScratchWorld $world) use (&$inside): void {
        $inside = [
            'recipients' => collect($world->textChannel()->sent())->pluck('to')->all(),
            'alerts' => OwnerAlert::query()->count(),
        ];
    });

    expect($result->exhausted)->toBeNull()
        ->and($inside['recipients'])->toContain(ScratchWorld::OWNER_PHONE)
        ->and($inside['recipients'])->not->toContain('+13125550111')
        ->and($inside['alerts'])->toBeGreaterThan(0)
        ->and(textsSentToCustomers())->toBe([])
        ->and(OwnerAlert::query()->count())->toBe(0)
        ->and(liveRowCounts())->toBe($counts);
});

it('restores every global after a walk that throws (edge: concurrency)', function () {
    $compiled = activateExampleFolder()->compiled;
    Carbon::setTestNow('2026-11-20 08:15:00');

    $before = scratchGlobals();
    $recordsPath = null;

    expect($before['modelBound'])->toBeFalse()
        ->and($before['dispatchBound'])->toBeFalse()
        ->and($before['testNow'])->not->toBeNull();

    expect(function () use ($compiled, &$recordsPath) {
        app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function (ScratchWorld $world) use (&$recordsPath): void {
            $recordsPath = $world->recordsPath();

            throw new RuntimeException('the walk broke');
        });
    })->toThrow(RuntimeException::class, 'the walk broke');

    expect(scratchGlobals())->toBe($before)
        ->and($recordsPath)->not->toBeNull()
        ->and(File::isDirectory($recordsPath))->toBeFalse();
});

it('restores every global when opening fails part way', function () {
    Carbon::setTestNow('2026-11-20 08:15:00');
    $before = scratchGlobals();

    expect(fn () => ScratchWorld::open(['not' => NAN]))->toThrow(JsonEncodingException::class);

    expect(scratchGlobals())->toBe($before);
});

it('closes safely twice and the second close changes nothing', function () {
    $world = ScratchWorld::open(activateExampleFolder()->compiled);
    $world->close();

    $before = scratchGlobals();
    config(['queue.default' => 'database']);

    $world->close();

    expect(config('queue.default'))->toBe('database');

    config(['queue.default' => $before['queue']]);

    expect(scratchGlobals())->toBe($before);
});

it('reads the live version after a walk (Pitfall 6)', function () {
    IcmVersion::factory()->count(2)->create();
    $compiled = activateExampleFolder()->compiled;
    $liveId = app(ActiveConfig::class)->version()->id;
    $inside = null;

    app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function () use (&$inside): void {
        $inside = app(ActiveConfig::class)->version()->fingerprint;
    });

    expect($inside)->toBe('walk')
        ->and($liveId)->toBeGreaterThan(2)
        ->and(app(ActiveConfig::class)->version()->id)->toBe($liveId);
});

it('starts every walk from an empty world', function () {
    $compiled = activateExampleFolder()->compiled;
    $seen = [];

    foreach ([1, 2] as $run) {
        app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function () use (&$seen, $run): void {
            $seen[$run] = [
                'contacts' => Contact::query()->count(),
                'conversations' => Conversation::query()->count(),
                'inbound' => Message::query()->where('direction', MessageDirection::In)->count(),
                'messages' => Message::query()->count(),
                'versions' => IcmVersion::query()->count(),
                'users' => User::query()->count(),
            ];
        });
    }

    expect($seen[1])->toMatchArray(['contacts' => 1, 'conversations' => 1, 'inbound' => 1, 'versions' => 1, 'users' => 1])
        ->and($seen[2])->toBe($seen[1]);
});

it("writes no card into the owner's folder", function () {
    $compiled = activateExampleFolder()->compiled;
    $ownersFolder = config('icm.path');
    $before = collect(File::allFiles(icmPath('records')))->map(fn ($file) => $file->getRelativePathname())->sort()->values()->all();
    $inside = [];

    app(WalkRunner::class)->replay(scratchWalk(), $compiled, scratchSendableTape(), function (ScratchWorld $world) use (&$inside): void {
        $inside = [
            'path' => config('icm.path'),
            'cards' => collect(File::allFiles($world->recordsPath().'/records'))->map(fn ($file) => $file->getRelativePathname())->all(),
        ];
    });

    $after = collect(File::allFiles(icmPath('records')))->map(fn ($file) => $file->getRelativePathname())->sort()->values()->all();

    expect($inside['path'])->not->toBe($ownersFolder)
        ->and($inside['cards'])->not->toBe([])
        ->and($after)->toBe($before);
});
