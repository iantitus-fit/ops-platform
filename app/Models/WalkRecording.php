<?php

namespace App\Models;

use Database\Factories\WalkRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What one live run captured for one walk: the front desk model's replies and Jev's raw scores, in the shape WalkTape
 * reads (10 D-01, D-03). The gate reads only the as_built variant; the others hold wordings Ian measures, never ones the
 * gate replays (D-18). A recording never holds a verdict, so a change to a bar in config/gate.php applies at the next sync.
 */
#[Fillable([
    'walk_path',
    'script_hash',
    'variant',
    'fingerprint',
    'payload',
    'recorded_at',
])]
class WalkRecording extends Model
{
    /** @use HasFactory<WalkRecordingFactory> */
    use HasFactory;

    /**
     * The variant the gate replays.
     */
    public const string AS_BUILT = 'as_built';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
