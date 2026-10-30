<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ProjectActor;
use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Opening a project is not an agent action, so no checks-passed record is
 * kept; it records who opened it and puts one entry on the customer's timeline. The entry's summary is one of two fixed
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
            'summary' => $openedBy === ProjectActor::User ? 'Opened by the owner' : 'Opened by a new lead',
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'occurred_at' => $openedAt,
        ]);

        return $project;
    }
}
