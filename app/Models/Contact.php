<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'phone_e164',
    'email',
    'status',
    'lead_source_key',
    'acquisition_cost_cents',
    'last_activity_at',
    'sms_opted_out_at',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'acquisition_cost_cents' => 'integer',
            'last_activity_at' => 'datetime',
            'sms_opted_out_at' => 'datetime',
        ];
    }

    /**
     * Whether this customer texted STOP and so must not be texted by anyone (D-40).
     */
    public function isOptedOut(): bool
    {
        return $this->sms_opted_out_at !== null;
    }

    /**
     * Every conversation this contact has had, open or closed.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * The one conversation currently open for this contact, if any.
     *
     * @return HasOne<Conversation, $this>
     */
    public function openConversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->where('status', ConversationStatus::Open);
    }

    /**
     * The timeline entries for this contact.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * The timeline entries, newest first. Timestamps have one-second resolution, so the id
     * breaks ties between entries from the same second.
     *
     * @return HasMany<Activity, $this>
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(Activity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
