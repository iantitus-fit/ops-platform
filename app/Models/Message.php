<?php

namespace App\Models;

use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'channel',
    'direction',
    'author',
    'body',
    'status',
    'provider',
    'provider_message_id',
    'meta',
    'kind',
    'label',
    'gate_outcome',
    'gate_reason',
    'gate',
    'dispatched_at',
    'handled_at',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'direction' => MessageDirection::class,
            'author' => MessageAuthor::class,
            'status' => MessageStatus::class,
            'meta' => 'array',
            'kind' => MessageKind::class,
            'label' => ReplyLabel::class,
            'gate_outcome' => GateOutcome::class,
            'gate' => 'array',
            'dispatched_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * The thread this message belongs to.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
