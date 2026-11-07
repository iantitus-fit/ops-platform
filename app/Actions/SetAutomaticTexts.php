<?php

namespace App\Actions;

use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Models\User;
use App\Rooms\RecordRoomEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The owner's switch for automatic texts on a customer who has never texted the business (D-20). It is off by default and
 * the owner turns it on when the customer agreed to get texts, on a call for example; the contact keeps who turned it on
 * and when. It governs automatic sends only, through OutboundSender's check (D-21), and never blocks the owner's own
 * reply. This is not an agent action, so it has no chokepoint of its own: the switch only changes what that check reads.
 *
 * A customer's own text turns automatic texts on by itself, derived and never stored (Contact::hasTextedBusiness()), so
 * the switch is refused for a customer who has texted. A STOP never turns it on.
 *
 * Every change also writes one automatic_texts_changed entry on the contact's timeline naming who changed it and to which
 * state, at the time it changed (Ian, 2026-11-07, adds to D-20). The contact's columns hold only the current agreement, so
 * the timeline is what keeps the record that a customer had agreed after the switch is turned off. The entry goes through
 * RecordRoomEntry as the schedule and booking room's, the way EditVisitNote's does, so a room that is off writes none.
 */
class SetAutomaticTexts
{
    private const string ALREADY_ALLOWED = 'This customer has texted the business, so automatic texts are already allowed.';

    public function __construct(private readonly RecordRoomEntry $recordRoomEntry) {}

    /**
     * Turn automatic texts on. A switch that is already on keeps its first time and its first user.
     *
     * @throws DomainException when the customer has texted the business.
     */
    public function on(Contact $contact, User $user): void
    {
        $this->change($contact, $user, 'on', function (Contact $locked) use ($user): bool {
            if ($locked->automatic_texts_on_at !== null) {
                return false;
            }

            $locked->update([
                'automatic_texts_on_at' => now(),
                'automatic_texts_on_by_user_id' => $user->id,
            ]);

            return true;
        });
    }

    /**
     * Turn automatic texts off, which clears who turned them on and when.
     *
     * @throws DomainException when the customer has texted the business.
     */
    public function off(Contact $contact, User $user): void
    {
        $this->change($contact, $user, 'off', function (Contact $locked): bool {
            if ($locked->automatic_texts_on_at === null) {
                return false;
            }

            $locked->update([
                'automatic_texts_on_at' => null,
                'automatic_texts_on_by_user_id' => null,
            ]);

            return true;
        });
    }

    /**
     * Read the contact again under a row lock, so two tabs never interleave, refuse a customer who has texted, write the
     * change and its timeline entry together, and redraw the card when the switch changed. A switch already in that state
     * writes nothing.
     *
     * @param  'on'|'off'  $state  The state the switch is turned to, as the entry names it.
     * @param  callable(Contact): bool  $write  Writes the change and says whether it changed anything.
     */
    private function change(Contact $contact, User $user, string $state, callable $write): void
    {
        $changed = DB::transaction(function () use ($contact, $user, $state, $write): bool {
            $locked = Contact::query()->lockForUpdate()->findOrFail($contact->id);

            if ($locked->hasTextedBusiness()) {
                throw new DomainException(self::ALREADY_ALLOWED);
            }

            if (! $write($locked)) {
                return false;
            }

            $this->recordRoomEntry->handle(
                $locked,
                'schedule_and_booking',
                'automatic_texts_changed',
                $user->name.' turned automatic texts '.$state.'.',
            );

            return true;
        });

        $contact->refresh();

        if ($changed) {
            CustomerRecordChanged::dispatch($contact->id);
        }
    }
}
