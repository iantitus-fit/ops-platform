<?php

namespace App\Icm;

use App\Enums\OwnableKind;
use App\Enums\PlatformKind;

/**
 * The checks on a connector file (connectors/*.md, type: connector) beyond the field rules the Validator already
 * applied: the kinds of record in owns and reads (D-22, D-23) and the old objects field (CONN-07). They work only from
 * the files the Loader already read, and every refusal sentence a connector file can earn is written here so the
 * wording can be changed in one place. Values the owner wrote go through PlainWords::oneLine().
 */
class ConnectorChecks
{
    /**
     * What is wrong inside one connector file. system, direction and schedule are not looked at: they wait for CONN-01
     * (D-24).
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function fileErrors(IcmFile $file): array
    {
        $errors = [];

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
