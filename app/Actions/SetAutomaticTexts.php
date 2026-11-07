<?php

namespace App\Actions;

use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Models\User;
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
 */
class SetAutomaticTexts
{
    private const string ALREADY_ALLOWED = 'This customer has texted the business, so automatic texts are already allowed.';

    /**
     * Turn automatic texts on. A switch that is already on keeps its first time and its first user.
     *
     * @throws DomainException when the customer has texted the business.
     */
    public function on(Contact $contact, User $user): void
    {
        $this->change($contact, function (Contact $locked) use ($user): bool {
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
        $this->change($contact, function (Contact $locked): bool {
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
     * Read the contact again under a row lock, so two tabs never interleave, refuse a customer who has texted, and
     * redraw the card when the switch changed.
     *
     * @param  callable(Contact): bool  $write  Writes the change and says whether it changed anything.
     */
    private function change(Contact $contact, callable $write): void
    {
        $changed = DB::transaction(function () use ($contact, $write): bool {
            $locked = Contact::query()->lockForUpdate()->findOrFail($contact->id);

            if ($locked->hasTextedBusiness()) {
                throw new DomainException(self::ALREADY_ALLOWED);
            }

            return $write($locked);
        });

        $contact->refresh();

        if ($changed) {
            CustomerRecordChanged::dispatch($contact->id);
        }
    }
}
