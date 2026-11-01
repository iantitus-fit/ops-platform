<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Icm\HiddenCharacters;
use App\Models\Project;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * One of the four writers of projects (D-11). Renaming is not an agent action, so no checks-passed record is kept; it
 * stores the owner's name and puts one entry on the customer's timeline. The entry's summary is a fixed platform
 * sentence and never carries the name, because the name is owner-typed text: the card quotes it in the project section
 * (D-13). A name is one line of at most 100 characters, with no hidden characters (D-08; the bound is Claude's
 * discretion, research A3). Renaming never changes the contact's status (D-07), asks nothing and alerts no one (D-04).
 */
class RenameProject
{
    /**
     * The most characters a name may hold, counted as characters and not bytes.
     */
    public const MAX_LENGTH = 100;

    /**
     * The owner gives a project a name, or clears it with an empty one so the screens fall back to "<Word> N".
     *
     * The name is tidied first: every run of whitespace, line breaks included, becomes one space, and the ends are
     * trimmed. A name the action cannot keep is refused before any write, in plain words the screen shows. Then the
     * project is read again under a row lock, so renaming to the name it already has, from a second tab or not,
     * changes nothing and adds no entry (D-11).
     *
     * The projects row keeps no user id for a rename (D-08), so the entry says the owner did it.
     *
     * @throws DomainException when the name holds a hidden character or is longer than the bound
     */
    public function handle(Project $project, User $user, ?string $name): void
    {
        $tidy = $this->tidy($name);

        $renamed = DB::transaction(function () use ($project, $tidy): bool {
            Project::query()->lockForUpdate()->find($project->id);
            $project->refresh();

            if ($project->name === $tidy) {
                return false;
            }

            $project->update(['name' => $tidy]);

            $project->contact->activities()->create([
                'type' => ActivityType::ProjectRenamed,
                'summary' => 'Renamed by the owner',
                'subject_type' => $project->getMorphClass(),
                'subject_id' => $project->id,
                'occurred_at' => now(),
            ]);

            return true;
        });

        if ($renamed) {
            CustomerRecordChanged::dispatch($project->contact_id);
        }
    }

    /**
     * The name on one line, or null when nothing is left. Throws when the name cannot be kept.
     *
     * Text that is not valid UTF-8 makes the collapsing step return null, which would read as an empty name and clear
     * the stored one, so it is refused as a hidden character, the same way HiddenCharacters::found() counts it.
     * The hidden-character check runs on the tidied name because a line break in the raw text is only whitespace once
     * it has become a space.
     *
     * @throws DomainException
     */
    private function tidy(?string $name): ?string
    {
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', (string) $name);

        if ($collapsed === null || HiddenCharacters::found($collapsed)) {
            throw new DomainException('A name cannot hold a hidden character, such as a direction mark or a zero-width space. Type it again.');
        }

        $tidy = trim($collapsed);

        if (mb_strlen($tidy) > self::MAX_LENGTH) {
            throw new DomainException('A name can be at most '.self::MAX_LENGTH.' characters.');
        }

        return $tidy === '' ? null : $tidy;
    }
}
