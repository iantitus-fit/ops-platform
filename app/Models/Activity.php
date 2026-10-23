<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'contact_id',
    'type',
    'summary',
    'subject_type',
    'subject_id',
    'lead_source_key',
    'cost_cents',
    'occurred_at',
    'room',
    'entry',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'cost_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The customer whose timeline this entry is on.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The record this entry is about, such as the message that arrived.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Keep the rows a timeline may show: those with no room, which is every row v1.0 writes, and those of a room that
     * is in the live version. Rows of a room that is not in the live version are hidden, never deleted, and show
     * again when the room's folder comes back (Ian, 2026-10-22). Every timeline reader applies this one scope.
     *
     * @param  Builder<Activity>  $query
     */
    #[Scope]
    protected function visibleOnTimeline(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('room')->orWhereIn('room', app(ActiveConfig::class)->roomKeys());
        });
    }
}
