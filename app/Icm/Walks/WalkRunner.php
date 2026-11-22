<?php

namespace App\Icm\Walks;

use App\Actions\DispatchInbound;
use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Ai\FrontDeskPrompt;
use App\Ai\ReplayFrontDeskAgent;
use App\Ai\RunFrontDeskAgent;
use App\Enums\MessageChannel;
use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Jobs\ProcessInbound;
use App\Records\RecordsWriter;
use App\SendRule\Classifier;
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
            $capture = new CaptureDispatchInbound;

            $world->instance(Classifier::class, new ReplayClassifier($tape));
            $world->bind(RunFrontDeskAgent::class, fn (Application $app): RunFrontDeskAgent => new ReplayFrontDeskAgent($app->make(FrontDeskPrompt::class), $tape));
            $world->instance(DispatchInbound::class, $capture);

            $world->freezeAt(app(WalkClock::class)->startFor($walk, app(BusinessClock::class), app(ActiveConfig::class)->timezone(), $began));

            $outcome = WalkOutcome::Passed;

            foreach ($walk->steps as $step) {
                match ($step->kind) {
                    WalkStepKind::Says => $this->customerSays($step, $tape, $capture),
                    WalkStepKind::Wait => $this->timePasses($step, $world),
                    // A booking is played by plan 10-04.
                    WalkStepKind::Books => null,
                };

                if ($tape->exhausted() !== null) {
                    $outcome = WalkOutcome::NeedsRecording;

                    break;
                }
            }

            if ($inspect !== null) {
                $inspect($world);
            }

            return new WalkResult($walk->path, $outcome, $tape->exhausted(), $tape);
        } finally {
            $world->close();
        }
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
