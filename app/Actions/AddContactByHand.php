<?php

namespace App\Actions;

use App\Enums\ContactStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The owner adds a customer who called rather than texted (D-19, reversing Phase 1's "never by hand"). The contact is
 * matched the way an inbound is (01 D-12): the phone first, then the email, and a match is refused beside its field
 * naming the person already saved, so a later text from that number lands on the same contact and no one is saved
 * twice. RecordInbound is not edited; this is its own small lookup that asks the same two questions.
 *
 * A hand-added contact is a Lead and shows in New leads for 7 days. Adding never sends a text and never opens a
 * project or a conversation (D-19, 07 D-07): booking by hand opens the job. Its automatic texts stay off until the
 * owner turns them on (D-20).
 */
class AddContactByHand
{
    /**
     * The phone is stored as E.164. The email is stored trimmed and lower-cased, exactly as RecordInbound stores and
     * matches one, because RecordInbound later matches with an exact where('email', ...), which PostgreSQL compares
     * case-sensitively: a hand-added "Sam@Example.com" would otherwise be missed by a form lead giving "sam@example.com".
     *
     * @throws ContactRefused when neither is given, the phone cannot be read, or either matches a contact.
     */
    public function handle(string $name, ?string $phone, ?string $email): Contact
    {
        $name = trim($name);
        $rawPhone = trim((string) $phone);
        $email = $this->normalizeEmail($email);

        if ($rawPhone === '' && $email === null) {
            throw new ContactRefused('Enter a phone number or an email.', 'phone');
        }

        $e164 = null;

        if ($rawPhone !== '') {
            $e164 = PhoneNumber::toE164($rawPhone);

            if ($e164 === null) {
                throw new ContactRefused('This is not a US phone number we can text. Use one such as 312 555 0147.', 'phone');
            }
        }

        try {
            $contact = DB::transaction(function () use ($name, $e164, $email): Contact {
                $this->refuseAMatch($e164, $email);

                return Contact::create([
                    'name' => $name,
                    'phone_e164' => $e164,
                    'email' => $email,
                    'status' => ContactStatus::Lead,
                    'last_activity_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $match = $e164 === null ? null : Contact::query()->where('phone_e164', $e164)->first();

            throw new ContactRefused($this->alreadySaved('number', $match), 'phone');
        }

        CustomerRecordChanged::dispatch($contact->id);

        return $contact;
    }

    /**
     * Refuse a phone that matches a contact, then an email that does.
     */
    private function refuseAMatch(?string $e164, ?string $email): void
    {
        if ($e164 !== null) {
            $match = Contact::query()->where('phone_e164', $e164)->first();

            if ($match !== null) {
                throw new ContactRefused($this->alreadySaved('number', $match), 'phone');
            }
        }

        if ($email !== null) {
            $match = Contact::query()->where('email', $email)->orderBy('id')->first();

            if ($match !== null) {
                throw new ContactRefused($this->alreadySaved('email', $match), 'email');
            }
        }
    }

    private function alreadySaved(string $what, ?Contact $match): string
    {
        $name = $match === null ? '' : trim((string) $match->name);

        return "This {$what} is already saved as ".($name === '' ? '(no name)' : $name).'.';
    }

    /**
     * An email as RecordInbound stores and compares it: trimmed and lower-cased, or null when empty.
     */
    private function normalizeEmail(?string $email): ?string
    {
        $normalized = mb_strtolower(trim((string) $email));

        return $normalized === '' ? null : $normalized;
    }
}
