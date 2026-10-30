<?php

namespace App\Models;

use App\Enums\ProjectActor;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of work for one contact. The table is the platform's own, not a room's (04 D-23, D-10). Only OpenProject,
 * CloseProject, ReopenProject and RenameProject write it (D-11). Appointments attach to a project in Phase 8, with the
 * link on the appointment, so this model holds no appointment (D-09).
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
     * A project is open until it is closed.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
