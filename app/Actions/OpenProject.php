<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ProjectActor;
use App\Events\CustomerRecordChanged;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Opening a project is not an agent action, so no checks-passed record is
 * kept; it records who opened it and puts one entry on the customer's timeline. The entry's summary is one of three fixed
 * platform sentences: it carries neither the business's word for a project nor owner-typed text, because the word is
 * read when the card is drawn and a project's name is quoted only in the card's project section (research A4).
 * Opening never changes the contact's status (D-07).
 */
class OpenProject
{
    /**
     * The owner opens a project for a customer by hand. It is tied to the customer's open conversation at this moment,
     * or to none when no conversation is open (D-06). A customer may have any number of projects open at once (D-05).
     *
     * The contact row is locked first, so a hand open and the automatic open for a new lead never interleave.
     */
    public function byHand(Contact $contact, User $user): Project
    {
        $project = DB::transaction(function () use ($contact, $user): Project {
            Contact::query()->lockForUpdate()->find($contact->id);

            return $this->open(
                $contact,
                ProjectActor::User,
                $user,
                $contact->openConversation()->value('id'),
            );
        });

        CustomerRecordChanged::dispatch($contact->id);

        return $project;
    }

    /**
     * A customer's booking on the page needs a new job, so one opens for them, tied to the conversation the link was
     * sent in (D-22). It is the third step of BookAppointment's job choice and never reopens a closed job. The contact
     * row is locked first, as in the other opens.
     */
    public function forBookingPage(Contact $contact, int $conversationId): Project
    {
        $project = DB::transaction(function () use ($contact, $conversationId): Project {
            Contact::query()->lockForUpdate()->find($contact->id);

            return $this->open($contact, ProjectActor::Customer, null, $conversationId);
        });

        CustomerRecordChanged::dispatch($contact->id);

        return $project;
    }

    /**
     * The automatic open for a new lead (LEAD-05). A contact that already has any project, opened by a lead or by hand,
     * gets none. The project is tied to the conversation the arriving message belongs to (D-06). It never touches the
     * contact's own fields, so the new lead stays a lead (D-07). Returns the project, or null when none was made.
     *
     * "Already has a project" and "the database's one-lead-project index refused the insert" both mean already opened.
     * The insert runs in a nested transaction, which is a savepoint, so on Postgres a refused insert does not spoil the
     * outer transaction (the RecordInbound::openConversationFor() precedent).
     */
    public function forNewLead(Contact $contact, Activity $arrival): ?Project
    {
        $project = DB::transaction(function () use ($contact, $arrival): ?Project {
            Contact::query()->lockForUpdate()->find($contact->id);

            if ($contact->projects()->exists()) {
                return null;
            }

            $conversationId = $arrival->subject_type === (new Message)->getMorphClass()
                ? Message::query()->whereKey($arrival->subject_id)->value('conversation_id')
                : null;

            try {
                return DB::transaction(fn (): Project => $this->open($contact, ProjectActor::Lead, null, $conversationId));
            } catch (UniqueConstraintViolationException) {
                return null;
            }
        });

        if ($project !== null) {
            CustomerRecordChanged::dispatch($contact->id);
        }

        return $project;
    }

    /**
     * Write the project and its one timeline entry. Called inside a transaction.
     */
    private function open(Contact $contact, ProjectActor $openedBy, ?User $user, ?int $conversationId): Project
    {
        $openedAt = now();

        $project = $contact->projects()->create([
            'conversation_id' => $conversationId,
            'opened_by' => $openedBy,
            'opened_by_user_id' => $user?->id,
            'opened_at' => $openedAt,
        ]);

        $contact->activities()->create([
            'type' => ActivityType::ProjectOpened,
            'summary' => match ($openedBy) {
                ProjectActor::User => 'Opened by the owner',
                ProjectActor::Lead => 'Opened by a new lead',
                ProjectActor::Customer => 'Opened by the customer on the booking page',
            },
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'occurred_at' => $openedAt,
        ]);

        return $project;
    }
}
