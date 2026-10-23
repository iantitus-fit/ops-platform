<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Enums\OwnableKind;
use App\Enums\PlatformKind;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Collection;

/**
 * The checks on a connector file (connectors/*.md, type: connector) beyond the field rules the Validator already
 * applied: the kinds of record in owns and reads (D-22, D-23) and the old objects field (CONN-07). They work only from
 * the files the Loader already read, and every refusal sentence a connector file can earn is written here so the
 * wording can be changed in one place. Values the owner wrote go through PlainWords::oneLine().
 */
class ConnectorChecks
{
    public function __construct(private RoomRegistry $rooms, private RoomChecks $roomChecks) {}

    /**
     * What is wrong inside one connector file. system, direction and schedule are not looked at: they wait for CONN-01
     * (D-24).
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function fileErrors(IcmFile $file): array
    {
        $errors = [];

        if (! str_starts_with($file->path, 'connectors/')) {
            $errors[] = $this->error($file->path, 'type', 'type connector belongs only in connectors/, the folder for connector files');
        }

        if (array_key_exists('objects', $file->frontMatter)) {
            $errors[] = $this->error($file->path, 'objects', 'objects is replaced by owns and reads: list under owns the kinds this system is the truth for, and under reads the kinds it only copies');
        }

        array_push($errors, ...$this->ownsErrors($file), ...$this->readsErrors($file));

        foreach (array_unique(array_intersect($this->kinds($file, 'owns'), $this->kinds($file, 'reads'))) as $kind) {
            if (OwnableKind::tryFrom($kind) !== null) {
                $errors[] = $this->error($file->path, 'reads', PlainWords::oneLine($kind).' is in both owns and reads; a system that owns a kind already reads it, so list it only under owns');
            }
        }

        return $errors;
    }

    /**
     * What is wrong across the connector files of the whole folder, in path order so the later file is the one blamed
     * and a re-run gives the same lines: a slot no room has (D-25), a slot whose room is not here, a slot that already
     * has a connector file, and a kind two files both own (D-26). A connector file outside connectors/ is left out,
     * because fileErrors() already refused it and it must not be named as another file's rival.
     *
     * @param  Collection<int, IcmFile>  $files  Sorted by path.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function folderErrors(Collection $files): array
    {
        $connectors = $files->filter(fn (IcmFile $file): bool => $file->kind === IcmFileKind::Typed && $file->type === 'connector' && str_starts_with($file->path, 'connectors/'))->values();

        return [...$this->slotErrors($connectors, $files), ...$this->ownerErrors($connectors)];
    }

    /**
     * A connector file belongs to the room whose slot it names. The file must name a slot some room offers, that room
     * must be in the folder, and no earlier file may already fill the slot. A room with a slot and no connector file is
     * a valid empty room, so nothing here looks for a missing file.
     *
     * @param  Collection<int, IcmFile>  $connectors
     * @param  Collection<int, IcmFile>  $files
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function slotErrors(Collection $connectors, Collection $files): array
    {
        $errors = [];
        $filledBy = [];

        foreach ($connectors as $file) {
            $slot = $file->frontMatter['slot'] ?? null;

            if (! is_string($slot) || $slot === '') {
                continue;
            }

            $shown = PlainWords::oneLine($slot);
            $room = $this->rooms->roomWithSlot($slot);

            if ($room === null) {
                $slots = $this->rooms->slots();
                $errors[] = $this->error($file->path, 'slot', "this file fills the {$shown} slot, which no room has; ".($slots === [] ? 'this engine has no connector slots yet' : 'the slots are: '.implode(', ', $slots)));
            } elseif (! $this->roomChecks->isPresent($room, $files)) {
                $errors[] = $this->error($file->path, 'slot', "this file fills the {$shown} slot, but the {$room->label} room is not in this folder");
            } elseif (isset($filledBy[$slot])) {
                $errors[] = $this->error($file->path, 'slot', "the {$shown} slot is already filled by {$filledBy[$slot]}; a slot takes one connector file");
            } else {
                $filledBy[$slot] = $file->path;
            }
        }

        return $errors;
    }

    /**
     * One owner per kind of record (D-26): the first file in path order to own a kind keeps it, and each later file that
     * owns it too is refused. Only a file whose every owns entry is a kind a connector may own counts, so a file already
     * refused for its kinds never blames another.
     *
     * @param  Collection<int, IcmFile>  $connectors
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function ownerErrors(Collection $connectors): array
    {
        $errors = [];
        $owners = [];

        foreach ($connectors as $file) {
            $owns = $this->kinds($file, 'owns');

            if ($owns !== array_values(array_filter($owns, fn (string $kind): bool => OwnableKind::tryFrom($kind) !== null))) {
                continue;
            }

            foreach (array_unique($owns) as $kind) {
                if (isset($owners[$kind])) {
                    $errors[] = $this->error($file->path, 'owns', "{$kind} is owned by both {$owners[$kind]} and {$file->path}; pick one, the other can read it");
                } else {
                    $owners[$kind] = $file->path;
                }
            }
        }

        return $errors;
    }

    /**
     * Owns holds only the kinds a business may hand over: a platform kind and a word that is no kind each get their own
     * sentence, and a kind written twice is named once.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function ownsErrors(IcmFile $file): array
    {
        $errors = [];
        $reported = [];

        foreach ($this->kinds($file, 'owns') as $kind) {
            $shown = PlainWords::oneLine($kind);

            if (isset($reported[$kind])) {
                continue;
            }

            if (PlatformKind::tryFrom($kind) !== null) {
                $errors[] = $this->error($file->path, 'owns', "owns lists {$shown}, which the platform owns; a connector can read it but never own it");
                $reported[$kind] = true;
            } elseif (OwnableKind::tryFrom($kind) === null) {
                $errors[] = $this->error($file->path, 'owns', "owns has {$shown}, which is not a kind of record; the kinds are: ".implode(', ', OwnableKind::values()));
                $reported[$kind] = true;
            } elseif ($this->isRepeated($file, 'owns', $kind)) {
                $errors[] = $this->error($file->path, 'owns', "owns lists {$shown} twice");
                $reported[$kind] = true;
            }
        }

        return $errors;
    }

    /**
     * Reads may name any kind of record, the platform's included: a word that is no kind gets a sentence, and a kind
     * written twice is named once.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function readsErrors(IcmFile $file): array
    {
        $errors = [];
        $reported = [];

        foreach ($this->kinds($file, 'reads') as $kind) {
            $shown = PlainWords::oneLine($kind);

            if (isset($reported[$kind])) {
                continue;
            }

            if (OwnableKind::tryFrom($kind) === null && PlatformKind::tryFrom($kind) === null) {
                $errors[] = $this->error($file->path, 'reads', "reads has {$shown}, which is not a kind of record; the kinds are: ".implode(', ', [...OwnableKind::values(), ...PlatformKind::values()]));
                $reported[$kind] = true;
            } elseif ($this->isRepeated($file, 'reads', $kind)) {
                $errors[] = $this->error($file->path, 'reads', "reads lists {$shown} twice");
                $reported[$kind] = true;
            }
        }

        return $errors;
    }

    /**
     * The text entries of a list field. Anything that is not text is left out because the field's rule string already
     * reports it.
     *
     * @return list<string>
     */
    private function kinds(IcmFile $file, string $field): array
    {
        $entries = $file->frontMatter[$field] ?? null;

        return is_array($entries) ? array_values(array_filter($entries, is_string(...))) : [];
    }

    private function isRepeated(IcmFile $file, string $field, string $kind): bool
    {
        return count(array_keys($this->kinds($file, $field), $kind, true)) > 1;
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(string $file, ?string $field, string $message): array
    {
        return ['file' => $file, 'field' => $field, 'message' => $message];
    }
}
