<?php

use App\Actions\OwnerAlertText;
use App\Actions\RaiseOwnerAlert;
use App\Actions\RecordIntakeAnswers;
use App\Actions\ReportFrontDeskFailure;
use App\Actions\SendOwnerAlert;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * A failed front-desk run is reported once per claim token, whichever path gets there first (06 D-11, ALRT-02).
 *
 * The run's own catch, the queue's failed() and RunFrontDesk's own reports all end in ReportFrontDeskFailure, and they
 * can race. The report takes one conditional update on the token's report row, so these tests call the paths in both
 * orders on the same job and on a copy rebuilt from its serialized form, the shape the queue gives failed(). The two
 * databases are covered by running this file on SQLite here and on Postgres in CI (D-21), not by a two-connection
 * test. The catch-first order is the re-pointed case in FrontDeskFailureTest.
 */

/**
 * Dana's number on file for the owner, and one customer text a run has claimed under a token.
 *
 * @return array{Conversation, Message} the conversation and the claimed text
 */
function failureOnceClaimedText(string $token = 'run-token'): array
{
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $text = Message::factory()->for($conversation)->claimedBy($token)->create();

    return [$conversation, $text];
}

/**
 * The failed() hook, run on a copy of the job rebuilt from its serialized form, the way the queue does after a timeout.
 */
function failureOnceQueueFails(Message $text, Conversation $conversation, string $token = 'run-token'): void
{
    unserialize(serialize(new ProcessInbound($text->id, $conversation->id, $token)))->failed(new RuntimeException('timed out'));
}

/**
 * The notes "Front desk could not answer" wrote, as their summaries.
 *
 * @return list<string>
 */
function failureOnceNotes(): array
{
    return Contact::query()->get()
        ->flatMap(fn (Contact $contact): Collection => $contact->activities()->where('type', ActivityType::Note)->orderBy('id')->pluck('summary'))
        ->values()
        ->all();
}

/**
 * How many front_desk_failed alerts exist.
 */
function failureOnceAlertCount(): int
{
    return OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count();
}

/**
 * Make the next front_desk_failed alert throw once, then behave as RaiseOwnerAlert does. Shared as a singleton so the
 * path that retries after the failure finds the same object, which has already thrown.
 */
function failureOnceAlertThatBreaksOnce(): void
{
    app()->singleton(RaiseOwnerAlert::class, fn () => new class(app(ActiveConfig::class), app(BusinessClock::class), app(OwnerAlertText::class), app(SendOwnerAlert::class)) extends RaiseOwnerAlert
    {
        private bool $broke = false;

        public function handle(Conversation $conversation, AlertReason $reason, ?Message $subject = null, ?string $why = null): Collection
        {
            if ($reason === AlertReason::FrontDeskFailed && ! $this->broke) {
                $this->broke = true;

                throw new RuntimeException('the alert could not be stored');
            }

            return parent::handle($conversation, $reason, $subject, $why);
        }
    });
}

it('reports once when failed() reports first and the catch then reports the same burst (D-11)', function () {
    [$conversation, $text] = failureOnceClaimedText();

    failureOnceQueueFails($text, $conversation);
    app(ReportFrontDeskFailure::class)->handle($conversation, ReportFrontDeskFailure::subjectOf(collect([$text])), 'it stopped with an error', 'Front desk stopped with an error');

    expect(failureOnceAlertCount())->toBe(1)
        ->and(failureOnceNotes())->toBe(['Front desk could not answer: it stopped before finishing'])
        ->and($text->fresh()->failure_reported_at)->not->toBeNull();
});

it('reports once when RunFrontDesk reports that no agent answers texts and failed() then runs on the same job (D-11)', function () {
    User::factory()->withPhone('+12025550199')->create();
    File::delete(icmPath('agents/front-desk.md'));
    frontDeskCustomerTexts();

    runQueuedFrontDeskJobs();
    unserialize(serialize(Queue::pushed(ProcessInbound::class)->sole()))->failed(new RuntimeException('timed out'));

    $noted = array_values(array_filter(failureOnceNotes(), fn (string $summary): bool => str_starts_with($summary, 'Front desk could not answer')));

    expect(failureOnceAlertCount())->toBe(1)
        ->and($noted)->toBe(['Front desk could not answer: no agent file answers texts'])
        ->and(Message::query()->where('direction', 'in')->sole()->failure_reported_at)->not->toBeNull();
});

it('reports once for a burst whose newest row is a web form, whichever row the first caller passes (D-11)', function () {
    [$conversation, $text] = failureOnceClaimedText('mixed-run');
    $form = Message::factory()->web()->for($conversation)->claimedBy('mixed-run')->create();

    app(ReportFrontDeskFailure::class)->handle($conversation, $form, 'no agent file answers texts', 'No front desk agent is set up for texts');
    failureOnceQueueFails($form, $conversation, 'mixed-run');

    expect(failureOnceAlertCount())->toBe(1)
        ->and(failureOnceNotes())->toHaveCount(1)
        ->and($text->fresh()->failure_reported_at)->not->toBeNull()
        ->and($form->fresh()->failure_reported_at)->toBeNull();
});

it('gives the claim back when the report cannot be written, so the other path still tells the owner (02.1 IN-11)', function () {
    [$conversation, $text] = failureOnceClaimedText();
    failureOnceAlertThatBreaksOnce();

    expect(fn () => app(ReportFrontDeskFailure::class)->handle($conversation, $text, 'it stopped with an error', 'Front desk stopped with an error'))
        ->toThrow(RuntimeException::class, 'the alert could not be stored');

    expect(failureOnceAlertCount())->toBe(0)
        ->and($text->fresh()->failure_reported_at)->toBeNull();

    failureOnceQueueFails($text, $conversation);

    expect(failureOnceAlertCount())->toBe(1)
        ->and($text->fresh()->failure_reported_at)->not->toBeNull();
});

it('keeps the words fixed, and the exception message out of the note and the alert, on a report that broke and was retried', function () {
    [$conversation, $text] = failureOnceClaimedText();
    failureOnceAlertThatBreaksOnce();

    expect(fn () => app(ReportFrontDeskFailure::class)->handle($conversation, $text, 'it stopped with an error', 'Front desk stopped with an error'))
        ->toThrow(RuntimeException::class);

    failureOnceQueueFails($text, $conversation);

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($alert->body)->toContain('Front desk stopped before answering')
        ->and($alert->body)->not->toContain('could not be stored')
        ->and(failureOnceNotes())->each->not->toContain('could not be stored');
});

it('reports a text that carries no claim token exactly as before, once, by the alert that already exists', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $text = Message::factory()->for($conversation)->create(['handled_at' => now()]);

    app(ReportFrontDeskFailure::class)->handle($conversation, $text, 'it stopped with an error', 'Front desk stopped with an error');
    app(ReportFrontDeskFailure::class)->handle($conversation, $text, 'it stopped before finishing', 'Front desk stopped before answering');

    expect(failureOnceAlertCount())->toBe(1)
        ->and(failureOnceNotes())->toBe(['Front desk could not answer: it stopped with an error'])
        ->and($text->fresh()->failure_reported_at)->toBeNull();
});

/**
 * Make every life-safety alert throw, so the one step a web-form-only burst runs after its claim breaks the run.
 */
function failureOnceLifeSafetyAlertThatBreaks(): void
{
    app()->singleton(RaiseOwnerAlert::class, fn () => new class(app(ActiveConfig::class), app(BusinessClock::class), app(OwnerAlertText::class), app(SendOwnerAlert::class)) extends RaiseOwnerAlert
    {
        public function handle(Conversation $conversation, AlertReason $reason, ?Message $subject = null, ?string $why = null): Collection
        {
            if ($reason === AlertReason::LifeSafety) {
                throw new RuntimeException('the life-safety alert could not be stored');
            }

            return parent::handle($conversation, $reason, $subject, $why);
        }
    });
}

it('reports a failed web-form-only burst on the catch path, tied to the form, and texts the lead nothing (D-20)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $form = Message::factory()->web()->for($conversation)->create(['body' => 'I smell gas in the basement']);
    failureOnceLifeSafetyAlertThatBreaks();

    $job = new ProcessInbound($form->id, $conversation->id, 'form-run');
    $returned = null;

    expect(function () use ($job, &$returned) {
        $returned = app()->call([$job, 'handle']);
    })->not->toThrow(Throwable::class);

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($returned)->toBeFalse()
        ->and($alert->message_id)->toBe($form->id)
        ->and($alert->body)->toContain('Front desk stopped with an error')
        ->and($alert->body)->not->toContain('could not be stored')
        ->and(failureOnceNotes())->toBe(['Front desk could not answer: it stopped with an error'])
        ->and(textsSentToCustomers())->toBe([]);
});

it('adds nothing when failed() runs after the catch reported a form-only burst (D-11, D-20)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $form = Message::factory()->web()->for($conversation)->create(['body' => 'I smell gas in the basement']);
    failureOnceLifeSafetyAlertThatBreaks();
    $job = new ProcessInbound($form->id, $conversation->id, 'form-run');

    app()->call([$job, 'handle']);
    unserialize(serialize($job))->failed(new RuntimeException('timed out'));

    expect(failureOnceAlertCount())->toBe(1)
        ->and(failureOnceNotes())->toBe(['Front desk could not answer: it stopped with an error']);
});

it('reports her text, not the newer web form, when a run that claimed both throws (D-20, research A6)', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    $conversation = Conversation::sole();
    $text = Message::query()->where('direction', 'in')->sole();
    $form = Message::factory()->web()->for($conversation)->create(['body' => 'Need AC looked at this week']);
    $this->mock(RecordIntakeAnswers::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('database went away')));

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($form->fresh()->handled_at)->not->toBeNull()
        ->and($form->id)->toBeGreaterThan($text->id)
        ->and($alert->message_id)->toBe($text->id)
        ->and(failureOnceNotes())->toBe(['Front desk could not answer: it stopped with an error']);
});

it('still returns true, and reports her text, when a text-only run throws', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    $this->mock(RecordIntakeAnswers::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('database went away')));

    $job = Queue::pushed(ProcessInbound::class)->sole();
    $returned = app()->call([$job, 'handle']);

    expect($returned)->toBeTrue()
        ->and(failureOnceAlertCount())->toBe(1);
});
