<?php

namespace Tests\Rooms\PretendRoom;

use Illuminate\Support\Str;

/**
 * The slot's stand-in driver: it remembers every push and reaches nothing outside the app.
 */
class FakePretendFeed implements PretendFeed
{
    /**
     * @var list<array{note: string, id: string}>
     */
    private array $pushed = [];

    public function name(): string
    {
        return 'fake';
    }

    public function push(string $note): string
    {
        $id = 'fake-'.Str::uuid();

        $this->pushed[] = ['note' => $note, 'id' => $id];

        return $id;
    }

    /**
     * The notes pushed through this instance, for tests.
     *
     * @return list<array{note: string, id: string}>
     */
    public function pushed(): array
    {
        return $this->pushed;
    }
}
