<?php

use App\Enums\SyncOutcome;
use App\Filament\Pages\BusinessFolder;
use App\Icm\FolderSync;
use App\Models\IcmVersion;
use App\Models\User;
use Pest\Browser\Playwright\Page;
use Tests\Rooms\RoomSectionAxe;

require_once __DIR__.'/../Walks/WalkHelpers.php';

/**
 * The walk lines on the Business Folder page (10 D-11, the UI-SPEC's accessibility contract), as browser tests: a walk the
 * gate refuses shows as one more plain line in the Last sync section's refusal list, read in order after the heading and
 * the status line, with no control added and so no new tab stop, no sideways scroll at 375 CSS px, and nothing for axe to
 * flag on the page. Every line comes from a real sync of a real folder, never a hand-written list. Real Chromium, at desktop
 * width and at 375 CSS px, in CI on both databases with no skip.
 */

/**
 * The page's address for the signed-in owner, after a sync that the walks refused: one walk with no recording (tests/a-new.md)
 * and one whose recording fails (tests/b-price.md: the recorded reply carries no price sentence and the service's price
 * policy is none, so the check cannot hold). The world is built before visit(), because the Pest browser plugin starts its
 * browser only for a test whose own body calls it. Returns the page's address and the live version the refused sync left alone.
 *
 * @return array{0: string, 1: IcmVersion}
 */
function walkLinesBrowserRefusedWorld(): array
{
    $version = activateExampleFolder();

    layWalk('tests/a-new.md', pricingWalkText());
    seedRecording(layWalk('tests/b-price.md', pricingWalkText()), pricingWalkTape());
    breakFile('business/services/ac-repair.md', fn (string $text) => preg_replace(
        ['/^price_policy: .*$/m', '/^price_cents: .*\n/m', '/^price_sentences:\n  - sentence: .*\n/m'],
        ['price_policy: none', '', ''],
        $text,
    ));

    expect(app(FolderSync::class)->run()->outcome)->toBe(SyncOutcome::Rejected);

    return [BusinessFolder::getUrl(isAbsolute: false), $version];
}

/**
 * The page's address after a sync that a walk file itself was refused for (D-10): a booking at a named time in a walk that
 * names no start. Returns the page's address and the live version the refused sync left alone.
 *
 * @return array{0: string, 1: IcmVersion}
 */
function walkLinesBrowserWalkFileWorld(): array
{
    $version = activateExampleFolder();

    writeIcmFile('tests/b-late.md', "---\ntype: walk_test\nname: Dana books a late visit\npersona: A homeowner whose air conditioner stopped\nchannel: sms\nscript:\n  - says: \"My AC stopped working. Can someone come?\"\n    expect: [sent on its own]\n  - books: \"2026-12-02 11:00\"\n---\nA walk.\n");

    expect(app(FolderSync::class)->run()->outcome)->toBe(SyncOutcome::Rejected);

    return [BusinessFolder::getUrl(isAbsolute: false), $version];
}

/**
 * Wait until the Last sync section is drawn on the page the test opened with visit() itself (the Pest browser plugin
 * starts its browser only for a test whose own body calls visit), and return the browser page.
 *
 * @param  object  \$page  what visit() returned
 */
function walkLinesBrowserReady(object $page): Page
{
    $page->assertSee('Last sync');

    return $page->page();
}

/**
 * The Last sync section as a reader meets it: its heading, every paragraph's text in document order, whether the heading
 * comes before the first paragraph in the document, and how many links, buttons, fields or elements with a tabindex it holds.
 *
 * @return array{heading: string, paragraphs: list<string>, headingFirst: bool, stops: int}
 */
function walkLinesBrowserSection(Page $browser): array
{
    return $browser->evaluate(<<<'JS'
() => {
  const section = [...document.querySelectorAll('.fi-section')].find((candidate) => {
    const heading = candidate.querySelector('.fi-section-header-heading');
    return heading !== null && heading.textContent.trim() === 'Last sync';
  });
  const heading = section.querySelector('.fi-section-header-heading');
  const paragraphs = [...section.querySelectorAll('p')];
  return {
    heading: heading.textContent.trim(),
    paragraphs: paragraphs.map((paragraph) => paragraph.innerText.trim()),
    headingFirst: (heading.compareDocumentPosition(paragraphs[0]) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
    stops: section.querySelectorAll('a, button, input, select, textarea, [tabindex]').length,
  };
}
JS);
}

/**
 * Whether the page fits its window with no sideways scroll.
 */
function walkLinesBrowserFitsWidth(Page $browser): bool
{
    return (bool) $browser->evaluate('() => document.documentElement.scrollWidth <= window.innerWidth');
}

/**
 * Run axe on the whole page with the WCAG 2.2 AA tags and the target-size rule on, except the color-contrast rule, which
 * is switched off for this page. The refusal lines carry an inline color of #dc2626 that the walk lines inherit, and that
 * red falls below 4.5:1 on Filament's dark section background. That is a known gap in the page that came before the walk
 * lines, and Ian made fixing it a Phase 11 must-have on 2026-11-21 (10-UI-SPEC, "Out of scope"). Every other rule runs.
 *
 * @return array{checked: int, violations: list<array<string, mixed>>}
 */
function businessFolderWalkLinesAxe(Page $browser): array
{
    $options = json_encode([
        'runOnly' => ['type' => 'tag', 'values' => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']],
        'rules' => ['target-size' => ['enabled' => true], 'color-contrast' => ['enabled' => false]],
    ], JSON_THROW_ON_ERROR);

    $report = $browser->evaluate(<<<JS
async () => {
  const results = await window.axe.run(document, {$options});
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);

    return ['checked' => (int) ($report['checked'] ?? 0), 'violations' => $report['violations'] ?? []];
}

it('reads the walk lines in order after the heading and the status line', function (int $width) {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$url, $version] = walkLinesBrowserRefusedWorld();

    $page = $width === 375 ? visit($url)->resize(375, 812) : visit($url);
    $browser = walkLinesBrowserReady($page);
    $section = walkLinesBrowserSection($browser);

    expect($section['heading'])->toBe('Last sync')
        ->and($section['headingFirst'])->toBeTrue()
        ->and($section['paragraphs'])->toHaveCount(3)
        ->and($section['paragraphs'][0])->toMatch('/^Rejected · [A-Z][a-z]{2} \d{1,2}, \d{1,2}:\d{2} (am|pm)$/')
        ->and($section['paragraphs'][1])->toBe("tests/a-new.md: this walk has no recording yet. Recording it is Ian's step. {$version->label()} stays live.")
        ->and($section['paragraphs'][2])->toBe("tests/b-price.md, turn 1: the reply did not carry the price sentence for AC repair. {$version->label()} stays live.");

    if ($width === 375) {
        expect(walkLinesBrowserFitsWidth($browser))->toBeTrue();
    }
})->with([
    'at desktop width' => [1280],
    'at 375 CSS px' => [375],
]);

it('reads a walk-file refusal the same way', function (int $width) {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    walkLinesBrowserWalkFileWorld();

    $page = $width === 375 ? visit(BusinessFolder::getUrl(isAbsolute: false))->resize(375, 812) : visit(BusinessFolder::getUrl(isAbsolute: false));
    $browser = walkLinesBrowserReady($page);
    $section = walkLinesBrowserSection($browser);

    expect($section['heading'])->toBe('Last sync')
        ->and($section['headingFirst'])->toBeTrue()
        ->and($section['paragraphs'])->toHaveCount(2)
        ->and($section['paragraphs'][0])->toMatch('/^Rejected · /')
        ->and($section['paragraphs'][1])->toStartWith('tests/b-late.md: script item 2 books names a time (2026-12-02 11:00) but the walk has no start.');

    if ($width === 375) {
        expect(walkLinesBrowserFitsWidth($browser))->toBeTrue();
    }
})->with([
    'at desktop width' => [1280],
    'at 375 CSS px' => [375],
]);

it('adds no tab stop to the Last sync section', function (int $width) {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$url] = walkLinesBrowserRefusedWorld();

    $page = $width === 375 ? visit($url)->resize(375, 812) : visit($url);
    $section = walkLinesBrowserSection(walkLinesBrowserReady($page));

    expect($section['paragraphs'])->toHaveCount(3)
        ->and($section['stops'])->toBe(0);
})->with([
    'at desktop width' => [1280],
    'at 375 CSS px' => [375],
]);

it('has no sideways scroll at 375 CSS px with walk lines showing', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$url] = walkLinesBrowserRefusedWorld();

    $page = visit($url)->resize(375, 812);
    $browser = walkLinesBrowserReady($page);

    expect(walkLinesBrowserSection($browser)['paragraphs'])->toHaveCount(3)
        ->and($browser->evaluate('() => window.innerWidth'))->toBe(375)
        ->and(walkLinesBrowserFitsWidth($browser))->toBeTrue();
});

it('finds no accessibility violation with walk lines showing', function (string $world, int $width) {
    $this->actingAs(User::factory()->create(['name' => 'Ian Owner']));

    [$url] = $world === 'refused walks' ? walkLinesBrowserRefusedWorld() : walkLinesBrowserWalkFileWorld();

    $page = $width === 375 ? visit($url)->resize(375, 812) : visit($url);
    $browser = walkLinesBrowserReady($page);

    RoomSectionAxe::assertClean(businessFolderWalkLinesAxe($browser));
})->with([
    'refused walks, at desktop width' => ['refused walks', 1280],
    'refused walks, at 375 CSS px' => ['refused walks', 375],
    'a walk file refusal, at desktop width' => ['a walk file refusal', 1280],
    'a walk file refusal, at 375 CSS px' => ['a walk file refusal', 375],
]);
