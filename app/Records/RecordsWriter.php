<?php

namespace App\Records;

use App\Models\Contact;
use Illuminate\Support\Facades\File;

/**
 * Writes the records/ folder of the business folder.
 *
 * Every write goes through File::replace, which writes a temporary file and renames it into
 * place, so a reader never sees half a file. The class never reads a card back: the database is
 * the source of truth and the files follow it.
 */
class RecordsWriter
{
    public function __construct(private readonly CustomerCard $customerCard) {}

    /**
     * The records folder inside the business folder.
     */
    public function root(): string
    {
        return config('icm.path').'/records';
    }

    /**
     * Render the contact's card and write it, then remove any older card of the same contact
     * under a different filename, such as the one from before a rename.
     *
     * @return string the path written
     */
    public function writeCard(Contact $contact): string
    {
        $directory = $this->customersDirectory();
        $filename = $this->customerCard->filename($contact);

        File::ensureDirectoryExists($directory);
        File::replace("{$directory}/{$filename}", $this->customerCard->render($contact));

        $this->deleteCardFiles($contact->id, keep: $filename);

        return "{$directory}/{$filename}";
    }

    /**
     * Remove every card file of a contact.
     */
    public function removeCard(int $contactId): void
    {
        $this->deleteCardFiles($contactId);
    }

    private function customersDirectory(): string
    {
        return $this->root().'/customers';
    }

    /**
     * Delete the card files whose name ends with -{id}.md, except the one to keep. The match is
     * on that exact ending, so another contact's card and INDEX.md are never touched.
     */
    private function deleteCardFiles(int $contactId, ?string $keep = null): void
    {
        $directory = $this->customersDirectory();

        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            $name = $file->getFilename();

            if ($name !== $keep && str_ends_with($name, "-{$contactId}.md")) {
                File::delete($file->getPathname());
            }
        }
    }
}
