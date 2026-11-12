<?php

use App\Actions\OutboundSender;
use App\Enums\MessageAuthor;
use App\Enums\SendOrigin;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * A lead who came in through the website form: no text from the customer, so they have never texted the business.
 */
function automaticTextsRuleWebFormLead(): Conversation
{
    activateExampleFolder();

    test()->postJson(route('leads.store'), [
        'name' => 'Dana Whitfield',
        'phone' => '+12025550199',
        'email' => 'dana@example.com',
        'source' => 'angi',
        'message' => 'Need a quote',
    ])->assertOk();

    return Conversation::query()->sole();
}

/**
 * A customer texting the business through the fake webhook, with a sid of their own so two texts never collide.
 */
function automaticTextsRuleCustomerTexts(string $body, string $sid, string $from = '+12025550123'): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_repeat($sid, 32),
        'From' => $from,
        'Body' => $body,
    ]))->assertOk();
}

it('refuses an automatic text to a web-form lead who never texted (D-21)', function () {
    $conversation = automaticTextsRuleWebFormLead();

    expect(app(OutboundSender::class)->whyBlocked($conversation, SendOrigin::Automated))
        ->toBe('The customer has not texted the business or agreed to automatic texts.');

    expect(fn () => app(OutboundSender::class)->send($conversation, 'Your visit is tomorrow.', MessageAuthor::Agent, SendOrigin::Automated))
        ->toThrow(DomainException::class, 'The customer has not texted the business or agreed to automatic texts.');

    expect(Message::query()->where('direction', 'out')->count())->toBe(0);
});

it('sends it once the owner turned the switch on (D-20)', function () {
    $conversation = automaticTextsRuleWebFormLead();

    $conversation->contact->update([
        'automatic_texts_on_at' => now(),
        'automatic_texts_on_by_user_id' => User::factory()->create()->id,
    ]);

    expect(app(OutboundSender::class)->whyBlocked($conversation->refresh(), SendOrigin::Automated))->toBeNull();
});

it('counts a contact who texted the business', function () {
    activateExampleFolder();
    automaticTextsRuleCustomerTexts('My AC is out', 'a');

    $contact = Contact::query()->sole();
    $conversation = Conversation::query()->sole();

    expect($contact->hasTextedBusiness())->toBeTrue()
        ->and($contact->mayGetAutomaticTexts())->toBeTrue()
        ->and(app(OutboundSender::class)->whyBlocked($conversation, SendOrigin::Automated))->toBeNull();
});

it('never counts a first STOP, and counts a later START (D-20)', function () {
    activateExampleFolder();
    automaticTextsRuleCustomerTexts('STOP', 'b', '+12025550188');

    $contact = Contact::query()->sole();
    $conversation = Conversation::query()->sole();

    expect($contact->hasTextedBusiness())->toBeFalse()
        ->and($contact->mayGetAutomaticTexts())->toBeFalse()
        ->and(app(OutboundSender::class)->whyBlocked($conversation, SendOrigin::Automated))->toStartWith('Opted out by texting STOP on');

    automaticTextsRuleCustomerTexts('START', 'c', '+12025550188');

    expect($contact->refresh()->hasTextedBusiness())->toBeTrue();
});

/**
 * The query on messages must name this customer's conversations by their ids, not through a subquery on
 * conversations. With the subquery, PostgreSQL can start from every customer text in the table and read the whole
 * conversations table again for each one; in the D-46 invariant test that cost up to a second a send and added about
 * nine minutes to the PostgreSQL suite. The shape of the query is what decides that plan, so the shape is what this
 * checks: a timing would be slow to run and would not fail reliably.
 */
it('reads only this customer\'s conversations, named by id, to decide whether they texted (D-20)', function () {
    Message::factory()->count(20)->create();

    $conversation = Conversation::factory()->create();
    $earlier = Conversation::factory()->closed()->for($conversation->contact)->create();
    Message::factory()->for($conversation)->create();

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query;
    });

    expect($conversation->contact->hasTextedBusiness())->toBeTrue();

    $onMessages = collect($queries)->sole(fn (QueryExecuted $query): bool => str_contains($query->sql, 'from "messages"'));

    expect($onMessages->sql)->not->toContain('from "conversations"')
        ->and($onMessages->bindings)->toContain($conversation->id, $earlier->id);
});

it('leaves the owner and the safety line alone (D-21)', function () {
    $conversation = automaticTextsRuleWebFormLead();
    $sender = app(OutboundSender::class);

    expect($sender->whyBlocked($conversation, SendOrigin::Owner))->toBeNull()
        ->and($sender->whyBlocked($conversation, SendOrigin::LifeSafety))->toBeNull();
});

it('keeps the order: opted out, closed, paused, then this rule', function () {
    $sender = app(OutboundSender::class);

    $optedOut = Conversation::factory()->for(Contact::factory()->optedOut()->create())->create();
    $closed = Conversation::factory()->closed()->for(Contact::factory()->create())->create();
    $paused = Conversation::factory()->paused()->for(Contact::factory()->create())->create();
    $neither = Conversation::factory()->for(Contact::factory()->create())->create();

    expect($sender->whyBlocked($optedOut, SendOrigin::Automated))->toStartWith('Opted out by texting STOP on')
        ->and($sender->whyBlocked($closed, SendOrigin::Automated))->toBe('This conversation is closed.')
        ->and($sender->whyBlocked($paused, SendOrigin::Automated))->toBe('The assistant is paused in this conversation.')
        ->and($sender->whyBlocked($neither, SendOrigin::Automated))->toBe(OutboundSender::NOT_AGREED);
});
