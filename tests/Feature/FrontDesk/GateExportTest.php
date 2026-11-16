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
 * The stored gate JSON of a fixed reply (no model words), shaped like the one SendCombinedReply writes: no inputs, only
 * the reading of the customer's text. The error is the place a provider can echo the text it was sent.
 *
 * @return array<string, mixed>
 */
function gateExportFixedVerdict(?string $readingError = null): array
{
    return [
        'version' => 1,
        'fixed' => true,
        'reason' => ['code' => 'fixed_reply', 'plain' => null],
        'customer_reading' => ['ran' => true, 'failed' => false, 'missing' => false, 'error' => $readingError, 'model' => 'fake-jev', 'answers' => null, 'reading' => null],
    ];
}

/**
 * The code-assembled texts the export also holds, plus four kinds it leaves out, oldest first: an answering code_answer,
 * the draft it answered, one sent text of each other code-assembled kind, then a holding, handoff, life-safety and booking
 * text that must not appear. A separate helper from gateExportDecisions(), which keeps its five lines.
 *
 * @return array{answer: Message, answered: Message, others: array<string, Message>}
 */
function gateExportCodeAssembled(): array
{
    $answer = Message::factory()->outbound()->create([
        'kind' => MessageKind::CodeAnswer,
        'gate_outcome' => GateOutcome::Send,
        'gate_reason' => 'fixed_reply',
        'gate' => gateExportFixedVerdict(),
        'meta' => ['parts' => ['code_sentences']],
        'body' => 'A tune-up is $89.',
    ]);

    $answered = Message::factory()->draft()->create([
        'label' => 'price',
        'gate_reason' => 'code:price',
        'gate' => gateExportVerdict('How much is a tune-up?', 'Mike will text you about that.', 'code:price', 'price'),
        'body' => 'Mike will text you about that.',
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Answered,
        'resolved_by' => null,
        'resolved_at' => now(),
        'meta' => ['answered_by' => $answer->id],
    ]);

    $others = [];

    foreach (MessageKind::assembledByCode() as $kind) {
        if ($kind === MessageKind::CodeAnswer) {
            continue;
        }

        $others[$kind->value] = Message::factory()->outbound()->create([
            'kind' => $kind,
            'gate_outcome' => GateOutcome::Send,
            'gate_reason' => 'fixed_reply',
            'gate' => gateExportFixedVerdict(),
            'meta' => ['parts' => ['code_sentences']],
            'body' => 'A fixed line for '.$kind->value.'.',
        ]);
    }

    foreach (['holding_reply', 'handoff_reply', 'life_safety', 'booking_text'] as $kind) {
        Message::factory()->outbound()->create([
            'kind' => $kind,
            'gate_outcome' => GateOutcome::Send,
            'gate' => gateExportFixedVerdict(),
            'body' => 'Left out: '.$kind,
        ]);
    }

    return compact('answer', 'answered', 'others');
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
            'kind', 'has_model_words', 'answered_by_message_id',
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
        ->and($line['original_text'])->toBe('What is the system doing?')
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

it('gives original_text one meaning when body, original_body and agent_reply all differ (D-17)', function () {
    Message::factory()->draft()->create([
        'label' => 'promise',
        'gate_reason' => 'code:promise',
        'gate' => gateExportVerdict('Can you come today?', 'We can be there today.', 'code:promise', 'promise'),
        'body' => 'Mike will check and text you back.',
        'original_body' => 'We can be there today. - Dana (AI assistant)',
        'status' => MessageStatus::Sent,
        'resolution' => DraftResolution::Edited,
        'resolved_by' => User::factory()->create()->id,
        'resolved_at' => now(),
    ]);

    $line = gateExportLines()[0];

    expect($line['original_text'])->toBe('We can be there today.')
        ->and($line['final_text'])->toBe('Mike will check and text you back.');
});

it('has no original text when the model wrote nothing (D-17)', function () {
    Message::factory()->draft()->create([
        'gate' => gateExportVerdict('Can you come today?', '', 'code:promise', 'promise'),
        'body' => 'Mike will text you back.',
    ]);

    $noInputs = gateExportVerdict('Can you come today?', 'unused', 'code:promise', 'promise');
    unset($noInputs['inputs']);

    Message::factory()->draft()->create(['gate' => $noInputs, 'body' => 'Mike will text you back too.']);

    $lines = gateExportLines();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['original_text'])->toBeNull()
        ->and($lines[1]['original_text'])->toBeNull();
});

it('exports each code-assembled kind with its kind and whether model words were in (D-18)', function (MessageKind $kind) {
    gateExportCodeAssembled();

    $matching = array_values(array_filter(gateExportLines(), fn (array $line): bool => $line['kind'] === $kind->value));

    expect($matching)->toHaveCount(1)
        ->and($matching[0])->toHaveKeys(['kind', 'has_model_words', 'status']);
})->with(fn (): array => array_map(fn (MessageKind $kind): array => [$kind], MessageKind::assembledByCode()));

it('leaves out holding, handoff, life-safety and booking texts (D-18)', function () {
    gateExportCodeAssembled();

    $kinds = array_column(gateExportLines(), 'kind');

    expect($kinds)->not->toContain('holding_reply')
        ->and($kinds)->not->toContain('handoff_reply')
        ->and($kinds)->not->toContain('life_safety')
        ->and($kinds)->not->toContain('booking_text');
});

it('reads model words the way the sender does (D-18)', function (MessageKind $kind, ?array $parts, bool $expected) {
    Message::factory()->outbound()->create([
        'kind' => $kind,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportFixedVerdict(),
        'meta' => $parts === null ? null : ['parts' => $parts],
        'body' => 'Some text.',
    ]);

    expect(gateExportLines()[0]['has_model_words'])->toBe($expected);
})->with([
    'agent_reply, no parts' => [MessageKind::AgentReply, null, true],
    'code_answer, code sentences only' => [MessageKind::CodeAnswer, ['code_sentences'], false],
    'code_answer, with model words' => [MessageKind::CodeAnswer, ['code_sentences', 'model_words'], true],
    'code_answer, no parts' => [MessageKind::CodeAnswer, null, true],
    'partial_holding, no parts' => [MessageKind::PartialHolding, null, true],
    'check_in_reply, no parts' => [MessageKind::CheckInReply, null, true],
    'added_info_reply, no parts' => [MessageKind::AddedInfoReply, null, true],
    'complaint_holding, no parts' => [MessageKind::ComplaintHolding, null, false],
    'discount_holding, no parts' => [MessageKind::DiscountHolding, null, false],
]);

it('keeps a fixed reply\'s original text empty and a combined one\'s as the model words (D-18)', function () {
    $fixed = Message::factory()->outbound()->create([
        'kind' => MessageKind::CodeAnswer,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportFixedVerdict(),
        'meta' => ['parts' => ['code_sentences']],
        'body' => 'A tune-up is $89.',
    ]);

    $combined = Message::factory()->outbound()->create([
        'kind' => MessageKind::CodeAnswer,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportVerdict('How much, and can you come?', 'The model words.', null),
        'meta' => ['parts' => ['code_sentences', 'model_words']],
        'body' => 'A tune-up is $89. The model words.',
    ]);

    $lines = array_column(gateExportLines(), null, 'message_id');

    expect($lines[$fixed->id]['original_text'])->toBeNull()
        ->and($lines[$combined->id]['original_text'])->toBe('The model words.')
        ->and($lines[$combined->id]['final_text'])->toBe('A tune-up is $89. The model words.');
});

it('names the text that answered a draft and keeps both lines (D-19)', function () {
    $made = gateExportCodeAssembled();

    $lines = array_column(gateExportLines(), null, 'message_id');

    expect($lines[$made['answered']->id]['resolution'])->toBe('answered')
        ->and($lines[$made['answered']->id]['final_text'])->toBeNull()
        ->and($lines[$made['answered']->id]['status'])->toBe('withdrawn')
        ->and($lines[$made['answered']->id]['answered_by_message_id'])->toBe($made['answer']->id)
        ->and($lines[$made['answer']->id]['kind'])->toBe('code_answer')
        ->and($lines[$made['answer']->id]['answered_by_message_id'])->toBeNull()
        ->and($lines[$made['answer']->id]['final_text'])->toBe('A tune-up is $89.');

    foreach ($lines as $id => $line) {
        if ($id !== $made['answered']->id) {
            expect($line['answered_by_message_id'])->toBeNull();
        }
    }
});

it('prints lines in message id order whatever their kind (GATE-05)', function () {
    $first = Message::factory()->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportVerdict('One?', 'One.', null),
    ]);
    $middle = Message::factory()->outbound()->create([
        'kind' => MessageKind::CodeAnswer,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportFixedVerdict(),
        'meta' => ['parts' => ['code_sentences']],
    ]);
    $last = Message::factory()->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportVerdict('Two?', 'Two.', null),
    ]);

    expect(array_column(gateExportLines(), 'message_id'))->toBe([$first->id, $middle->id, $last->id]);
});

it('keeps every word out with --without-text, new kinds included (D-20)', function () {
    $words = 'Your guy broke my fence at 12 Elm Street';

    $answer = Message::factory()->outbound()->create([
        'kind' => MessageKind::CodeAnswer,
        'gate_outcome' => GateOutcome::Send,
        'gate' => [...gateExportVerdict($words, 'Sorry about the fence at 12 Elm Street.', null), 'customer_reading' => gateExportFixedVerdict('HTTP 500 while reading: '.$words)['customer_reading']],
        'meta' => ['parts' => ['code_sentences', 'model_words']],
        'body' => 'Sorry about the fence at 12 Elm Street.',
    ]);

    $fixed = Message::factory()->outbound()->create([
        'kind' => MessageKind::CheckInReply,
        'gate_outcome' => GateOutcome::Send,
        'gate' => gateExportFixedVerdict('Timeout for '.$words),
        'meta' => ['parts' => ['code_sentences']],
        'body' => 'Still on it, about 12 Elm Street.',
    ]);

    $answeredGate = gateExportVerdict($words, 'Sorry about the fence at 12 Elm Street.', 'code:never_say', 'complaint');
    $answeredGate['customer_reading'] = gateExportFixedVerdict('Timeout for '.$words)['customer_reading'];

    $answered = Message::factory()->draft()->create([
        'gate' => $answeredGate,
        'body' => 'Sorry about the fence at 12 Elm Street.',
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Answered,
        'meta' => ['answered_by' => $answer->id],
    ]);

    Artisan::call('gate:export', ['--without-text' => true]);
    $output = Artisan::output();
    $lines = array_column(gateExportLines(['--without-text' => true]), null, 'message_id');

    expect($output)->not->toContain('Elm Street')
        ->and($output)->not->toContain('fence')
        ->and($lines[$fixed->id]['original_text_length'])->toBeNull()
        ->and($lines[$fixed->id]['verdict'])->not->toHaveKey('inputs')
        ->and($lines[$answer->id]['original_text_length'])->toBe(mb_strlen('Sorry about the fence at 12 Elm Street.'))
        ->and($lines[$answer->id]['verdict']['customer_reading'])->not->toHaveKey('error')
        ->and($lines[$answer->id]['verdict']['customer_reading']['error_length'])->toBe(mb_strlen('HTTP 500 while reading: '.$words))
        ->and($lines[$answered->id]['answered_by_message_id'])->toBe($answer->id)
        ->and($lines[$answered->id]['status'])->toBe('withdrawn');

    foreach ($lines as $line) {
        expect($line)->toHaveKeys(['status', 'kind', 'has_model_words', 'answered_by_message_id']);
    }
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
