<?php

use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Tuesday 2026-10-13 at the given business-zone time, the owner with a phone, and Dana's text arriving
 * before the front desk runs. Dana is on file so the alert can name her.
 */
function danaAsksAndFrontDeskReplies(string $body, array $reply, string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone('+12025550199')->create();
    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    frontDeskCustomerTexts($body);
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached the customer's own phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function textsSentToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === '+12025550123',
    ));
}

it('keeps a price answer as a draft with its plain reason and sends none of it to the customer (GATE-01, D-07)', function () {
    danaAsksAndFrontDeskReplies('How much for a new unit?', [
        'reply' => 'Mike will text you a price for a new unit.',
        'label' => 'price',
    ]);

    $draft = theAgentMessage();

    expect($draft->status->value)->toBe('draft')
        ->and($draft->body)->toBe('Mike will text you a price for a new unit.')
        ->and($draft->gate['reason']['plain'])->toBe('Held: asks about price')
        ->and(collect(textsSentToDana())->pluck('body')->implode("\n"))->not->toContain('Mike will text you a price');
});

it('texts the owner one alert that names the customer and the plain reason, and quotes both sides (GATE-01, D-07)', function () {
    danaAsksAndFrontDeskReplies('How much for a new unit?', [
        'reply' => 'Mike will text you a price for a new unit.',
        'label' => 'price',
    ]);

    $draft = theAgentMessage();
    $alerts = OwnerAlert::query()->get();
    $toOwner = array_values(array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === '+12025550199'));

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->reason->value)->toBe('draft_waiting')
        ->and($alerts[0]->message_id)->toBe($draft->id)
        ->and($toOwner)->toHaveCount(1)
        ->and(explode("\n", $toOwner[0]['body'])[0])->toBe('Dana Whitfield: Held: asks about price')
        ->and($toOwner[0]['body'])->toContain('They wrote: "How much for a new unit?"')
        ->and($toOwner[0]['body'])->toContain('Draft: "Mike will text you a price for a new unit."');
});

it('alerts a held complaint reply as a complaint, at once, even after hours (GATE-01, D-20)', function () {
    danaAsksAndFrontDeskReplies('You never showed up!', [
        'reply' => "I'm sorry about the visit.",
        'label' => 'complaint',
    ], '2026-10-13 20:00');

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason->value)->toBe('complaint')
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->message_id)->toBe(theAgentMessage()->id);
});

it('puts a draft_held entry on the customer timeline and shows it on the card (D-07)', function () {
    danaAsksAndFrontDeskReplies('How much for a new unit?', [
        'reply' => 'Mike will text you a price for a new unit.',
        'label' => 'price',
    ]);

    $contact = Contact::query()->where('phone_e164', '+12025550123')->sole();
    $entry = $contact->activities()->where('type', 'draft_held')->sole();
    $card = app(CustomerCard::class)->render($contact->fresh());

    expect($entry->subject_id)->toBe(theAgentMessage()->id)
        ->and($entry->summary)->toBe('Held: asks about price')
        ->and($card)->toContain('Draft held for owner')
        ->and($card)->toContain('Held: asks about price')
        ->and($card)->toContain('Mike will text you a price for a new unit.');
});

it('shows a held draft in the conversation as a draft in the warning colour, never as sent', function () {
    danaAsksAndFrontDeskReplies('How much for a new unit?', [
        'reply' => 'Mike will text you a price for a new unit.',
        'label' => 'price',
    ]);

    test()->actingAs(User::factory()->create());

    $html = Livewire::test(ConversationThread::class, ['conversation' => Conversation::sole()])->html();

    expect($html)->toContain('Mike will text you a price for a new unit.')
        ->and($html)->toMatch('/fi-color-warning[^>]*>\s*(?:<[^>]+>\s*)*draft\s*</')
        ->and($html)->not->toMatch('/fi-color-success[^>]*>\s*(?:<[^>]+>\s*)*draft\s*</');
});
