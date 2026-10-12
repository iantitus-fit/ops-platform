<?php

use App\Enums\PausedReason;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Widgets\DraftsWaiting;
use App\Filament\Widgets\NewLeads;
use App\Filament\Widgets\PausedConversationsWaiting;
use App\Filament\Widgets\TodaysConversations;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));
    $this->actingAs(User::factory()->create());
});

/**
 * A conversation for a customer with the given name, with one held draft written the given number of minutes ago.
 * Returns the draft.
 */
function dashboardWidgetsHeldDraft(string $customer, int $minutesAgo, string $reason = 'Held: asks about price'): Message
{
    $conversation = Conversation::factory()
        ->for(Contact::factory()->create(['name' => $customer]))
        ->create(['last_message_at' => now()->subMinutes($minutesAgo + 5)]);

    return Message::factory()->for($conversation)->draft()->create([
        'created_at' => now()->subMinutes($minutesAgo),
        'gate' => ['version' => 1, 'reason' => ['code' => 'code:price', 'plain' => $reason]],
    ]);
}

/**
 * A paused conversation for a customer with the given name whose last text came from the customer or from the owner.
 */
function dashboardWidgetsPaused(string $customer, int $minutesAgo, bool $customerSpokeLast = true): Conversation
{
    $conversation = Conversation::factory()
        ->for(Contact::factory()->create(['name' => $customer]))
        ->paused(PausedReason::TakenOver)
        ->create(['last_message_at' => now()->subMinutes($minutesAgo)]);

    $customerSpokeLast
        ? Message::factory()->for($conversation)->create(['created_at' => now()->subMinutes($minutesAgo)])
        : Message::factory()->for($conversation)->fromOwner()->create(['created_at' => now()->subMinutes($minutesAgo)]);

    return $conversation;
}

it('lists live drafts oldest first with the customer, the held reason and how long they have waited', function () {
    $newer = dashboardWidgetsHeldDraft('Dana Whitfield', 30);
    $older = dashboardWidgetsHeldDraft('Ray Ortega', 120, 'Held: asks for a person');
    $withdrawn = Message::factory()->for(Conversation::factory()->create())->withdrawn()->create();

    Livewire::test(DraftsWaiting::class)
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->assertCanNotSeeTableRecords([$withdrawn])
        ->assertSee('Drafts waiting')
        ->assertSee('Dana Whitfield')
        ->assertSee('Held: asks about price')
        ->assertSee('Held: asks for a person')
        ->assertSee('2 hours ago');
});

it('flags a waiting draft whose alert could not be sent and links each row to its conversation', function () {
    $alerted = dashboardWidgetsHeldDraft('Dana Whitfield', 30);
    $unalerted = dashboardWidgetsHeldDraft('Ray Ortega', 60);
    OwnerAlert::factory()->create(['conversation_id' => $alerted->conversation_id, 'message_id' => $alerted->id, 'sent_at' => now()]);
    OwnerAlert::factory()->create([
        'conversation_id' => $unalerted->conversation_id,
        'message_id' => $unalerted->id,
        'failed_at' => now(),
        'failure' => 'No phone on file',
    ]);

    $widget = Livewire::test(DraftsWaiting::class)
        ->assertSee('Not alerted: No phone on file');

    expect(substr_count($widget->html(), 'Not alerted'))->toBe(1);

    $widget->assertSeeHtml(ConversationResource::getUrl('view', ['record' => $unalerted->conversation_id]))
        ->assertSeeHtml(ConversationResource::getUrl('view', ['record' => $alerted->conversation_id]));
});

it('lists paused conversations where the customer spoke last, oldest waiting first', function () {
    $newerWaiting = dashboardWidgetsPaused('Dana Whitfield', 10);
    $olderWaiting = dashboardWidgetsPaused('Ray Ortega', 90);
    $ownerReplied = dashboardWidgetsPaused('Cal Brandt', 5, customerSpokeLast: false);
    $notPaused = Conversation::factory()->create(['last_message_at' => now()->subMinutes(3)]);
    Message::factory()->for($notPaused)->create();

    Livewire::test(PausedConversationsWaiting::class)
        ->assertCanSeeTableRecords([$olderWaiting, $newerWaiting], inOrder: true)
        ->assertCanNotSeeTableRecords([$ownerReplied, $notPaused])
        ->assertSee('Customers waiting while you handle it')
        ->assertSee('Ray Ortega');
});

it('lists contacts who became leads in the last 7 days, newest first, with the cost per lead in dollars', function () {
    $recent = Contact::factory()->fromSource('google', 4250)->create(['name' => 'Dana Whitfield', 'created_at' => now()->subDays(1)]);
    $newest = Contact::factory()->create(['name' => 'Ray Ortega', 'created_at' => now()->subHour()]);
    $old = Contact::factory()->create(['name' => 'Cal Brandt', 'created_at' => now()->subDays(8)]);
    $customer = Contact::factory()->customer()->create(['name' => 'Eve Marsh', 'created_at' => now()->subDay()]);

    Livewire::test(NewLeads::class)
        ->assertCanSeeTableRecords([$newest, $recent], inOrder: true)
        ->assertCanNotSeeTableRecords([$old, $customer])
        ->assertSee('New leads')
        ->assertSee('google')
        ->assertSee('$42.50');
});

it('lists conversations whose last message is today in the business time zone, newest first', function () {
    activateExampleFolder();

    // 4:30 pm Tuesday in Chicago is 21:30 UTC, so the business day started at 05:00 UTC.
    $morning = Conversation::factory()->create(['last_message_at' => Carbon::parse('2026-10-13 09:00', 'America/Chicago')]);
    $afternoon = Conversation::factory()->create(['last_message_at' => Carbon::parse('2026-10-13 15:45', 'America/Chicago')]);
    $lastNight = Conversation::factory()->create(['last_message_at' => Carbon::parse('2026-10-12 22:00', 'America/Chicago')]);
    $silent = Conversation::factory()->create(['last_message_at' => null]);

    Livewire::test(TodaysConversations::class)
        ->assertCanSeeTableRecords([$afternoon, $morning], inOrder: true)
        ->assertCanNotSeeTableRecords([$lastNight, $silent])
        ->assertSee("Today's conversations");
});

it('shows the four home screen widgets in place of the stock ones', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Drafts waiting',
            'Customers waiting while you handle it',
            'New leads',
            "Today's conversations",
        ])
        ->assertDontSee('fi-filament-info-widget')
        ->assertDontSee('fi-account-widget');
});
