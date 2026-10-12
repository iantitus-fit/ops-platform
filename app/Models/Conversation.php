<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\PausedReason;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['contact_id', 'status', 'last_message_at', 'closed_at', 'paused_at', 'paused_reason', 'paused_by'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'paused_at' => 'datetime',
            'paused_reason' => PausedReason::class,
        ];
    }

    /**
     * The customer this thread belongs to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The owner who took over or handed back last, when a person did it.
     *
     * @return BelongsTo<User, $this>
     */
    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * Whether the assistant has stepped back from this conversation (D-23). The pause belongs to the
     * conversation, not the customer: a new conversation after a close starts active.
     */
    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * The messages in arrival order. Timestamps have one-second resolution, so the id
     * breaks ties between messages recorded in the same second.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Every intake answer given in this thread, corrections included, oldest first.
     *
     * @return HasMany<IntakeAnswer, $this>
     */
    public function intakeAnswers(): HasMany
    {
        return $this->hasMany(IntakeAnswer::class)->orderBy('id');
    }
}
