<?php

use App\Models\Contact;
use App\Models\User;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Rooms\PretendRoom\ReceivePing;
use Tests\Rooms\RoomSectionAxe;

/**
 * The room standard's accessibility check (A11Y-01, D-27), proven on the pretend room: axe with the WCAG 2.2 Level AA
 * tags runs in a real browser on the room's dashboard section, as the D-21 empty line and furnished. The browser is
 * signed in with actingAs(): the spike in Plan 04-09 showed the plugin's in-process server carries the test's session
 * cookie, so `visit('/admin')` lands on the dashboard without going through the login form. There is no
 * database-driver guard and no skip: CI runs this on SQLite and Postgres.
 */
beforeEach(function () {
    enablePretendRoom();
    $this->actingAs(User::factory()->create());
});

it('finds no accessibility violation in the pretend section while it shows the empty line', function () {
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    $page = visit('/admin');
    $page->assertSee('Pretend: nothing set up yet.');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'pretend'));
});

it('finds no accessibility violation in the furnished pretend section', function () {
    layPretendRoomFolder();
    activateExampleFolder();
    app(ReceivePing::class)->handle(Contact::factory()->create(), 'Hello from the feed');

    $page = visit('/admin');
    $page->assertSee('Pings so far: 1')->assertSee('Pinned: First note');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'pretend'));
});

it('reports an image without alt text injected into the section, so the check is not vacuous', function () {
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    $page = visit('/admin');
    $page->script(<<<'JS'
() => {
  document.querySelector('[data-room-section="pretend"]').insertAdjacentHTML('beforeend', '<img src="/missing.png">');
}
JS);

    $report = RoomSectionAxe::report($page, 'pretend');

    expect(array_column($report['violations'], 'id'))->toContain('image-alt');
    expect(fn () => RoomSectionAxe::assertClean($report))->toThrow(ExpectationFailedException::class, 'image-alt');
});
