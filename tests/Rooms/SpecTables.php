<?php

namespace Tests\Rooms;

/**
 * Reads the tables in docs/SPEC.md for the tests that tie them to code. Every method takes the markdown as a string and
 * never touches disk, so the test that calls it decides which file to read.
 */
class SpecTables
{
    /**
     * The cells of each table row under a heading, from the line after the heading to the next line that starts with
     * #. A row is a line that starts with a pipe and a backtick, so the header and divider lines are not rows. Each
     * cell is trimmed.
     *
     * @return list<list<string>>
     */
    public static function rows(string $markdown, string $heading): array
    {
        $rows = [];
        $underHeading = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (! $underHeading) {
                $underHeading = trim($line) === $heading;

                continue;
            }

            if (str_starts_with($line, '#')) {
                break;
            }

            if (str_starts_with($line, '| `')) {
                $rows[] = array_map('trim', explode('|', (string) preg_replace('/^\||\|$/', '', trim($line))));
            }
        }

        return $rows;
    }

    /**
     * The rows of the "Who owns each file" table: the pattern is the first backticked token of the first cell, and the
     * owner is the second cell up to its first parenthesis.
     *
     * @return list<array{pattern: string, owner: string}>
     */
    public static function ownership(string $markdown): array
    {
        $ownership = [];

        foreach (self::rows($markdown, '### Who owns each file') as $cells) {
            if (preg_match('/`([^`]+)`/', $cells[0], $pattern) === 1) {
                $ownership[] = ['pattern' => $pattern[1], 'owner' => trim(explode(' (', $cells[1] ?? '')[0])];
            }
        }

        return $ownership;
    }

    /**
     * The patterns the table gives to one owner, in table order. A room's boot test compares its contract's owns with
     * this.
     *
     * @return list<string>
     */
    public static function patternsOwnedBy(string $markdown, string $owner): array
    {
        return array_values(array_map(
            fn (array $row): string => $row['pattern'],
            array_filter(self::ownership($markdown), fn (array $row): bool => $row['owner'] === $owner),
        ));
    }

    /**
     * The kinds a connector may own: the first backticked token of each row under "Kinds of record a connector may own",
     * in table order. The same list is OwnableKind in code.
     *
     * @return list<string>
     */
    public static function ownableKinds(string $markdown): array
    {
        return self::keysUnder($markdown, '### Kinds of record a connector may own');
    }

    /**
     * The kinds the platform owns, from the rows under "Kinds of record the platform owns", in table order. The same
     * list is PlatformKind in code.
     *
     * @return list<string>
     */
    public static function platformKinds(string $markdown): array
    {
        return self::keysUnder($markdown, '### Kinds of record the platform owns');
    }

    /**
     * The first backticked token of each row under a heading, in table order.
     *
     * @return list<string>
     */
    private static function keysUnder(string $markdown, string $heading): array
    {
        $keys = [];

        foreach (self::rows($markdown, $heading) as $cells) {
            if (preg_match('/`([^`]+)`/', $cells[0], $key) === 1) {
                $keys[] = $key[1];
            }
        }

        return $keys;
    }
}
