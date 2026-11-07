<?php

use App\Actions\AddContactByHand;
use App\Actions\BookAppointment;
use App\Actions\SetAutomaticTexts;
use App\Enums\ActivityType;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Livewire\ConversationThread;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The owner's switch for automatic texts on a contact who has never texted (D-20, D-21, U-26): off by default, turned on
 * when the customer agreed on a call, recorded with who and when. It governs automatic sends only; the owner can always
 * reply (D-20).
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function automaticTextsChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * Sam Ortiz, added by hand the way the owner adds a customer who called: never texted, switch off.
 */
function automaticTextsSam(string $phone = '(312) 555-0188'): Contact
{
    return app(AddContactByHand::class)->handle('Sam Ortiz', $phone, null);
}

/**
 * Dana Whitfield, who texted the business, so the switch does not apply to her.
 */
function automaticTextsDana(): Contact
{
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    Message::factory()->for(Conversation::factory()->for($dana)->create())->create(['handled_at' => now()]);

    return $dana;
}

/**
 * A contact's screen, mounted the way the contact page mounts it.
 */
function automaticTextsScreen(Contact $contact): Testable
{
    return Livewire::test(ViewContact::class, ['record' => $contact->getRouteKey()]);
}

it('turns automatic texts on and off with who and when', function () {
    $this->travelTo('2026-11-03 10:00:00');
    $sam = automaticTextsSam();

    app(SetAutomaticTexts::class)->on($sam, $this->owner);

    $sam->refresh();
    expect($sam->automatic_texts_on_at->toDateTimeString())->toBe('2026-11-03 10:00:00')
        ->and($sam->automatic_texts_on_by_user_id)->toBe($this->owner->id);

    $this->travelTo('2026-11-04 10:00:00');
    app(SetAutomaticTexts::class)->on($sam, User::factory()->create());

    $sam->refresh();
    expect($sam->automatic_texts_on_at->toDateTimeString())->toBe('2026-11-03 10:00:00')
        ->and($sam->automatic_texts_on_by_user_id)->toBe($this->owner->id);

    app(SetAutomaticTexts::class)->off($sam, $this->owner);

    $sam->refresh();
    expect($sam->automatic_texts_on_at)->toBeNull()
        ->and($sam->automatic_texts_on_by_user_id)->toBeNull();
});

it('refuses the switch for a customer who has texted', function () {
    $dana = automaticTextsDana();

    expect(fn () => app(SetAutomaticTexts::class)->on($dana, $this->owner))
        ->toThrow(DomainException::class, 'This customer has texted the business, so automatic texts are already allowed.')
        ->and(fn () => app(SetAutomaticTexts::class)->off($dana, $this->owner))
        ->toThrow(DomainException::class, 'This customer has texted the business, so automatic texts are already allowed.');

    expect($dana->fresh()->automatic_texts_on_at)->toBeNull();
});

it('shows the switch only to a contact who never texted', function () {
    $this->travelTo(automaticTextsChicago('2026-11-03 11:00'));
    $sam = automaticTextsSam();

    automaticTextsScreen($sam)
        ->assertActionVisible('automaticTexts')
        ->assertActionHasLabel('automaticTexts', 'Turn on automatic texts')
        ->assertSeeInOrder(['Automatic texts', 'Off'])
        ->mountAction('automaticTexts')
        ->assertSee('Turn on automatic texts for Sam Ortiz?')
        ->assertSee('Only turn this on if the customer agreed to get texts from the business, on a call for example. The business will then text booking confirmations and reminders on its own.')
        ->assertSee('Turn on')
        ->callMountedAction()
        ->assertNotified('Automatic texts are on')
        ->assertActionHasLabel('automaticTexts', 'Turn off automatic texts')
        ->assertSee('On, turned on by Ian Owner on Nov 3, 2026');

    expect($sam->fresh()->automatic_texts_on_by_user_id)->toBe($this->owner->id);

    automaticTextsScreen($sam->fresh())
        ->mountAction('automaticTexts')
        ->assertSee('Turn off automatic texts for Sam Ortiz?')
        ->assertSee('The business will stop texting confirmations and reminders on its own. You can still reply yourself.')
        ->callMountedAction()
        ->assertNotified('Automatic texts are off')
        ->assertActionHasLabel('automaticTexts', 'Turn on automatic texts')
        ->assertSeeInOrder(['Automatic texts', 'Off']);

    expect($sam->fresh()->automatic_texts_on_at)->toBeNull();

    automaticTextsScreen(automaticTextsDana())
        ->assertActionHidden('automaticTexts')
        ->assertDontSee('Automatic texts');
});

it('names a user who was removed, and escapes the name in the dialog', function () {
    $sam = app(AddContactByHand::class)->handle('<script>alert(1)</script> Sam', '(312) 555-0188', null);
    $gone = User::factory()->create();
    app(SetAutomaticTexts::class)->on($sam, $gone);
    $gone->delete();

    automaticTextsScreen($sam->fresh())
        ->assertSee('On, turned on by a removed user on')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->mountAction('automaticTexts')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('Turn off automatic texts for', false);
});

/**
 * Sam's thread: a conversation the owner started, so Sam has never texted.
 */
function automaticTextsOwnerStartedThread(Contact $contact): Conversation
{
    $conversation = Conversation::factory()->for($contact)->create(['last_message_at' => now()]);
    Message::factory()->for($conversation)->outbound()->create();

    return $conversation;
}

it('shows the reply-box line and still lets the owner reply', function () {
    $sam = automaticTextsSam();
    $conversation = automaticTextsOwnerStartedThread($sam);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('This customer has not texted the business or agreed to texts.');

    $html = $thread->html();
    preg_match('/<p id="([^"]+)"[^>]*>\s*This customer has not texted the business or agreed to texts\./', $html, $matches);

    expect($matches)->not->toBeEmpty()
        ->and($html)->toContain('aria-describedby="'.$matches[1].'"');

    $thread->set('body', 'Hi Sam, it is Mike from the shop.')->call('sendReply')->assertNotified('Reply sent');

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['to'])->toBe('+13125550188');
});

it('leaves the reply-box line out once agreed, and for a customer who texted', function () {
    $sam = automaticTextsSam();
    $conversation = automaticTextsOwnerStartedThread($sam);
    app(SetAutomaticTexts::class)->on($sam, $this->owner);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertDontSee('This customer has not texted the business or agreed to texts.');

    $dana = automaticTextsDana();

    Livewire::test(ConversationThread::class, ['conversation' => $dana->conversations()->sole()])
        ->assertDontSee('This customer has not texted the business or agreed to texts.');
});

/**
 * The note the sender left when it did not send a booking text, as its sentence.
 *
 * @return list<string>
 */
function automaticTextsNotSentNotes(): array
{
    return Activity::query()
        ->where('type', ActivityType::RoomEntry->value)
        ->where('entry', 'booking_text_not_sent')
        ->orderBy('id')
        ->pluck('summary')
        ->all();
}

it('sends a hand-booked confirmation only once agreed (D-21)', function () {
    activateExampleFolder();
    $this->travelTo(automaticTextsChicago('2026-11-03 11:00')->utc());

    $sam = automaticTextsSam();
    $off = app(BookAppointment::class)->byHand($sam, $this->owner, 'ac-repair', automaticTextsChicago('2026-11-05 10:00'), null, null);

    $row = $off->sends()->where('lead_minutes', 0)->sole();

    expect(textsSentToCustomers())->toBeEmpty()
        ->and($row->skipped_at)->not->toBeNull()
        ->and($row->sent_at)->toBeNull()
        ->and(automaticTextsNotSentNotes())->toBe(['Confirmation text not sent: the customer has not agreed to automatic texts.'])
        ->and(OwnerAlert::query()->count())->toBe(0);

    $second = automaticTextsSam('(312) 555-0199');
    app(SetAutomaticTexts::class)->on($second, $this->owner);

    $on = app(BookAppointment::class)->byHand($second, $this->owner, 'ac-repair', automaticTextsChicago('2026-11-06 10:00'), null, null);

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['to'])->toBe('+13125550199')
        ->and($on->sends()->where('lead_minutes', 0)->sole()->sent_at)->not->toBeNull()
        ->and(Conversation::query()->where('contact_id', $second->id)->count())->toBe(1);
});
