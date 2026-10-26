<?php

namespace Tests\Rooms\PretendRoom;

use App\Icm\IcmFile;
use App\Rooms\RoomFileType;

/**
 * The pretend room's new kind of file: a short note with a title and a pinned flag. It reads nothing from disk; the
 * sync hands it a file it has already read.
 */
class PretendNoteType implements RoomFileType
{
    public function type(): string
    {
        return 'pretend_note';
    }

    public function rules(IcmFile $file): array
    {
        return [
            'title' => 'bail|required|string',
            'pinned' => 'bail|required|boolean:strict',
        ];
    }

    public function checks(IcmFile $file): array
    {
        if (($file->frontMatter['pinned'] ?? null) !== true || trim($file->body) !== '') {
            return [];
        }

        return [['file' => $file->path, 'field' => null, 'message' => 'a pinned note needs some words under its front matter']];
    }

    /**
     * The pretend room keeps any number of notes (RoomFileTypeTest syncs two).
     */
    public function singleFileLabel(): ?string
    {
        return null;
    }

    /**
     * No default path, since the pretend room keeps any number of notes (05 D-29).
     */
    public function singleFileDefaultPath(): ?string
    {
        return null;
    }
}
