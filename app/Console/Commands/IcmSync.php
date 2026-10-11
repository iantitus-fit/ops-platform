<?php

namespace App\Console\Commands;

use App\Enums\SyncOutcome;
use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\FolderSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('icm:sync')]
#[Description('Read the business folder, validate it and make it the live version')]
class IcmSync extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FolderSync $folderSync): int
    {
        try {
            $result = $folderSync->run();
        } catch (IcmFolderMissing $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result->notices as $notice) {
            $this->warn($notice);
        }

        if ($result->outcome === SyncOutcome::Busy) {
            $this->error($result->message);

            return self::FAILURE;
        }

        if ($result->outcome === SyncOutcome::Rejected) {
            $this->error($result->message);

            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
