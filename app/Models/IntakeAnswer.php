<?php

namespace App\Models;

use Database\Factories\IntakeAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'message_id',
    'icm_version_id',
    'service_slug',
    'question_key',
    'option',
    'raw_text',
])]
class IntakeAnswer extends Model
{
    /** @use HasFactory<IntakeAnswerFactory> */
    use HasFactory;

    /**
     * The thread the customer gave this answer in.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The customer's text the answer came from.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The folder version that asked the question when the answer was recorded.
     *
     * @return BelongsTo<IcmVersion, $this>
     */
    public function icmVersion(): BelongsTo
    {
        return $this->belongsTo(IcmVersion::class);
    }
}
