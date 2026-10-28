<?php

namespace App\Models;

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use Carbon\CarbonImmutable;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'claim_token',
    'failure_reported_at',
    'resolution',
    'resolved_by',
    'resolved_at',
    'original_body',
    'sent_at',
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
            'failure_reported_at' => 'datetime',
            'resolution' => DraftResolution::class,
            'resolved_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * When this message happened, for the thread order and every time shown: for a text that went out, when it was
     * sent; otherwise when it was recorded (a customer's text arriving, a draft being written, a text still queued
     * or one that failed). A copy, so changing its time zone for display never changes the model.
     */
    public function happenedAt(): CarbonImmutable
    {
        return ($this->sent_at ?? $this->created_at)->toImmutable();
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

    /**
     * The person who approved, edited or discarded this draft. Empty for a draft a newer one replaced.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * The texts to the owner that this message caused, oldest first.
     *
     * @return HasMany<OwnerAlert, $this>
     */
    public function ownerAlerts(): HasMany
    {
        return $this->hasMany(OwnerAlert::class)->orderBy('id');
    }

    /**
     * Why nobody was told about this message, or null when somebody was or may yet be (D-19). It answers only when the
     * message has alerts and every one of them failed: an alert still waiting to go, sent, or skipped on purpose is not
     * a gap. An empty string means the last failure left no words.
     */
    public function alertFailure(): ?string
    {
        $alerts = $this->ownerAlerts;

        if ($alerts->isEmpty() || $alerts->contains(fn (OwnerAlert $alert): bool => $alert->failed_at === null)) {
            return null;
        }

        return rtrim(trim((string) $alerts->last()->failure), '.');
    }
}
