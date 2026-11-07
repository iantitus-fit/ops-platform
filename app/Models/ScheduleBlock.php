<?php

namespace App\Models;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentKind;
use App\Enums\AppointmentState;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A block of time the owner holds for themselves, such as the truck being in the shop. It reads the same appointments
 * table as Appointment but only rows of kind block (A3). A block has no contact, project, service or booking link, and
 * the database refuses a block that has one. It is booked while it holds time and cancelled once removed, so the row
 * keeps who removed it (D-15). Only the named actions write this table (D-15).
 */
#[Fillable([
    'starts_at',
    'ends_at',
    'state',
    'booked_by',
    'booked_by_user_id',
    'booked_at',
    'cancelled_by',
    'cancelled_by_user_id',
    'cancelled_at',
    'note',
])]
class ScheduleBlock extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use HasFactory;

    protected $table = 'appointments';

    /**
     * Limit every query to blocks and mark every new row as one.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('kind', function (Builder $query): void {
            $query->where('appointments.kind', AppointmentKind::Block->value);
        });

        static::creating(function (ScheduleBlock $block): void {
            $block->kind = AppointmentKind::Block;
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
        ];
    }

    /**
     * The signed-in user who set the block.
     *
     * @return BelongsTo<User, $this>
     */
    public function bookedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    /**
     * Only blocks that still hold time (D-14).
     *
     * @param  Builder<ScheduleBlock>  $query
     */
    #[Scope]
    protected function booked(Builder $query): void
    {
        $query->where('appointments.state', AppointmentState::Booked->value);
    }

    /**
     * Whether the block still holds time.
     */
    public function isBooked(): bool
    {
        return $this->state === AppointmentState::Booked;
    }
}
