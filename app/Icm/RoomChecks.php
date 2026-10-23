<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        $errors = $this->connectorErrors($file);

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
     * whose folder has files but no contract, a rooms/<name>/ folder for a room the engine does not have, and the
     * ownership of every file: what a contract may claim, a file two contracts claim, a file nobody claims, a file whose
     * room is not here, and a room's own kind of file outside its folder.
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

        array_push($errors, ...$this->ownershipErrors($files, $folders));

        return $errors;
    }

    /**
     * Every file has one owner (ROOM-01), and a room's own kind of file sits in the room's folder (D-04). Everything is
     * worked out from the files already read, in path order, so a re-run of the same folder gives the same lines.
     *
     * @param  Collection<int, IcmFile>  $files
     * @param  Collection<int, string>  $folders  The room folder names that hold at least one loaded file.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function ownershipErrors(Collection $files, Collection $folders): array
    {
        $present = $this->presentRooms($files);
        $errors = [];
        $misplaced = [];

        foreach ($files as $file) {
            $error = $file->kind === IcmFileKind::Typed ? $this->placementError($file, $folders) : null;

            if ($error !== null) {
                $errors[] = $error;
                $misplaced[$file->path] = true;
            }
        }

        [$claimErrors, $claimants] = $this->claims($files, $present);

        array_push($errors, ...$claimErrors, ...$this->doubleClaimErrors($files, $present, $claimants));

        foreach ($files as $file) {
            if ($file->kind !== IcmFileKind::Typed || in_array($file->type, ['room', 'connector'], true) || isset($misplaced[$file->path]) || isset($claimants[$file->path])) {
                continue;
            }

            $error = $this->unclaimedOrAbsentError($file, $present);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * The rooms that are here: registered in code, with their contract loaded as a room file. Contract field errors do
     * not make a room absent. In key order.
     *
     * @param  Collection<int, IcmFile>  $files
     * @return list<Room>
     */
    private function presentRooms(Collection $files): array
    {
        return array_values(array_filter(
            $this->rooms->all(),
            fn (Room $room): bool => $this->isPresent($room, $files),
        ));
    }

    /**
     * Whether a room is here: its contract loaded as a room file. ConnectorChecks asks the same question, so the answer
     * is worked out in one place.
     *
     * @param  Collection<int, IcmFile>  $files
     */
    public function isPresent(Room $room, Collection $files): bool
    {
        return $this->contractOf($room, $files) !== null;
    }

    /**
     * @param  Collection<int, IcmFile>  $files
     */
    private function contractOf(Room $room, Collection $files): ?IcmFile
    {
        return $files->first(fn (IcmFile $file): bool => $file->path === "rooms/{$room->key}/CONTEXT.md" && $file->kind === IcmFileKind::Typed && $file->type === 'room');
    }

    /**
     * A room's own kind of file must sit under rooms/<room>/, where <room> is the room whose code defines the type
     * (D-04). Only types a room registered are checked, so today's kinds of file stay where they are. One line either
     * way: the move-it line while the room's folder is in the business folder, and a line offering both ways out when
     * it is not (D-04 with D-10).
     *
     * @param  Collection<int, string>  $folders
     * @return array{file: string, field: ?string, message: string}|null
     */
    private function placementError(IcmFile $file, Collection $folders): ?array
    {
        $room = $this->roomDefiningType((string) $file->type);

        if ($room === null || str_starts_with($file->path, "rooms/{$room->key}/")) {
            return null;
        }

        return $this->error($file->path, null, $folders->contains($room->key)
            ? "this is a {$room->label} file, which belongs in rooms/{$room->key}/; move it there"
            : "this is a {$room->label} file, which belongs in rooms/{$room->key}/; this business doesn't use the {$room->label} room, so add rooms/{$room->key}/ and move it there, or remove the file");
    }

    /**
     * The room whose code defines a file type, as opposed to one that only handles it.
     */
    private function roomDefiningType(string $type): ?Room
    {
        foreach ($this->rooms->all() as $room) {
            foreach ($room->fileTypes as $fileType) {
                if ($fileType->type() === $type) {
                    return $room;
                }
            }
        }

        return null;
    }

    /**
     * What each present contract claims through owns. A claim of a file the room handles is kept; any other claim is an
     * error on the contract, field owns (D-17, D-11, D-25). Room contracts and files the Loader could not use are never
     * claimed, and a router inside the claiming room's own folder is the room's own.
     *
     * @param  Collection<int, IcmFile>  $files
     * @param  list<Room>  $present
     * @return array{0: list<array{file: string, field: ?string, message: string}>, 1: array<string, list<string>>} The errors, and the contract paths that validly claim each file path.
     */
    private function claims(Collection $files, array $present): array
    {
        $claimable = $files->filter(fn (IcmFile $file): bool => $file->kind !== IcmFileKind::Unrecognized && $file->type !== 'room')->values();
        $errors = [];
        $claimants = [];

        foreach ($present as $room) {
            $contract = $this->contractOf($room, $files);

            foreach ($this->ownsEntries($contract) as $entry) {
                foreach ($claimable->filter(fn (IcmFile $file): bool => Str::is($entry, $file->path)) as $file) {
                    if ($file->kind === IcmFileKind::Router && str_starts_with($file->path, "rooms/{$room->key}/")) {
                        continue;
                    }

                    $problem = $this->claimProblem($file, $room);

                    if ($problem === null) {
                        $claimants[$file->path] = array_values(array_unique([...($claimants[$file->path] ?? []), $contract->path]));
                    } else {
                        $errors["{$contract->path}\0{$problem}"] = $this->error($contract->path, 'owns', $problem);
                    }
                }
            }
        }

        return [array_values($errors), $claimants];
    }

    /**
     * Why a room may not claim this file, or null when the claim is fine.
     */
    private function claimProblem(IcmFile $file, Room $room): ?string
    {
        if ($file->kind === IcmFileKind::Router || $file->type === 'walk_test') {
            return "{$file->path} belongs to the house (routers and walk tests); {$room->label} can read it";
        }

        if ($file->type === 'connector') {
            return "{$file->path} is a connector file; a room owns its connector through its slot, so leave it out of owns";
        }

        if (in_array($file->type, $room->handledTypes(), true)) {
            return null;
        }

        $handler = $this->rooms->roomsHandling((string) $file->type)[0] ?? null;

        return $handler === null
            ? "{$file->path} is a {$file->type} file, which no room handles yet; {$room->label} can read it"
            : "{$file->path} is a {$file->type} file, which the {$handler->label} room handles; {$room->label} can read it";
    }

    /**
     * A file two contracts both validly claim is refused on the later contract in path order (D-15), and so is the same
     * owns entry written in two contracts when no file matches it yet, so the clash shows before the file exists.
     *
     * @param  Collection<int, IcmFile>  $files
     * @param  list<Room>  $present
     * @param  array<string, list<string>>  $claimants
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function doubleClaimErrors(Collection $files, array $present, array $claimants): array
    {
        $errors = [];

        foreach ($files as $file) {
            foreach (array_slice($claimants[$file->path] ?? [], 1) as $later) {
                $errors[] = $this->bothClaimError($later, $claimants[$file->path][0], $file->path);
            }
        }

        $contractsByEntry = [];

        foreach ($present as $room) {
            $contract = $this->contractOf($room, $files);

            foreach ($this->ownsEntries($contract) as $entry) {
                $contractsByEntry[$entry] = array_values(array_unique([...($contractsByEntry[$entry] ?? []), $contract->path]));
            }
        }

        foreach ($contractsByEntry as $entry => $contracts) {
            $entry = (string) $entry;

            if (count($contracts) < 2 || $files->contains(fn (IcmFile $file): bool => $file->type !== 'room' && Str::is($entry, $file->path))) {
                continue;
            }

            foreach (array_slice($contracts, 1) as $later) {
                $errors[] = $this->bothClaimError($later, $contracts[0], $entry);
            }
        }

        return $errors;
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function bothClaimError(string $later, string $first, string $claimed): array
    {
        return $this->error($later, 'owns', "{$claimed} is claimed by both {$first} and {$later}; pick one, the other can read it");
    }

    /**
     * The one line for a typed file nobody validly claims: a present room handles its type but no contract lists it
     * (ROOM-01), or only rooms this business does not have handle it (D-10). A type no registered room handles passes
     * (D-15), and a file inside a registered room's folder whose contract is missing is left to the missing-contract line.
     *
     * @param  list<Room>  $present
     * @return array{file: string, field: ?string, message: string}|null
     */
    private function unclaimedOrAbsentError(IcmFile $file, array $present): ?array
    {
        $handlers = $this->rooms->roomsHandling((string) $file->type);

        if ($handlers === []) {
            return null;
        }

        $presentKeys = array_map(fn (Room $room): string => $room->key, $present);
        $here = array_values(array_filter($handlers, fn (Room $room): bool => in_array($room->key, $presentKeys, true)));

        if ($here !== []) {
            return $this->error($file->path, null, "no room lists this {$file->type} file under owns; the {$here[0]->label} room handles it, so add it to rooms/{$here[0]->key}/CONTEXT.md");
        }

        $folder = $this->roomKeyOf($file->path);

        if ($folder !== null && $this->rooms->has($folder)) {
            return null;
        }

        return $this->error($file->path, null, "this file belongs to the {$handlers[0]->label} room, which this business doesn't use; add rooms/{$handlers[0]->key}/ or remove the file");
    }

    /**
     * The usable entries of a contract's owns list: text, not empty.
     *
     * @return list<string>
     */
    private function ownsEntries(IcmFile $contract): array
    {
        $owns = $contract->frontMatter['owns'] ?? null;

        return is_array($owns) ? array_values(array_filter($owns, fn (mixed $entry): bool => is_string($entry) && trim($entry) !== '')) : [];
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
     * The contract's connector must be the slot its room's code offers, or none for a room with no slot (D-25). A
     * room with a slot and no connector file is fine; nothing here looks for connector files.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function connectorErrors(IcmFile $file): array
    {
        $room = $this->rooms->get((string) $this->roomKeyOf($file->path));
        $connector = $file->frontMatter['connector'] ?? null;

        if ($room === null || ! is_string($connector) || $connector === ($room->slot ?? 'none')) {
            return [];
        }

        $wrote = PlainWords::value($connector);

        return [$this->error($file->path, 'connector', $room->slot === null
            ? "connector must be none, because the {$room->label} room has no connector slot (you wrote {$wrote})"
            : "connector must be {$room->slot}, the {$room->label} room's slot (you wrote {$wrote})")];
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
