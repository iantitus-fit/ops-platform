<?php

use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Rooms\RoomSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
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

it('still shows the empty line when the room\'s folder holds a router file beside the contract', function () {
    $this->actingAs(User::factory()->create());
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('rooms/pretend/notes/CONTEXT.md', 'Points at the notes.');
    activateExampleFolder();

    expect(app(ActiveConfig::class)->roomOwnedFiles('pretend'))->toBe([]);

    $response = $this->get('/admin')->assertOk();

    expect(preg_match('/<div data-room-section="pretend">(.*?)<\/div>\s*<\/div>/s', $response->getContent(), $section))->toBe(1)
        ->and($section[1])->toContain('Pretend: nothing set up yet. Add a file to rooms/pretend/notes/ to start.');
});

it('shows one plain line in a room section that fails, reports the error and leaves the rest of the dashboard standing', function () {
    $this->actingAs(User::factory()->create());
    enablePretendRoom();
    layPretendRoomFolder();
    activateExampleFolder();
    Message::factory()
        ->for(Conversation::factory()->for(Contact::factory()->create(['name' => 'Dana Whitfield'])))
        ->draft()
        ->create();

    Exceptions::fake();
    Schema::drop('pretend_pings');

    $response = $this->get('/admin')->assertOk();

    expect(preg_match('/<div data-room-section="pretend">(.*?)<\/div>\s*<\/div>/s', $response->getContent(), $section))->toBe(1)
        ->and($section[1])->toContain('This section could not load. The rest of the dashboard is fine.')
        ->and($section[1])->not->toContain('Pings so far');

    $response->assertSee('Drafts waiting')
        ->assertSee('Dana Whitfield')
        ->assertSee('data-room-section="money"', escape: false);

    Exceptions::assertReported(QueryException::class);
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
