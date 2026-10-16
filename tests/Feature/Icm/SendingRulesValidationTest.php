<?php

use App\Enums\AlertReason;
use App\Enums\ReplyLabel;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

const OWNER_ALERT_BLOCK = "owner_alert:\n  channels: [text]\n  right_away: [complaint, asked_for_person, life_safety, paused_message, opted_out_message, front_desk_failed, reply_cap, check_in]\n  after_hours: next_opening\n";

/*
| Each row: file to change, text to find, text to put in its place, and the exact line the owner must see.
*/
dataset('broken send rules', [
    'a price in send_on_own' => ['rules/sending.md', 'send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [intake_question, price]', 'rules/sending.md: send_on_own cannot include price: anything about a price always waits for you'],
    'a complaint in send_on_own' => ['rules/sending.md', 'send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [intake_question, complaint]', 'rules/sending.md: send_on_own cannot include complaint: a complaint always waits for you'],
    'a promise in send_on_own' => ['rules/sending.md', 'send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [promise]', 'rules/sending.md: send_on_own cannot include promise: a promise always waits for you'],
    'a discount in send_on_own' => ['rules/sending.md', 'send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [discount]', 'rules/sending.md: send_on_own cannot include discount: a discount always waits for you'],
    'an action nobody knows in send_on_own' => ['rules/sending.md', 'send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [booking]', 'rules/sending.md: send_on_own item 1 must be one of intake_question, scheduling, faq_answer (you wrote booking)'],
    'an action nobody knows in draft_for_owner' => ['rules/sending.md', 'draft_for_owner: [price, promise, discount, complaint, other]', 'draft_for_owner: [prices]', 'rules/sending.md: draft_for_owner item 1 must be one of price, promise, discount, complaint, other (you wrote prices)'],
    'scheduling in both lists' => ['rules/sending.md', 'draft_for_owner: [price, promise, discount, complaint, other]', 'draft_for_owner: [price, promise, discount, complaint, other, scheduling]', 'rules/sending.md: scheduling is in both send_on_own and draft_for_owner; put it in one list'],
    'email as an alert channel' => ['rules/sending.md', 'channels: [text]', 'channels: [email]', 'rules/sending.md: owner_alert channels item 1 must be one of text (you wrote email)'],
    'right_away without life_safety' => ['rules/sending.md', 'life_safety, ', '', 'rules/sending.md: owner_alert right_away must include life_safety: a safety message always reaches you at once'],
    'an alert event nobody knows' => ['rules/sending.md', 'right_away: [complaint,', 'right_away: [soon, complaint,', 'rules/sending.md: owner_alert right_away item 1 must be one of draft_waiting, complaint, asked_for_person, life_safety, paused_message, opted_out_message, front_desk_failed, reply_cap, check_in, safety_check_down, safety_check_missing (you wrote soon)'],
    'an after-hours rule nobody knows' => ['rules/sending.md', 'after_hours: next_opening', 'after_hours: later', 'rules/sending.md: owner_alert after_hours must be one of next_opening, right_away (you wrote later)'],
    'owner_alert written as a sentence' => ['rules/sending.md', OWNER_ALERT_BLOCK, "owner_alert: Text the owner when a draft is waiting or a customer asks for a person\n", 'rules/sending.md: owner_alert must list channels, right_away and after_hours (you wrote Text the owner when a draft is waiting or a customer asks for a person)'],
    'a sign-off that uses a never_say phrase' => ['voice/how-we-talk.md', 'sign_off: Example Heating & Air', 'sign_off: No problem, Example Heating & Air', 'voice/how-we-talk.md: sign_off uses a phrase the voice file says never to use (No problem)'],
    'an AI disclosure that names a price' => ['voice/how-we-talk.md', 'can step in anytime."', 'can step in anytime. Repairs from $49."', 'voice/how-we-talk.md: ai_disclosure mentions a price ($49); a fixed text goes out without you, so it cannot mention a price, offer a discount or make a promise'],
]);

it('rejects send rules and voice texts that would loosen the floor and keeps nothing live', function (string $file, string $search, string $replace, string $line) {
    expect(File::get(icmPath($file)))->toContain($search);

    breakFile($file, fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toContain($line)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('broken send rules');

it('rejects a price in send_on_own with that one line and nothing else', function () {
    breakFile('rules/sending.md', fn (string $text) => str_replace('send_on_own: [intake_question, scheduling, faq_answer]', 'send_on_own: [intake_question, price]', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rules/sending.md: send_on_own cannot include price: anything about a price always waits for you']);
});

it('puts the structured send rules live with the example folder', function () {
    activateExampleFolder();

    $rules = app(ActiveConfig::class)->sendingRules();

    expect($rules['send_on_own'])->toBe(['intake_question', 'scheduling', 'faq_answer'])
        ->and($rules['draft_for_owner'])->toBe(['price', 'promise', 'discount', 'complaint', 'other'])
        ->and($rules['owner_alert']['channels'])->toBe(['text'])
        ->and($rules['owner_alert']['right_away'])->toContain('life_safety')
        ->and($rules['owner_alert']['after_hours'])->toBe('next_opening')
        ->and($rules['owner_alert'])->not->toHaveKey('phone');
});

it('lets the owner alert wait for the next opening or go right away after hours', function (string $afterHours) {
    breakFile('rules/sending.md', fn (string $text) => str_replace('after_hours: next_opening', "after_hours: {$afterHours}", $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->sendingRules()['owner_alert']['after_hours'])->toBe($afterHours);
})->with(['next_opening', 'right_away']);

it('keeps price, promise, discount and complaint off the send-on-its-own list for good', function () {
    expect(ReplyLabel::neverOnItsOwn())->toBe([ReplyLabel::Price, ReplyLabel::Promise, ReplyLabel::Discount, ReplyLabel::Complaint])
        ->and(ReplyLabel::sendOnOwnAllowed())->toBe([ReplyLabel::IntakeQuestion, ReplyLabel::Scheduling, ReplyLabel::FaqAnswer])
        ->and(ReplyLabel::values())->toBe(['intake_question', 'scheduling', 'faq_answer', 'price', 'promise', 'discount', 'complaint', 'other'])
        ->and(array_filter(ReplyLabel::sendOnOwnAllowed(), fn (ReplyLabel $label) => in_array($label, ReplyLabel::neverOnItsOwn(), true)))->toBe([]);
});

it('names every owner alert event in plain words', function () {
    expect(AlertReason::values())->toBe(['draft_waiting', 'complaint', 'asked_for_person', 'life_safety', 'paused_message', 'opted_out_message', 'front_desk_failed', 'reply_cap', 'check_in', 'safety_check_down', 'safety_check_missing'])
        ->and(AlertReason::FrontDeskFailed->plainWords())->toBe('Front desk could not answer')
        ->and(AlertReason::PausedMessage->plainWords())->toBe("New message while you're handling it")
        ->and(collect(AlertReason::cases())->map->plainWords()->unique())->toHaveCount(count(AlertReason::cases()));
});
