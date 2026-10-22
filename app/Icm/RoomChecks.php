<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Collection;

/**
 * The checks on a room's contract (rooms/<room>/CONTEXT.md, type: room) and on the rooms/ folder as a whole. They work
 * only from the files the Loader already read, never from disk, and every refusal sentence a room can earn is written
 * here so the wording can be changed in one place. The Validator adds what these return to its report, so a refusal
 * takes the same path as any other: the folder is stored as rejected and the previous version stays live.
 */
class RoomChecks
{
    /**
     * The path of a room's contract: a CONTEXT.md directly inside rooms/<room>/.
     */
    private const CONTRACT_PATH = '#^rooms/[^/]+/CONTEXT\.md$#';

    public function __construct(private RoomRegistry $rooms) {}

    /**
     * What is wrong with one file whose type is room, beyond the field rules the Validator already applied.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function contractErrors(IcmFile $file): array
    {
        if (preg_match(self::CONTRACT_PATH, $file->path) !== 1) {
            return [$this->error($file->path, 'type', 'type room belongs only in rooms/<name>/CONTEXT.md, the contract at the top of a room folder')];
        }

        return [];
    }

    /**
     * What is wrong across the whole folder: a file at a room's contract path that is not a contract.
     *
     * @param  Collection<int, IcmFile>  $files
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function errors(Collection $files): array
    {
        $errors = [];

        foreach ($files as $file) {
            if ($file->kind !== IcmFileKind::Typed || $file->type === 'room' || preg_match(self::CONTRACT_PATH, $file->path) !== 1) {
                continue;
            }

            $key = (string) $this->roomKeyOf($file->path);
            $errors[] = $this->error($file->path, 'type', "type must be room, because this file is the {$key} room's contract (you wrote ".PlainWords::oneLine((string) $file->type).')');
        }

        return $errors;
    }

    /**
     * The room folder a path sits in: "pretend" for rooms/pretend/CONTEXT.md or rooms/pretend/notes/a.md, and null for
     * a path outside rooms/<name>/.
     */
    public function roomKeyOf(string $path): ?string
    {
        return preg_match('#^rooms/([^/]+)/#', $path, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(string $file, ?string $field, string $message): array
    {
        return ['file' => $file, 'field' => $field, 'message' => $message];
    }
}
