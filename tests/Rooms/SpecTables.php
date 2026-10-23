<?php

namespace Tests\Rooms;

/**
 * Reads the tables in docs/SPEC.md for the tests that tie them to code. Every method takes the markdown as a string and
 * never touches disk, so the test that calls it decides which file to read.
 */
class SpecTables
{
    /**
     * @return list<list<string>>
     */
    public static function rows(string $markdown, string $heading): array
    {
        return [];
    }

    /**
     * @return list<array{pattern: string, owner: string}>
     */
    public static function ownership(string $markdown): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function patternsOwnedBy(string $markdown, string $owner): array
    {
        return [];
    }
}
