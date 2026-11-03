<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable([
    'name',
    'phone_e164',
    'email',
    'status',
    'lead_source_key',
    'acquisition_cost_cents',
    'last_activity_at',
    'sms_opted_out_at',
    'automatic_texts_on_at',
    'automatic_texts_on_by_user_id',
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
            'automatic_texts_on_at' => 'datetime',
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
     * Whether this customer has ever texted the business (D-20). It is derived and nothing is stored: a customer text
     * message over SMS in any of this contact's conversations counts, unless that very message is the one that opted
     * the contact out, so a first text that is STOP never counts and a later START does. A web form, a call or an
     * email is not a text.
     */
    public function hasTextedBusiness(): bool
    {
        return Message::query()
            ->whereIn('conversation_id', $this->conversations()->select('conversations.id'))
            ->where('direction', MessageDirection::In->value)
            ->where('channel', MessageChannel::Sms->value)
            ->where('author', MessageAuthor::Customer->value)
            ->whereNotExists(function (QueryBuilder $optOut): void {
                $optOut->selectRaw('1')
                    ->from('activities')
                    ->where('activities.contact_id', $this->getKey())
                    ->where('activities.type', ActivityType::OptedOut->value)
                    ->where('activities.subject_type', (new Message)->getMorphClass())
                    ->whereColumn('activities.subject_id', 'messages.id');
            })
            ->exists();
    }

    /**
     * Whether an automatic text may go to this customer: they texted the business, or the owner turned automatic texts
     * on for them (D-21). The switch is the only stored state (who and when); "has texted" is derived each time.
     */
    public function mayGetAutomaticTexts(): bool
    {
        return $this->automatic_texts_on_at !== null || $this->hasTextedBusiness();
    }

    /**
     * The signed-in user who turned automatic texts on for this contact, when one did (D-20).
     *
     * @return BelongsTo<User, $this>
     */
    public function automaticTextsTurnedOnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'automatic_texts_on_by_user_id');
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
     * Every project this contact has, open or closed.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Every appointment this contact has. This is the scoped Appointment model, so a block never shows here (A3). The
     * link between a contact and an appointment lives on the appointment (07 D-09).
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
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
     * The timeline entries that may be shown, newest first: entries of a room that is not in the live version are
     * left out (Activity::visibleOnTimeline()). Timestamps have one-second resolution, so the id breaks ties between
     * entries from the same second.
     *
     * @return HasMany<Activity, $this>
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(Activity::class)->visibleOnTimeline()->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
