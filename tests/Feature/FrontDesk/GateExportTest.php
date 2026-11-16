<?php

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * The stored gate JSON of a front-desk decision, shaped like the one DecideSendOrDraft writes (version 1).
 *
 * @return array<string, mixed>
 */
function gateExportVerdict(string $customerText, string $agentReply, ?string $reasonCode, string $label = 'intake_question'): array
{
    return [
        'version' => 1,
        'label' => $label,
        'wants_person' => false,
        'inputs' => ['customer_text' => $customerText, 'agent_reply' => $agentReply],
        'checks' => [
            'label' => ['passed' => $reasonCode === null, 'label' => $label],
            'length' => ['passed' => true, 'characters' => mb_strlen($agentReply), 'limit' => 320],
            'code' => ['passed' => true, 'findings' => []],
            'classifier' => ['ran' => true, 'model' => 'fake-jev', 'answers' => ['intent' => 'other'], 'error' => null, 'reading' => ['sendable' => $reasonCode === null]],
        ],
        'thresholds' => ['intent_confidence' => 0.7],
        'reply_model' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'],
        'failed_attempts' => [],
        'reason' => ['code' => $reasonCode, 'plain' => $reasonCode === null ? null : 'Held: asks about price'],
    ];
}

/**
 * The five decisions the export is about, oldest first, plus a holding reply and a life-safety text that must not
 * appear. The first two were made on 2026-10-01 and the rest on 2026-10-10.
 *
 * @return array{own: Message, approved: Message, edited: Message, discarded: Message, replaced: Message}
 */
function gateExportDecisions(): array
{
    $owner = User::factory()->create();
    $early = Carbon::parse('2026-10-01 15:00:00', 'UTC');
    $late = Carbon::parse('2026-10-10 15:00:00', 'UTC');

    $own = Message::factory()->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'label' => 'intake_question',
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportVerdict('My AC is blowing warm air', 'What is the system doing?', null),
        'body' => 'What is the system doing? - Dana (AI assistant)',
        'created_at' => $early,
    ]);

    $approved = Message::factory()->draft()->create([
        'label' => 'price',
        'gate_reason' => 'code:price',
        'gate' => gateExportVerdict('How much for a new unit?', 'Mike will text you about that.', 'code:price', 'price'),
        'body' => 'Mike will text you about that.',
        'status' => MessageStatus::Sent,
        'resolution' => DraftResolution::Approved,
        'resolved_by' => $owner->id,
        'resolved_at' => $early->copy()->addHour(),
        'created_at' => $early,
    ]);

    $edited = Message::factory()->draft()->create([
        'label' => 'promise',
        'gate_reason' => 'code:promise',
        'gate' => gateExportVerdict('Can you come today?', 'We can definitely be there today.', 'code:promise', 'promise'),
        'body' => 'Mike will check and text you back.',
        'original_body' => 'We can definitely be there today.',
        'status' => MessageStatus::Sent,
        'resolution' => DraftResolution::Edited,
        'resolved_by' => $owner->id,
        'resolved_at' => $late->copy()->addHour(),
        'created_at' => $late,
    ]);

    $discarded = Message::factory()->draft()->create([
        'label' => 'complaint',
        'gate_reason' => 'label:complaint',
        'gate' => gateExportVerdict('Your guy broke my fence', 'So sorry to hear that.', 'label:complaint', 'complaint'),
        'body' => 'So sorry to hear that.',
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Discarded,
        'resolved_by' => $owner->id,
        'resolved_at' => $late->copy()->addHour(),
        'created_at' => $late,
    ]);

    $replaced = Message::factory()->withdrawn()->create([
        'label' => 'price',
        'gate' => gateExportVerdict('And the heat pump?', 'Mike will text you about that.', 'code:price', 'price'),
        'body' => 'Mike will text you about that.',
        'created_at' => $late,
    ]);

    foreach ([MessageKind::HoldingReply, MessageKind::LifeSafety] as $kind) {
        Message::factory()->outbound()->create([
            'kind' => $kind,
            'gate_outcome' => GateOutcome::Send,
            'gate' => gateExportVerdict('Smells like gas', 'Call 911.', null),
            'body' => 'Call 911.',
            'created_at' => $late,
        ]);
    }

    return compact('own', 'approved', 'edited', 'discarded', 'replaced');
}

/**
 * Run gate:export and return the lines it printed, decoded.
 *
 * @param  array<string, mixed>  $options
 * @return list<array<string, mixed>>
 */
function gateExportLines(array $options = []): array
{
    $exitCode = Artisan::call('gate:export', $options);
    $lines = array_values(array_filter(explode("\n", Artisan::output()), fn (string $line): bool => $line !== ''));

    expect($exitCode)->toBe(0);

    return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines);
}

it('prints one JSON line per front-desk decision in message id order (D-07)', function () {
    $messages = gateExportDecisions();

    $lines = gateExportLines();

    expect($lines)->toHaveCount(5)
        ->and(array_column($lines, 'message_id'))->toBe([
            $messages['own']->id,
            $messages['approved']->id,
            $messages['edited']->id,
            $messages['discarded']->id,
            $messages['replaced']->id,
        ])
        ->and(array_keys($lines[0]))->toBe([
            'message_id', 'conversation_id', 'decided_at', 'outcome', 'reason_code', 'reason_plain', 'label', 'verdict',
            'final_text', 'original_text', 'resolution', 'resolved_at', 'resolved_by', 'owner_edited', 'status',
        ]);
});

it('exports final text only for a text that reached the customer (D-16)', function (MessageStatus $status, ?string $finalText) {
    Message::factory()->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'status' => $status,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportVerdict('My AC is blowing warm air', 'What is the system doing?', null),
        'body' => 'What is the system doing?',
    ]);

    $line = gateExportLines()[0];

    expect($line['final_text'])->toBe($finalText)
        ->and($line['status'])->toBe($status->value);
})->with([
    'sent' => [MessageStatus::Sent, 'What is the system doing?'],
    'delivered' => [MessageStatus::Delivered, 'What is the system doing?'],
    'queued' => [MessageStatus::Queued, null],
    'failed' => [MessageStatus::Failed, null],
]);

it('carries each line\'s status (D-16)', function () {
    gateExportDecisions();

    expect(array_column(gateExportLines(), 'status'))->toBe(['sent', 'sent', 'sent', 'withdrawn', 'withdrawn']);
});

it('exports a reply that went out on its own with no resolution', function () {
    $own = gateExportDecisions()['own'];

    $line = gateExportLines()[0];

    expect($line['conversation_id'])->toBe($own->conversation_id)
        ->and($line['decided_at'])->toBe('2026-10-01T15:00:00+00:00')
        ->and($line['outcome'])->toBe('send')
        ->and($line['reason_code'])->toBeNull()
        ->and($line['label'])->toBe('intake_question')
        ->and($line['resolution'])->toBeNull()
        ->and($line['resolved_at'])->toBeNull()
        ->and($line['resolved_by'])->toBeNull()
        ->and($line['owner_edited'])->toBeFalse()
        ->and($line['final_text'])->toBe('What is the system doing? - Dana (AI assistant)')
        ->and($line['verdict']['thresholds'])->toBe(['intent_confidence' => 0.7]);
});

it('exports an approved draft as a hold the owner let through unchanged', function () {
    $messages = gateExportDecisions();

    $line = gateExportLines()[1];

    expect($line['outcome'])->toBe('hold')
        ->and($line['reason_code'])->toBe('code:price')
        ->and($line['reason_plain'])->toBe('Held: asks about price')
        ->and($line['label'])->toBe('price')
        ->and($line['resolution'])->toBe('approved')
        ->and($line['resolved_by'])->toBe($messages['approved']->resolved_by)
        ->and($line['resolved_at'])->toBe('2026-10-01T16:00:00+00:00')
        ->and($line['owner_edited'])->toBeFalse()
        ->and($line['final_text'])->toBe('Mike will text you about that.')
        ->and($line['original_text'])->toBe('Mike will text you about that.')
        ->and($line['verdict']['thresholds'])->toBe(['intent_confidence' => 0.7]);
});

it('exports an edited draft with the agent words and the words that were sent', function () {
    gateExportDecisions();

    $line = gateExportLines()[2];

    expect($line['resolution'])->toBe('edited')
        ->and($line['owner_edited'])->toBeTrue()
        ->and($line['original_text'])->toBe('We can definitely be there today.')
        ->and($line['final_text'])->toBe('Mike will check and text you back.')
        ->and($line['verdict']['inputs']['customer_text'])->toBe('Can you come today?');
});

it('exports a discarded and a replaced draft with no final text because nothing was sent', function () {
    gateExportDecisions();

    [,,, $discarded, $replaced] = gateExportLines();

    expect($discarded['resolution'])->toBe('discarded')
        ->and($discarded['final_text'])->toBeNull()
        ->and($discarded['original_text'])->toBe('So sorry to hear that.')
        ->and($replaced['resolution'])->toBe('replaced')
        ->and($replaced['resolved_by'])->toBeNull()
        ->and($replaced['final_text'])->toBeNull();
});

it('does not export holding, handoff or life-safety texts', function () {
    gateExportDecisions();

    $texts = array_column(gateExportLines(), 'original_text');

    expect($texts)->not->toContain('Call 911.');
});

it('limits the rows to decisions on or after --since', function () {
    $messages = gateExportDecisions();

    $lines = gateExportLines(['--since' => '2026-10-05']);

    expect(array_column($lines, 'message_id'))->toBe([$messages['edited']->id, $messages['discarded']->id, $messages['replaced']->id]);
});

it('refuses a --since that is not a date', function () {
    gateExportDecisions();

    $exitCode = Artisan::call('gate:export', ['--since' => 'last week']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('YYYY-MM-DD')
        ->and($output)->not->toContain('message_id');
});

it('leaves out every customer and reply text with --without-text but keeps the lengths', function () {
    gateExportDecisions();

    $lines = gateExportLines(['--without-text' => true]);

    expect($lines)->toHaveCount(5);

    foreach ($lines as $line) {
        expect($line)->not->toHaveKeys(['final_text', 'original_text'])
            ->and($line['verdict']['inputs'])->not->toHaveKeys(['customer_text', 'agent_reply'])
            ->and($line['verdict']['inputs'])->toHaveKeys(['customer_text_length', 'agent_reply_length'])
            ->and($line)->toHaveKeys(['final_text_length', 'original_text_length', 'verdict', 'reason_plain']);
    }

    expect($lines[2]['final_text_length'])->toBe(mb_strlen('Mike will check and text you back.'))
        ->and($lines[2]['original_text_length'])->toBe(mb_strlen('We can definitely be there today.'))
        ->and($lines[2]['verdict']['inputs']['customer_text_length'])->toBe(mb_strlen('Can you come today?'))
        ->and($lines[3]['final_text_length'])->toBeNull();
});

it('carries no customer or reply words anywhere in a --without-text export, even in a quoted finding or an error', function () {
    $words = 'Your guy broke my fence at 12 Elm Street';
    $gate = gateExportVerdict($words, 'Sorry about the fence at 12 Elm Street.', 'code:never_say', 'complaint');
    $gate['checks']['code'] = ['passed' => false, 'findings' => [['rule' => 'never_say', 'match' => 'fence at 12 Elm Street']]];
    $gate['checks']['classifier']['error'] = 'HTTP 500 while reading: '.$words;
    $gate['failed_attempts'] = [['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'error' => 'Timeout for '.$words]];

    Message::factory()->draft()->create(['gate' => $gate, 'body' => 'Sorry about the fence at 12 Elm Street.']);

    Artisan::call('gate:export', ['--without-text' => true]);
    $output = Artisan::output();

    expect($output)->not->toContain('Elm Street')
        ->and($output)->not->toContain('fence')
        ->and($output)->toContain('code:never_say');
});

it('prints nothing and succeeds when there are no decisions', function () {
    $exitCode = Artisan::call('gate:export');

    expect($exitCode)->toBe(0)
        ->and(trim(Artisan::output()))->toBe('');
});

it('prints unicode, slashes and angle brackets as written, not escaped or read as console formatting', function () {
    Message::factory()->draft()->create([
        'gate' => gateExportVerdict('Café at 3/4 Elm <error>now</error>', 'Sí, 3/4 está bien.', 'code:price', 'price'),
        'body' => 'Sí, 3/4 está bien.',
    ]);

    Artisan::call('gate:export');

    expect(Artisan::output())
        ->toContain('Sí, 3/4 está bien.')
        ->toContain('<error>now</error>')
        ->not->toContain('\\u00ed')
        ->not->toContain('3\/4');
});
