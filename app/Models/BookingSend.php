<?php

namespace App\Models;

use Database\Factories\BookingSendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One text to a customer about one appointment at one lead time: lead_minutes 0 is the confirmation, 1440 is a day ahead
 * (D-31). The database holds one row per appointment and lead time, so a lead time sends at most once. send_claimed_at,
 * waited_on_unread_at, sent_at, skipped_at, failed_at and failure are written only by the conditional updates in plan
 * 08-12, so they are not fillable (the OwnerAlert precedent, 06 D-06, D-07).
 */
#[Fillable([
    'appointment_id',
    'lead_minutes',
    'due_at',
    'ignores_window',
    'message_id',
])]
class BookingSend extends Model
{
    /** @use HasFactory<BookingSendFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_minutes' => 'integer',
            'due_at' => 'datetime',
            'ignores_window' => 'boolean',
            'send_claimed_at' => 'datetime',
            'waited_on_unread_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * The appointment the text is about.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * The text that was sent, once one was.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
