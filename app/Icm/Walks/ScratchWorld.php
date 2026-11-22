<?php

namespace App\Icm\Walks;

use App\Channels\FakeTextChannel;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A world of its own for one walk: an empty database, an empty records folder, a fake text carrier and a clock that only
 * the walk moves, with the candidate folder as the one live version. Whatever a walk does happens in here and nowhere
 * else, and close() puts every part of the real app back.
 *
 * Why a scratch database and not a rolled-back transaction (Claude's Discretion, 10-RESEARCH A1 and Pattern 1): a
 * transaction on the live connection would still read the live appointments (Availability reads every one), could take
 * an expected time, would burn Postgres sequence numbers and would hold events that wait for the commit. A database that
 * only this walk can see has none of those problems. It is an in-memory SQLite database named "walk", made the default
 * connection while the world is open and migrated from the app's own migrations, so every model query in the walk lands
 * in it. A new world is made for each walk, so the second walk starts from nothing.
 *
 * What open() swaps, and what close() puts back, in reverse order: Carbon's test clock, the default database connection
 * and the walk connection's settings, config('icm.path') and the temporary folder it points at, the text channel driver
 * and the FakeTextChannel instance, the queue and cache defaults, HTTP stray-request prevention, and the ActiveConfig
 * instance. The container entries a walk swaps (the Classifier, the model, DispatchInbound) go through instance() and
 * bind(), which remember what was there.
 *
 * The container's singletons and scoped bindings were checked for anything that holds folder configuration
 * (AppServiceProvider and the room providers): ActiveConfig is the only one, and it is forgotten on open and on close.
 * RoomRegistry holds only facts about code, and FakeGoogleListingSource only counts calls, so both are left alone.
 *
 * The owner in the world is fictional, with a number in the 555-01xx range, so an owner alert has someone to text and
 * the text goes to the world's fake carrier, never to the real owner.
 */
class ScratchWorld
{
    /**
     * The fictional owner's phone inside a world.
     */
    public const string OWNER_PHONE = '+12025550199';

    /**
     * What puts each swapped part back, in the order the parts were swapped. close() runs them last to first.
     *
     * @var list<Closure(): void>
     */
    private array $restores = [];

    private bool $closed = false;

    private FakeTextChannel $channel;

    private string $recordsPath;

    private function __construct() {}

    /**
     * Open a world holding the candidate as its one live version.
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy, as IcmVersion::compiled holds it.
     * @param  bool  $allowHttp  Leave HTTP open, for the one caller that must reach a real model (recording). Every replay leaves it shut.
     */
    public static function open(array $compiled, bool $allowHttp = false): self
    {
        $world = new self;

        $world->restoreClockLater();
        $world->swapDatabase();
        $world->swapRecordsFolder();
        $world->swapTextChannel();
        $world->swapQueueAndCache();

        if (! $allowHttp) {
            $world->shutHttp();
        }

        $world->restores[] = fn () => app()->forgetInstance(ActiveConfig::class);
        app()->forgetInstance(ActiveConfig::class);

        $world->seed($compiled);

        return $world;
    }

    /**
     * Swap a container entry for this object until the world closes, remembering what was there.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->rememberEntry($abstract);

        app()->instance($abstract, $instance);
    }

    /**
     * Swap a container binding for this closure until the world closes, remembering what was there.
     */
    public function bind(string $abstract, Closure $concrete): void
    {
        $this->rememberEntry($abstract);

        app()->bind($abstract, $concrete);
    }

    /**
     * The fake carrier this world sends through. Nothing sent here leaves the process.
     */
    public function textChannel(): FakeTextChannel
    {
        return $this->channel;
    }

    /**
     * The temporary records folder this world writes to, never the owner's records/.
     */
    public function recordsPath(): string
    {
        return $this->recordsPath;
    }

    /**
     * Stop the clock at this moment.
     */
    public function freezeAt(CarbonInterface $moment): void
    {
        Carbon::setTestNow($moment);
    }

    /**
     * Move the frozen clock forward. Only a walk's waits call this.
     */
    public function advance(int $minutes): void
    {
        Carbon::setTestNow(CarbonImmutable::now()->addMinutes($minutes));
    }

    /**
     * The frozen clock's moment.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    /**
     * Put the real app back: every part open() swapped, last swapped first. Safe to call twice.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        while (($restore = array_pop($this->restores)) !== null) {
            $restore();
        }
    }

    /**
     * Keep the test clock's current value, so close() sets it back and never leaves it null when a test had set one.
     */
    private function restoreClockLater(): void
    {
        $previous = Carbon::getTestNow();

        $this->restores[] = fn () => Carbon::setTestNow($previous);
    }

    /**
     * An in-memory SQLite connection named walk, made the default and filled with the app's tables.
     */
    private function swapDatabase(): void
    {
        $previousDefault = config('database.default');

        config(['database.connections.walk' => [...config('database.connections.sqlite'), 'database' => ':memory:', 'url' => null]]);

        $this->restores[] = function () use ($previousDefault): void {
            DB::setDefaultConnection($previousDefault);
            DB::purge('walk');

            $database = config('database');
            unset($database['connections']['walk']);
            config(['database' => $database]);
        };

        DB::setDefaultConnection('walk');

        Artisan::call('migrate', ['--database' => 'walk', '--force' => true]);
    }

    /**
     * A temporary folder for the records a walk writes, in place of the owner's.
     */
    private function swapRecordsFolder(): void
    {
        $files = new Filesystem;
        $previous = config('icm.path');

        $this->recordsPath = sys_get_temp_dir().'/walk-world-'.Str::uuid();
        $files->ensureDirectoryExists($this->recordsPath.'/records');

        config(['icm.path' => $this->recordsPath]);

        $this->restores[] = function () use ($files, $previous): void {
            config(['icm.path' => $previous]);
            $files->deleteDirectory($this->recordsPath);
        };
    }

    /**
     * The fake driver, and a fake carrier of this world's own, so no text can reach a real phone.
     */
    private function swapTextChannel(): void
    {
        $previousDriver = config('channels.text.driver');

        config(['channels.text.driver' => 'fake']);

        $this->restores[] = fn () => config(['channels.text.driver' => $previousDriver]);

        $this->channel = new FakeTextChannel;
        $this->instance(FakeTextChannel::class, $this->channel);
    }

    /**
     * Jobs run inline and locks live in an array that dies with the world, so nothing reaches the live queue or cache.
     */
    private function swapQueueAndCache(): void
    {
        $previousQueue = config('queue.default');
        $previousCache = config('cache.default');

        config(['queue.default' => 'sync', 'cache.stores.walk' => ['driver' => 'array', 'serialize' => false]]);
        Cache::setDefaultDriver('walk');

        $this->restores[] = function () use ($previousQueue, $previousCache): void {
            Cache::setDefaultDriver($previousCache);
            Cache::forgetDriver('walk');

            $cache = config('cache');
            unset($cache['stores']['walk']);
            config(['cache' => $cache, 'queue.default' => $previousQueue]);
        };
    }

    /**
     * No request leaves the process: an HTTP call nobody faked throws.
     */
    private function shutHttp(): void
    {
        $previous = Http::preventingStrayRequests();

        Http::preventStrayRequests();

        $this->restores[] = fn () => Http::preventStrayRequests($previous);
    }

    /**
     * The candidate as the one live version, and one fictional owner.
     *
     * @param  array<string, mixed>  $compiled
     */
    private function seed(array $compiled): void
    {
        IcmVersion::query()->create([
            'fingerprint' => 'walk',
            'compiled' => $compiled,
            'status' => VersionStatus::Active,
            'activated_at' => CarbonImmutable::now(),
        ]);

        User::query()->create([
            'name' => 'Walk owner',
            'email' => 'walk-owner@example.invalid',
            'password' => Str::random(40),
            'phone' => self::OWNER_PHONE,
        ]);
    }

    /**
     * Note what the container held for this name before a swap, so close() can put exactly that back: the binding, and the
     * instance when one had been made.
     */
    private function rememberEntry(string $abstract): void
    {
        $app = app();
        $binding = $app->getBindings()[$abstract] ?? null;
        $instance = $app->resolved($abstract) && $app->isShared($abstract) ? $app->make($abstract) : null;

        $this->restores[] = function () use ($app, $abstract, $binding, $instance): void {
            unset($app[$abstract]);

            if ($binding !== null) {
                $app->bind($abstract, $binding['concrete'], $binding['shared']);
            }

            if ($instance !== null) {
                $app->instance($abstract, $instance);
            }
        };
    }
}
