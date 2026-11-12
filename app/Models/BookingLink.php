<?php

namespace App\Models;

use Database\Factories\BookingLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A booking link texted to one customer for one service. Only the sha256 hash of the token is stored and the column is
 * hidden from arrays, so a copy of the database or a logged model holds no working link (D-12). One link carries at
 * most one appointment ever (D-13), and expired, superseded and revoked links stay as rows so a stale link can be
 * told apart from one that never existed.
 */
#[Fillable([
    'contact_id',
    'conversation_id',
    'service_slug',
    'message_id',
    'token_hash',
    'expires_at',
    'superseded_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class BookingLink extends Model
{
    /** @use HasFactory<BookingLinkFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * The customer the link was sent to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The conversation the link was sent in.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The text that carried the link.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The appointment booked through this link, if any (D-13).
     *
     * @return HasOne<Appointment, $this>
     */
    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }
}
