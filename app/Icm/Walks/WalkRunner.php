<?php

namespace App\Icm\Walks;

use App\Actions\BookAppointment;
use App\Actions\DispatchInbound;
use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Ai\BookingSectionWording;
use App\Ai\FrontDeskPrompt;
use App\Ai\RecordingFrontDeskAgent;
use App\Ai\ReplayFrontDeskAgent;
use App\Ai\RunFrontDeskAgent;
use App\Enums\MessageChannel;
use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Jobs\ProcessInbound;
use App\Models\BookingLink;
use App\Records\RecordsWriter;
use App\Rooms\ScheduleAndBooking\Availability;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\SendRule\Classifier;
use App\SendRule\RecordingClassifier;
use App\SendRule\ReplayClassifier;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Foundation\Application;

/**
 * Plays one walk against a candidate folder.
 *
 * The walk drives the front desk's real path in-process: the customer's text goes through RecordInbound, the job that
 * waits out the burst is run by hand the way the queue worker would run it, and the front desk, the send rule and the
 * outbound chokepoint run as they do for a real customer (10 D-01). Only two things are replaced, and only while the
 * world is open: the model and Jev answer from the tape. Everything runs inside a ScratchWorld, so no live row is read
 * or written, no real text is sent and every global is back as it was afterwards, whatever happens in between.
 */
class WalkRunner
{
    /**
     * The walk's fictional customer: a number in the 555-01xx range.
     */
    public const string PERSONA_PHONE = '+12025550142';

    /**
     * Play the walk once, from the tape.
     *
     * When the candidate asks for a model reply or a Jev reading the tape does not hold, the walk stops there and ends as
     * NeedsRecording; nothing is guessed (10 D-27).
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy.
     * @param  Closure(ScratchWorld): void|null  $inspect  Called with the open world once the steps have run, so a caller can read what the walk left in it.
     */
    public function replay(WalkFile $walk, array $compiled, WalkTape $tape, ?Closure $inspect = null): WalkResult
    {
        $began = CarbonImmutable::now();
        $world = ScratchWorld::open($compiled);

        try {
            $world->instance(Classifier::class, new ReplayClassifier($tape));
            $world->bind(RunFrontDeskAgent::class, fn (Application $app): RunFrontDeskAgent => new ReplayFrontDeskAgent($app->make(FrontDeskPrompt::class), $tape));

            return $this->play($walk, $world, $tape, $began, $inspect);
        } finally {
            $world->close();
        }
    }

    /**
     * Play the walk once with the real model and Jev, keeping what each of them answers on a new tape (10 D-03).
     *
     * The world is the same as a replay's, with two differences: HTTP is left open so the real providers can answer, and
     * the Classifier and the front desk model the container holds are wrapped by recorders instead of replaced. The text
     * channel is still the world's private fake and every row, file and clock is still the world's own, so nothing is
     * sent and nothing live is written. The returned result's tape holds the recording, and its fingerprint is the
     * candidate's.
     *
     * With a variant, the Booking section's two texts come from it for this walk only (10 D-18): it is bound in the world
     * before the first step and gone when the world closes.
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy.
     * @param  Closure(ScratchWorld): void|null  $inspect  Called with the open world once the steps have run.
     */
    public function record(WalkFile $walk, array $compiled, ?Closure $inspect = null, ?BookingSectionWording $variant = null): WalkResult
    {
        $began = CarbonImmutable::now();
        $world = ScratchWorld::open($compiled, allowHttp: true);

        try {
            $tape = new WalkTape;

            // Both are asked for before the recorders take their names in the container, so each recorder wraps the real one.
            $classifier = app(Classifier::class);
            $agent = app(RunFrontDeskAgent::class);

            $world->instance(Classifier::class, new RecordingClassifier($classifier, $tape));
            $world->bind(RunFrontDeskAgent::class, fn (Application $app): RunFrontDeskAgent => new RecordingFrontDeskAgent($app->make(FrontDeskPrompt::class), $agent, $tape));

            if ($variant !== null) {
                $world->instance(BookingSectionWording::class, $variant);
            }

            return $this->play($walk, $world, $tape, $began, $inspect);
        } finally {
            $world->close();
        }
    }

    /**
     * The walk's steps, played in an open world whose model and Jev are already in place: the same loop for a replay and a
     * recording.
     *
     * @param  Closure(ScratchWorld): void|null  $inspect
     */
    private function play(WalkFile $walk, ScratchWorld $world, WalkTape $tape, CarbonImmutable $began, ?Closure $inspect): WalkResult
    {
        $capture = new CaptureDispatchInbound;

        $world->instance(DispatchInbound::class, $capture);

        $world->freezeAt(app(WalkClock::class)->startFor($walk, app(BusinessClock::class), app(ActiveConfig::class)->timezone(), $began));

        $outcome = WalkOutcome::Passed;
        $reader = new WalkWorldReader;
        $services = collect(app(ActiveConfig::class)->services())->keyBy('slug')->all();
        $turns = [];
        $failure = null;

        foreach ($walk->steps as $step) {
            $before = $reader->mark();

            $refusal = null;

            match ($step->kind) {
                WalkStepKind::Says => $this->customerSays($step, $tape, $capture),
                WalkStepKind::Wait => $this->timePasses($step, $world),
                WalkStepKind::Books => $refusal = $this->customerBooks($step, $reader),
            };

            if ($tape->exhausted() !== null) {
                $outcome = WalkOutcome::NeedsRecording;

                break;
            }

            $persona = $reader->persona();
            $seen = $reader->turn($step->turn, $step->kind, $before, $persona);
            $turns[] = $seen;

            if ($refusal !== null) {
                $failure = new WalkFailure($step->turn, $refusal);
                $outcome = WalkOutcome::Failed;

                break;
            }

            $failure = $this->firstFailure($step->checks, $seen, $reader->world($persona), $services, $step->turn);

            if ($failure !== null) {
                $outcome = WalkOutcome::Failed;

                break;
            }
        }

        if ($outcome === WalkOutcome::Passed && $walk->endChecks !== []) {
            $end = $reader->world($reader->persona());
            $atTheEnd = WalkTurnObservation::quiet(count($walk->steps), WalkStepKind::Wait, $end->ownerAlertedInWalk);

            $failure = $this->firstFailure($walk->endChecks, $atTheEnd, $end, $services, null);
            $outcome = $failure === null ? $outcome : WalkOutcome::Failed;
        }

        if ($inspect !== null) {
            $inspect($world);
        }

        return new WalkResult($walk->path, $outcome, $tape->exhausted(), $tape, $failure, $turns, WalkFingerprint::forActiveConfig());
    }

    /**
     * The customer books through the link the front desk texted, exactly as the booking page does: the persona's newest
     * link that is still open, a time Availability offers, and BookAppointment::fromLink() (10 D-09). A walk that names a
     * time doubles as the check that the time is still offered. Returns the failure in plain words, or null once booked.
     */
    private function customerBooks(WalkStep $step, WalkWorldReader $reader): ?string
    {
        $persona = $reader->persona();

        $link = $persona === null ? null : BookingLink::query()
            ->where('contact_id', $persona->id)
            ->whereNull('superseded_at')
            ->whereNull('revoked_at')
            ->whereDoesntHave('appointment')
            ->orderByDesc('id')
            ->first();

        if ($link === null) {
            return 'no booking link had been sent to book with';
        }

        $config = app(ActiveConfig::class);
        $service = $config->service($link->service_slug);

        if ($service === null) {
            return $this->refusalWords(BookingRefused::noSuchService());
        }

        $zone = $config->timezone();
        $now = CarbonImmutable::now();
        $availability = app(Availability::class);

        if ($step->booksAt === null) {
            $start = array_values($availability->openStarts($service, $now))[0][0] ?? null;

            if ($start === null) {
                return 'no time was offered';
            }
        } else {
            $start = CarbonImmutable::parse($step->booksAt, $zone);

            if (! $availability->offers($service, $start, $now)) {
                return BookingSettings::dayText($start, $zone).' at '.BookingSettings::timeText($start, $zone).' was not offered';
            }
        }

        try {
            app(BookAppointment::class)->fromLink($link, $start, $now);
        } catch (BookingRefused $refused) {
            return $this->refusalWords($refused);
        }

        return null;
    }

    private function refusalWords(BookingRefused $refused): string
    {
        return 'the booking was refused: '.rtrim($refused->getMessage(), '.');
    }

    /**
     * The first of a step's checks that does not hold, judged in the order the walk states them, or null when all hold.
     * Later checks are not looked at once one has failed (10 UI-SPEC ruling 2).
     *
     * @param  list<WalkCheck>  $checks
     * @param  array<string, array<string, mixed>>  $services
     */
    private function firstFailure(array $checks, WalkTurnObservation $seen, WalkWorldState $world, array $services, ?int $turn): ?WalkFailure
    {
        foreach ($checks as $check) {
            $words = $check->evaluate($seen, $world, $services);

            if ($words !== null) {
                return new WalkFailure($turn, $words);
            }
        }

        return null;
    }

    /**
     * Time passes: the frozen clock moves by the wait and by nothing else. When the wait carries the walk into a new
     * business-zone day, today.md is redrawn once, as the nightly run would redraw it (10 D-07).
     */
    private function timePasses(WalkStep $step, ScratchWorld $world): void
    {
        $zone = app(ActiveConfig::class)->timezone();
        $dayBefore = $world->now()->setTimezone($zone)->toDateString();

        $world->advance((int) $step->waitMinutes);

        if ($world->now()->setTimezone($zone)->toDateString() !== $dayBefore) {
            app(RecordsWriter::class)->writeOwnerIntelligence();
        }
    }

    /**
     * The customer texts, and the held job for that burst runs at once.
     */
    private function customerSays(WalkStep $step, WalkTape $tape, CaptureDispatchInbound $capture): void
    {
        $tape->startTurn($step->turn);

        app(RecordInbound::class)->handle(new InboundData(MessageChannel::Sms, 'text', (string) $step->says, self::PERSONA_PHONE, null, null, 'walk', "walk-{$step->turn}"));

        foreach ($capture->takeCaptured() as $message) {
            app()->call([new ProcessInbound($message->id, $message->conversation_id), 'handle']);
        }
    }
}
