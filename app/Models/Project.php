<?php

namespace App\Models;

use App\Enums\ProjectActor;
use App\Enums\ProjectWord;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A piece of work for one contact. The table is the platform's own, not a room's (04 D-23, D-10). Only OpenProject,
 * CloseProject, ReopenProject and RenameProject write it (D-11). Appointments attach to a project in Phase 8, with the
 * link on the appointment, so this model holds no appointment column, only the appointments() relation (D-09).
 */
#[Fillable([
    'contact_id',
    'conversation_id',
    'name',
    'opened_by',
    'opened_by_user_id',
    'opened_at',
    'closed_at',
    'closed_by',
    'closed_by_user_id',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_by' => ProjectActor::class,
            'closed_by' => ProjectActor::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The customer this piece of work is for.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The conversation this project began in, when one was open at the time.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The signed-in user who opened it, when a user did.
     *
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * The signed-in user who closed it, when a user did.
     *
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Every appointment booked for this job. This is the scoped Appointment model, so a block never shows here (A3).
     * The link lives on the appointment (07 D-09).
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * A project is open until it is closed.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Add each project's number: the count of that contact's projects with an id up to this one's, so a contact's
     * first project is 1. N is the contact's own count in opening order and stays put because projects are never
     * deleted on their own. The card and the screens all number the same way, and the database counts it in the one
     * query, so no number is stored.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function withNumber(Builder $query): void
    {
        $query->select('projects.*')->addSelect([
            'number' => self::query()
                ->from('projects as numbered')
                ->selectRaw('count(*)')
                ->whereColumn('numbered.contact_id', 'projects.contact_id')
                ->whereColumn('numbered.id', '<=', 'projects.id'),
        ]);
    }

    /**
     * What a screen calls this project: its name when the owner gave one, else the business's word and the number,
     * such as "Job 2" (D-08).
     */
    public function label(ProjectWord $word, int $number): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? $name : "{$word->singular()} {$number}";
    }
}
