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

    /**
     * The contract's lists of paths, each entry checked on its own so the order of a list never changes a result.
     *
     * @var list<string>
     */
    private const PATH_LISTS = ['owns', 'reads', 'writes_records'];

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

        $errors = [];

        foreach (self::PATH_LISTS as $field) {
            $entries = $file->frontMatter[$field] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach (array_values($entries) as $position => $entry) {
                if (is_string($entry)) {
                    array_push($errors, ...$this->pathErrors($file->path, $field, $position + 1, $entry));
                }
            }
        }

        return $errors;
    }

    /**
     * What is wrong across the whole folder: a file at a room's contract path that is not a contract, a registered room
     * whose folder has files but no contract, and a rooms/<name>/ folder for a room the engine does not have.
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

        $folders = $files->map(fn (IcmFile $file): ?string => $this->roomKeyOf($file->path))->filter()->unique()->sort()->values();

        foreach ($this->rooms->all() as $room) {
            if ($folders->contains($room->key) && ! $files->contains(fn (IcmFile $file): bool => $file->path === "rooms/{$room->key}/CONTEXT.md")) {
                $errors[] = $this->error("rooms/{$room->key}/CONTEXT.md", null, "the {$room->label} room's folder has files but no CONTEXT.md; add the room's contract with type: room");
            }
        }

        foreach ($folders->reject(fn (string $folder): bool => $this->rooms->has($folder)) as $folder) {
            $name = PlainWords::oneLine($folder);
            $existing = $this->rooms->keys();
            $errors[] = $this->error("rooms/{$folder}/", null, $existing === []
                ? "this engine has no {$name} room, and no rooms yet"
                : "this engine has no {$name} room; the rooms are: ".implode(', ', $existing));
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
     * What is wrong with one entry of owns, reads or writes_records: empty, outside the folder, or (for writes_records)
     * not under records/. An entry earns at most one error.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function pathErrors(string $file, string $field, int $item, string $entry): array
    {
        $label = "{$field} item {$item}";

        if (trim($entry) === '') {
            return [$this->error($file, $field, "{$label} is empty; write a path inside this folder")];
        }

        if (str_starts_with($entry, '/') || str_starts_with($entry, '\\') || in_array('..', preg_split('#[/\\\\]#', $entry) ?: [], true)) {
            return [$this->error($file, $field, "{$label} must be a path inside this folder, with no .. and no leading / (you wrote ".PlainWords::value($entry).')')];
        }

        if ($field === 'writes_records' && ! (str_starts_with($entry, 'records/') && strlen($entry) > strlen('records/'))) {
            return [$this->error($file, $field, "{$label} must be a path under records/, such as records/today.md (you wrote ".PlainWords::value($entry).')')];
        }

        return [];
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(string $file, ?string $field, string $message): array
    {
        return ['file' => $file, 'field' => $field, 'message' => $message];
    }
}
