<?php

namespace App\Models;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentKind;
use App\Enums\AppointmentState;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's appointment for one job: a start and an end, both UTC (D-06), with the service copied from the folder at
 * booking (D-42). It reads the one appointments table, but only rows of kind appointment: a global scope adds that
 * where clause to every query this model makes, so a block never reaches the card, the timeline or the contact screen
 * (A3). The kind is not fillable; creating an appointment sets it. Only the named actions write this table (D-15), and
 * who acted is stored on the row (D-15).
 */
#[Fillable([
    'contact_id',
    'project_id',
    'booking_link_id',
    'service_slug',
    'service_name',
    'starts_at',
    'ends_at',
    'state',
    'booked_by',
    'booked_by_user_id',
    'booked_at',
    'cancelled_by',
    'cancelled_by_user_id',
    'cancelled_at',
    'done_by_user_id',
    'done_at',
    'note',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $table = 'appointments';

    /**
     * Limit every query to appointments and mark every new row as one.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('kind', function (Builder $query): void {
            $query->where('appointments.kind', AppointmentKind::Appointment->value);
        });

        static::creating(function (Appointment $appointment): void {
            $appointment->kind = AppointmentKind::Appointment;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'state' => AppointmentState::class,
            'booked_by' => AppointmentActor::class,
            'cancelled_by' => AppointmentActor::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    /**
     * The customer the appointment is for.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The job the appointment is part of (07 D-09: the link lives on the appointment).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The link the customer booked through, when they booked through one.
     *
     * @return BelongsTo<BookingLink, $this>
     */
    public function bookingLink(): BelongsTo
    {
        return $this->belongsTo(BookingLink::class);
    }

    /**
     * The signed-in user who booked it, when a user did.
     *
     * @return BelongsTo<User, $this>
     */
    public function bookedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    /**
     * The confirmation and reminder texts for this appointment, one per lead time (D-31).
     *
     * @return HasMany<BookingSend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(BookingSend::class);
    }

    /**
     * Only appointments that still hold time (D-14).
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function booked(Builder $query): void
    {
        $query->where('appointments.state', AppointmentState::Booked->value);
    }

    /**
     * Only appointments that start after this moment.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function ahead(Builder $query): void
    {
        $query->where('appointments.starts_at', '>', now());
    }

    /**
     * Whether the appointment still holds time.
     */
    public function isBooked(): bool
    {
        return $this->state === AppointmentState::Booked;
    }
}
