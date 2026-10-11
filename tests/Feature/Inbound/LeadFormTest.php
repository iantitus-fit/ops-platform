<?php

use App\Enums\ActivityType;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * The fields a website lead form posts to POST /leads.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function leadFormFields(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dana Whitfield',
        'phone' => '(202) 555-0123',
        'email' => 'dana@example.com',
        'message' => 'Need AC looked at this week',
        'source' => 'angi',
    ], $overrides);
}

it('lands an Angi form from someone who already texted on the same contact with Angi\'s cost', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM'.str_repeat('b', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'Hi, my AC stopped',
        'NumMedia' => '0',
    ])->assertOk();

    $this->postJson(route('leads.store'), leadFormFields())
        ->assertOk()
        ->assertExactJson(['received' => true]);

    $contact = Contact::sole();
    $conversation = Conversation::sole();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->messages)->toHaveCount(2);

    $message = $conversation->messages->last();
    expect($message->channel)->toBe(MessageChannel::Web)
        ->and($message->direction)->toBe(MessageDirection::In)
        ->and($message->author)->toBe(MessageAuthor::Customer)
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($message->body)->toBe('Need AC looked at this week')
        ->and($message->meta['form']['source'])->toBe('angi');

    $activity = Activity::query()->where('type', ActivityType::FormReceived)->sole();
    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->lead_source_key)->toBe('angi')
        ->and($activity->cost_cents)->toBe(4500);
});

it('creates a contact with the source and cost for a form from a new person', function () {
    activateExampleFolder();

    $this->postJson(route('leads.store'), leadFormFields(['phone' => '312-555-0162', 'email' => 'new@example.com']))
        ->assertOk();

    $contact = Contact::sole();
    expect($contact->phone_e164)->toBe('+13125550162')
        ->and($contact->lead_source_key)->toBe('angi')
        ->and($contact->acquisition_cost_cents)->toBe(4500);
});

it('keeps the first touch on the contact and records each later touch\'s own source and cost', function () {
    activateExampleFolder();
    $contactFields = ['phone' => '(202) 555-0123', 'email' => 'dana@example.com'];

    $this->postJson(route('leads.store'), leadFormFields($contactFields + ['source' => 'angi']))->assertOk();
    $this->postJson(route('leads.store'), leadFormFields($contactFields + ['source' => 'thumbtack']))->assertOk();
    $this->postJson(route('leads.store'), leadFormFields($contactFields + ['source' => 'angi']))->assertOk();

    $contact = Contact::sole();
    expect($contact->lead_source_key)->toBe('angi')
        ->and($contact->acquisition_cost_cents)->toBe(4500);

    $touches = Activity::query()
        ->where('type', ActivityType::FormReceived)
        ->orderBy('id')
        ->get(['lead_source_key', 'cost_cents'])
        ->map(fn (Activity $activity) => [$activity->lead_source_key, $activity->cost_cents])
        ->all();
    expect($touches)->toBe([['angi', 4500], ['thumbtack', 3200], ['angi', 4500]]);
});

it('accepts a source that is not in lead-sources.md, storing it raw with no cost and a note', function () {
    activateExampleFolder();

    $this->postJson(route('leads.store'), leadFormFields(['source' => 'Yelp ']))->assertOk();

    $contact = Contact::sole();
    expect($contact->lead_source_key)->toBe('yelp')
        ->and($contact->acquisition_cost_cents)->toBeNull();

    $activity = Activity::query()->where('type', ActivityType::FormReceived)->sole();
    expect($activity->lead_source_key)->toBe('yelp')
        ->and($activity->cost_cents)->toBeNull()
        ->and(Activity::query()->where('type', ActivityType::Note)->pluck('summary')->all())
        ->toBe(['unknown source: yelp']);
});

it('records a form with no source as direct without an unknown-source note', function (?string $source) {
    activateExampleFolder();

    $this->postJson(route('leads.store'), leadFormFields(['source' => $source]))->assertOk();

    expect(Contact::sole()->lead_source_key)->toBe('direct')
        ->and(Contact::sole()->acquisition_cost_cents)->toBeNull()
        ->and(Activity::query()->where('type', ActivityType::Note)->count())->toBe(0);
})->with([
    'no source' => [null],
    'an empty source' => [''],
    'a source of spaces' => ['   '],
]);

it('records a text with the source text and no unknown-source note', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM'.str_repeat('c', 32),
        'From' => '+12025550123',
        'Body' => 'Hello',
    ])->assertOk();

    expect(Contact::sole()->lead_source_key)->toBe('text')
        ->and(Activity::query()->where('type', ActivityType::Note)->count())->toBe(0);
});

it('still records a form when no business folder version is live, with no cost and a note', function () {
    $this->postJson(route('leads.store'), leadFormFields())
        ->assertOk()
        ->assertExactJson(['received' => true]);

    $contact = Contact::sole();
    expect($contact->lead_source_key)->toBe('angi')
        ->and($contact->acquisition_cost_cents)->toBeNull()
        ->and(Conversation::sole()->messages)->toHaveCount(1);

    $activity = Activity::query()->where('type', ActivityType::FormReceived)->sole();
    expect($activity->cost_cents)->toBeNull()
        ->and(Activity::query()->where('type', ActivityType::Note)->pluck('summary')->all())
        ->toBe(['Cost per lead unknown for angi: no business folder version is live yet']);
});

it('adds no cost note for the reserved sources when no business folder version is live', function () {
    $this->postJson(route('leads.store'), leadFormFields(['source' => null, 'phone' => '312-555-0162']))->assertOk();
    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM'.str_repeat('d', 32),
        'From' => '+12025550123',
        'Body' => 'Hello',
    ])->assertOk();

    expect(Contact::count())->toBe(2)
        ->and(Activity::query()->where('type', ActivityType::Note)->count())->toBe(0);
});

it('answers the 11th lead post from one IP within a minute with 429', function () {
    foreach (range(1, 10) as $number) {
        $this->postJson(route('leads.store'), leadFormFields(['message' => "Post {$number}"]))->assertOk();
    }

    $this->postJson(route('leads.store'), leadFormFields(['message' => 'Post 11']))->assertTooManyRequests();

    expect(Message::count())->toBe(10);
});

it('refuses fields longer than their caps', function (string $field, int $length) {
    $value = $field === 'email'
        ? str_repeat('a', $length - 12).'@example.com'
        : str_repeat('a', $length);

    $this->postJson(route('leads.store'), leadFormFields([$field => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Contact::count())->toBe(0);
})->with([
    'name' => ['name', 121],
    'phone' => ['phone', 41],
    'email' => ['email', 255],
    'message' => ['message', 5001],
    'source' => ['source', 65],
]);

it('exempts the lead form and webhooks from the request forgery check so a real post is not rejected with 419', function () {
    app(Kernel::class);

    expect(app(PreventRequestForgery::class)->getExcludedPaths())->toContain('leads', 'webhooks/*');
});
