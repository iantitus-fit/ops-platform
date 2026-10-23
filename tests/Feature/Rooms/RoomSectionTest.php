<?php

use App\Models\User;
use App\Rooms\RoomSection;
use Livewire\Livewire;
use Tests\Rooms\PretendRoom\PretendRoomSection;

/**
 * The one test of the empty line every room's section shows (D-21). The platform draws it the same way for every room
 * from the room's own CONTEXT.md, so a real room's tests do not repeat it.
 */
it('shows the empty line on the dashboard for a room whose folder holds only the contract', function () {
    $this->actingAs(User::factory()->create());
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    $response = $this->get('/admin')->assertOk();

    expect(preg_match('/<div data-room-section="pretend">(.*?)<\/div>\s*<\/div>/s', $response->getContent(), $section))->toBe(1)
        ->and($section[1])->toContain('Pretend: nothing set up yet. Add a file to rooms/pretend/notes/ to start.');
});

it('reads the empty line from the contract as the platform draws it', function (string $label, array $owns, array $ownedFiles, ?string $line) {
    expect(RoomSection::emptyLine($label, $owns, $ownedFiles))->toBe($line);

    if ($line !== null) {
        expect($line)->not->toContain('!')->not->toContain('—');
    }
})->with([
    'a folder of files' => ['money', ['rooms/money/*.md'], [], 'Money: nothing set up yet. Add a file to rooms/money/ to start.'],
    'a folder written with a bare star' => ['money', ['rooms/money/*'], [], 'Money: nothing set up yet. Add a file to rooms/money/ to start.'],
    'an exact file and a folder' => ['business', ['business/profile.md', 'business/services/*.md'], [], 'Business: nothing set up yet. Add business/profile.md or a file to business/services/ to start.'],
    'three things' => ['stock', ['a.md', 'b/*.md', 'c.md'], [], 'Stock: nothing set up yet. Add a.md, a file to b/ or c.md to start.'],
    'a room that owns nothing' => ['quiet', [], [], null],
    'a room with an owned file live' => ['money', ['rooms/money/*.md'], ['rooms/money/first.md'], null],
]);

it('refuses a direct request to a section whose room is not in the live version', function () {
    $this->actingAs(User::factory()->create());
    enablePretendRoom();
    activateExampleFolder();

    Livewire::test(PretendRoomSection::class)->assertForbidden();
});
