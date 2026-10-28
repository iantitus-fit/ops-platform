<?php

namespace App\Models;

use App\Enums\AlertReason;
use Database\Factories\OwnerAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One text to one person telling them something needs the owner. An alert ends in exactly one
 * of three ways: sent_at, skipped_at or failed_at (with the reason in failure). It never just
 * disappears (D-19). send_claimed_at is when a sender took the alert to text it; only a
 * conditional update in SendOwnerAlert writes it, so it is not fillable (06 D-06, D-07).
 */
#[Fillable([
    'conversation_id',
    'message_id',
    'user_id',
    'reason',
    'urgent',
    'due_at',
    'to_phone',
    'body',
    'sent_at',
    'skipped_at',
    'failed_at',
    'failure',
    'provider_message_id',
])]
class OwnerAlert extends Model
{
    /** @use HasFactory<OwnerAlertFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => AlertReason::class,
            'urgent' => 'boolean',
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
            'send_claimed_at' => 'datetime',
        ];
    }

    /**
     * The conversation the alert is about.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The message that triggered the alert, when one did.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The person the alert was meant for. Empty when no user had a phone on file.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
