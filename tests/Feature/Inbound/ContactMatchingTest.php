<?php

use App\Enums\ActivityType;
use App\Enums\MessageChannel;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => activateExampleFolder());

/**
 * A web form post. Phone and email are left out unless the test gives them.
 *
 * @param  array<string, string|null>  $fields
 */
function postLead(object $test, array $fields): TestResponse
{
    return $test->postJson(route('leads.store'), array_merge([
        'name' => 'Dana Whitfield',
        'message' => 'Need AC looked at this week',
        'source' => 'angi',
    ], $fields));
}

/**
 * The summaries of the note entries on the timeline.
 *
 * @return list<string>
 */
function noteSummaries(): array
{
    return Activity::query()
        ->where('type', ActivityType::Note)
        ->orderBy('id')
        ->pluck('summary')
        ->all();
}

it('puts a text and then a form from the same phone on one contact and one conversation', function () {
    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM1',
        'From' => '+12025550123',
        'Body' => 'Hi, my AC stopped',
    ])->assertOk();

    postLead($this, ['phone' => '(202) 555-0123', 'email' => 'dana@example.com'])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::count())->toBe(1)
        ->and(Message::query()->where('channel', MessageChannel::Sms)->count())->toBe(1)
        ->and(Message::query()->where('channel', MessageChannel::Web)->count())->toBe(1);
});

it('puts a form and then a text from the same phone on one contact and one conversation', function () {
    postLead($this, ['phone' => '(202) 555-0123', 'email' => 'dana@example.com'])->assertOk();

    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM1',
        'From' => '+12025550123',
        'Body' => 'Hi, my AC stopped',
    ])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::count())->toBe(1)
        ->and(Message::query()->where('channel', MessageChannel::Sms)->count())->toBe(1)
        ->and(Message::query()->where('channel', MessageChannel::Web)->count())->toBe(1);
});

it('matches a form with only an email to the contact with that email, ignoring case and spaces', function () {
    $contact = Contact::factory()->create(['email' => 'dana@example.com']);

    postLead($this, ['email' => ' Dana@Example.COM '])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::sole()->contact_id)->toBe($contact->id)
        ->and($contact->conversations)->toHaveCount(1);
});

it('creates a contact with a lowercased email when nobody matches', function () {
    postLead($this, ['email' => ' New.Person@Example.COM '])->assertOk();

    expect(Contact::sole()->email)->toBe('new.person@example.com')
        ->and(Contact::sole()->phone_e164)->toBeNull();
});

it('matches by email when the phone is only spaces', function () {
    $contact = Contact::factory()->create(['email' => 'dana@example.com']);

    postLead($this, ['phone' => '   ', 'email' => 'dana@example.com'])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::sole()->contact_id)->toBe($contact->id)
        ->and(noteSummaries())->toBe([]);
});

it('rejects a form with no phone and no email', function (array $fields) {
    postLead($this, $fields)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'Give a phone number or an email address so we can reply.',
        ]);

    expect(Contact::count())->toBe(0)
        ->and(Message::count())->toBe(0);
})->with([
    'both missing' => [[]],
    'both empty' => [['phone' => '', 'email' => '']],
    'both only spaces' => [['phone' => '   ', 'email' => '  ']],
]);

it('answers a plain form post with a 422 too, not a redirect', function () {
    $this->post(route('leads.store'), ['name' => 'Dana Whitfield'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('fills in the phone when the matched contact had none', function () {
    $contact = Contact::factory()->withoutPhone()->create(['email' => 'dana@example.com']);

    postLead($this, ['email' => 'dana@example.com', 'phone' => '+13125550162'])->assertOk();

    expect($contact->fresh()->phone_e164)->toBe('+13125550162')
        ->and(noteSummaries())->toBe([]);
});

it('keeps the stored phone and notes the different one the form gave', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+12025550123', 'email' => 'dana@example.com']);

    postLead($this, ['email' => 'dana@example.com', 'phone' => '+13125550162'])->assertOk();

    expect($contact->fresh()->phone_e164)->toBe('+12025550123')
        ->and(noteSummaries())->toBe(['form gave a different phone: +13125550162']);

    $note = Activity::query()->where('type', ActivityType::Note)->sole();
    expect($note->contact_id)->toBe($contact->id)
        ->and($note->subject->is(Message::sole()))->toBeTrue()
        ->and($note->occurred_at->equalTo(Activity::query()->where('type', ActivityType::FormReceived)->sole()->occurred_at))->toBeTrue();
});

it('lands on the contact the phone matches when the email belongs to someone else', function () {
    $byPhone = Contact::factory()->create(['phone_e164' => '+12025550123', 'email' => 'dana@example.com']);
    $byEmail = Contact::factory()->create(['phone_e164' => '+13125550162', 'email' => 'sam@example.com']);

    postLead($this, ['phone' => '202-555-0123', 'email' => 'sam@example.com'])->assertOk();

    expect(Conversation::sole()->contact_id)->toBe($byPhone->id)
        ->and($byEmail->conversations)->toHaveCount(0)
        ->and($byPhone->fresh()->email)->toBe('dana@example.com');
});

it('fills in an empty email on a contact matched by phone', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+12025550123', 'email' => null]);

    postLead($this, ['phone' => '(202) 555-0123', 'email' => 'Dana@Example.com'])->assertOk();

    expect($contact->fresh()->email)->toBe('dana@example.com')
        ->and(noteSummaries())->toBe([]);
});

it('keeps a different stored email and notes the one the form gave', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+12025550123', 'email' => 'dana@example.com']);

    postLead($this, ['phone' => '(202) 555-0123', 'email' => 'other@example.com'])->assertOk();

    expect($contact->fresh()->email)->toBe('dana@example.com')
        ->and(noteSummaries())->toBe(['form gave a different email: other@example.com']);
});

it('records the lead on the email contact and notes a phone it could not read', function () {
    $contact = Contact::factory()->withoutPhone()->create(['email' => 'dana@example.com']);

    postLead($this, ['phone' => '555-0100', 'email' => 'dana@example.com'])->assertOk();

    expect($contact->fresh()->phone_e164)->toBeNull()
        ->and(Conversation::sole()->contact_id)->toBe($contact->id)
        ->and(noteSummaries())->toBe(['form gave a phone we could not read: 555-0100']);
});

it('rejects an unreadable phone when there is no email to fall back on', function (string $phone) {
    postLead($this, ['phone' => $phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'We could not read that phone number. Check it, or give an email address.',
        ]);

    expect(Contact::count())->toBe(0);
})->with([
    'a 555 area code' => '555-0100',
    'a seven digit local number' => '555-0147',
    'letters' => 'call me',
]);

it('stores every common way of writing one US number as the same E.164 string', function (string $written) {
    postLead($this, ['phone' => '+13125550147', 'email' => 'first@example.com'])->assertOk();
    postLead($this, ['phone' => $written, 'email' => 'first@example.com'])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Contact::sole()->phone_e164)->toBeString()->toBe('+13125550147')
        ->and(Conversation::count())->toBe(1);
})->with([
    'brackets' => '(312) 555-0147',
    'dots' => '312.555.0147',
    'country code with spaces' => '+1 312 555 0147',
    'dashes' => '312-555-0147',
    'an extension' => '312-555-0147 ext 5',
]);

it('keeps the country code of an international number written with a plus', function () {
    postLead($this, ['phone' => '+44 20 7946 0958', 'email' => 'uk@example.com'])->assertOk();

    expect(Contact::sole()->phone_e164)->toBeString()->toBe('+442079460958');
});
