<?php

namespace App\Models;

use Database\Factories\SafetyCheckOutageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One stretch of time Jev, the safety check, was down (D-50). It opens at the first failed call or health ping and
 * closes at the next answer; ended_at is null while the check is still down. The two alert columns record the
 * texts the owner got, so each outage sends at most one down alert and one back-up alert.
 */
#[Fillable([
    'started_at',
    'ended_at',
    'down_alerted_at',
    'up_alerted_at',
    'last_error',
])]
class SafetyCheckOutage extends Model
{
    /** @use HasFactory<SafetyCheckOutageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'down_alerted_at' => 'datetime',
            'up_alerted_at' => 'datetime',
        ];
    }
}
