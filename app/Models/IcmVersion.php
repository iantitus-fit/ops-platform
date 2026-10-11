<?php

namespace App\Models;

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use Database\Factories\IcmVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'fingerprint',
    'git_sha',
    'compiled',
    'errors',
    'notices',
    'file_count',
    'status',
    'activated_at',
    'last_checked_at',
    'last_result',
])]
class IcmVersion extends Model
{
    /** @use HasFactory<IcmVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'compiled' => 'array',
            'errors' => 'array',
            'notices' => 'array',
            'file_count' => 'integer',
            'status' => VersionStatus::class,
            'last_result' => SyncOutcome::class,
            'activated_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * The owner-facing name of this version, such as "v3". It is the row id.
     */
    public function label(): string
    {
        return 'v'.$this->id;
    }
}
