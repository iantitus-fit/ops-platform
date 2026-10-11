<?php

namespace App\Console\Commands;

use App\Icm\Exceptions\IcmFolderMissing;
use App\Records\RecordsWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('records:rebuild')]
#[Description('Regenerate every file in records/ from the database')]
class RecordsRebuild extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordsWriter $recordsWriter): int
    {
        try {
            $count = $recordsWriter->rebuild();
        } catch (IcmFolderMissing $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $cards = Str::plural('customer card', $count);

        $this->info("Rebuilt {$count} {$cards}, customers/INDEX.md, open-leads.md and CLAUDE.md");

        return self::SUCCESS;
    }
}
