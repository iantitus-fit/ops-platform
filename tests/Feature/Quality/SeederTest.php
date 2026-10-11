<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Support\PhoneNumber;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('creates the owner user and four fictional contacts', function () {
    expect(User::query()->count())->toBe(1)
        ->and(User::query()->value('email'))->toBe('test@example.com')
        ->and(Contact::query()->count())->toBe(4);
});

it('stores only phone numbers libphonenumber accepts, already in E.164', function () {
    $phones = Contact::query()->whereNotNull('phone_e164')->pluck('phone_e164');

    expect($phones)->toHaveCount(3)
        ->and($phones->every(fn (string $phone) => PhoneNumber::toE164($phone) === $phone))->toBeTrue();
});

it('gives no contact two open conversations', function () {
    $contacts = Contact::query()->withCount([
        'conversations as open_conversations_count' => fn ($query) => $query->where('status', ConversationStatus::Open),
    ])->get();

    expect($contacts->max('open_conversations_count'))->toBeLessThanOrEqual(1);
});

it('puts at least one timeline entry on every lead', function () {
    $leads = Contact::query()->where('status', ContactStatus::Lead)->withCount('activities')->get();

    expect($leads)->toHaveCount(3)
        ->and($leads->every(fn (Contact $lead) => $lead->activities_count >= 1))->toBeTrue();
});

it('stores acquisition costs as integer cents from the example lead sources, or null', function () {
    $costs = Contact::query()->orderBy('name')->pluck('acquisition_cost_cents', 'name')->all();

    expect($costs)->toBe([
        'Dana Whitfield' => 4500,
        'Marcus Lee' => 3200,
        'Priya Shah' => 0,
        'Sam Ortiz' => null,
    ]);
});

it('seeds Dana with two customer texts and one owner reply in one open conversation', function () {
    $dana = Contact::query()->where('name', 'Dana Whitfield')->sole();
    $conversation = $dana->conversations()->sole();
    $messages = $conversation->messages()->get();

    expect($dana->phone_e164)->toBe('+12025550123')
        ->and($dana->lead_source_key)->toBe('angi')
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($messages)->toHaveCount(3)
        ->and($messages->where('direction', MessageDirection::In)->where('author', MessageAuthor::Customer))->toHaveCount(2)
        ->and($messages->where('direction', MessageDirection::Out)->where('author', MessageAuthor::Owner))->toHaveCount(1)
        ->and($dana->activities()->where('type', ActivityType::TextReceived)->count())->toBe(2)
        ->and($dana->activities()->where('type', ActivityType::ReplySent)->count())->toBe(1);
});

it('seeds Marcus with one web form message that keeps the form fields', function () {
    $marcus = Contact::query()->where('name', 'Marcus Lee')->sole();
    $message = $marcus->conversations()->sole()->messages()->sole();

    expect($marcus->phone_e164)->toBe('+13125550162')
        ->and($message->channel)->toBe(MessageChannel::Web)
        ->and(array_keys($message->meta['form']))->toBe(['name', 'phone', 'email', 'source', 'message'])
        ->and($marcus->activities()->where('type', ActivityType::FormReceived)->count())->toBe(1);
});

it('seeds Priya as a customer with one closed conversation', function () {
    $priya = Contact::query()->where('name', 'Priya Shah')->sole();

    expect($priya->status)->toBe(ContactStatus::Customer)
        ->and($priya->lead_source_key)->toBe('referral')
        ->and($priya->conversations()->sole()->status)->toBe(ConversationStatus::Closed);
});

it('seeds Sam with an email and no phone', function () {
    $sam = Contact::query()->where('name', 'Sam Ortiz')->sole();

    expect($sam->phone_e164)->toBeNull()
        ->and($sam->email)->toBe('sam.ortiz@example.com')
        ->and($sam->lead_source_key)->toBe('direct')
        ->and(Conversation::query()->where('contact_id', $sam->id)->where('status', ConversationStatus::Open)->count())->toBe(1);
});
