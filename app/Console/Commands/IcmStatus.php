<?php

namespace App\Console\Commands;

use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('icm:status')]
#[Description('Show the live business folder version')]
class IcmStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ActiveConfig $activeConfig): int
    {
        try {
            $version = $activeConfig->version();
            $timezone = $activeConfig->timezone();
        } catch (NoActiveVersion) {
            $this->error('No version is live yet. Run php artisan icm:sync.');

            return self::FAILURE;
        }

        $liveSince = $version->activated_at->clone()->setTimezone($timezone)->format('Y-m-d H:i');

        $this->line("Active version: {$version->label()}");
        $this->line("Files: {$version->file_count}");
        $this->line("Live since: {$liveSince} ({$timezone})");

        return self::SUCCESS;
    }
}
