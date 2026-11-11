<?php

use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Pest\Browser\Playwright\Page;

/**
 * The visit line box on a held draft for a booked customer (D-39 amended, U-37), in real Chromium: axe finds nothing wrong in the box,
 * one Tab from the draft box reaches the checkbox, a screen reader announces it by the line that says what it does, and
 * Space unticks it. Real keys, because the faults that matter here live in focus order and in the accessibility tree.
 * The rest of the Needs you block waits for Phase 11 (02.1 D-48), so axe is scoped to the box's own wrapper. Every wait
 * is Playwright's own locator wait, because the plugin's function and URL waits return at once in version 5.1 (STATE.md Phase 7).
 */
const DRAFT_VISIT_LINE_KEYBOARD_LABEL = 'The visit line will be added: Your visit for AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.';

/**
 * A held scheduling draft for ac-repair with a visit booked Thursday 2026-11-05 at 10:00 in the shop's zone, the owner signed in, and the address of the conversation's page. The test
 * calls visit() itself, because the Pest browser plugin starts its browser only for a test whose own body calls it.
 */
function draftVisitLineKeyboardConversationPage(): string
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    $owner = User::factory()->withPhone('+12025550199')->create();

    frontDeskCustomerTexts('Can you come out Thursday morning?');
    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();
    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'We guarantee 9 am Thursday. Does that work?', 'label' => 'scheduling', 'service_slug' => 'ac-repair']);
    runQueuedFrontDeskJobs();

    expect(Message::query()->where('status', 'draft')->count())->toBe(1);

    test()->actingAs($owner);

    return ConversationResource::getUrl('view', ['record' => Conversation::sole()], isAbsolute: false);
}

/**
 * Press one key on whatever has focus now, the way a person does: down and up at the page, not on a chosen element.
 */
function draftVisitLineKeyboardPress(Page $browser, string $key): void
{
    $browser->keyDown($key);
    $browser->keyUp($key);
}

/**
 * Run axe with the WCAG 2.2 AA tags and the target-size rule on the box's wrapper, and return what it found.
 *
 * @return array{checked: int, violations: list<array<string, mixed>>}
 */
function draftVisitLineKeyboardAxe(object $page): array
{
    $context = json_encode(['include' => [['[data-visit-line-choice]']]], JSON_THROW_ON_ERROR);
    $options = json_encode([
        'runOnly' => ['type' => 'tag', 'values' => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']],
        'rules' => ['target-size' => ['enabled' => true]],
    ], JSON_THROW_ON_ERROR);

    $report = $page->script(<<<JS
async () => {
  const results = await window.axe.run({$context}, {$options});
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);

    return ['checked' => (int) ($report['checked'] ?? 0), 'violations' => $report['violations'] ?? []];
}

it('finds no accessibility violation in the visit line box (D-43)', function () {
    $url = draftVisitLineKeyboardConversationPage();

    $page = visit($url);
    $page->assertSee('Needs you')->assertSee(DRAFT_VISIT_LINE_KEYBOARD_LABEL);

    $report = draftVisitLineKeyboardAxe($page);

    expect($report['checked'])->toBeGreaterThan(0, 'axe evaluated no rules, so the box was empty or missing')
        ->and($report['violations'])->toBeEmpty(json_encode($report['violations'], JSON_UNESCAPED_SLASHES));
});

it('reaches the box with one Tab from the draft, names it by the line, and unticks it with Space', function () {
    $url = draftVisitLineKeyboardConversationPage();

    $page = visit($url);
    $page->assertSee(DRAFT_VISIT_LINE_KEYBOARD_LABEL);
    $browser = $page->page();

    $checkbox = $browser->getByRole('checkbox', ['name' => DRAFT_VISIT_LINE_KEYBOARD_LABEL, 'exact' => true]);

    expect($checkbox->count())->toBe(1);

    $browser->evaluate("() => document.querySelector('textarea[aria-label=\"Draft reply to the customer\"]').focus()");
    draftVisitLineKeyboardPress($browser, 'Tab');

    $browser->locator('#add-visit-line:focus')->waitFor(['state' => 'attached']);

    expect($browser->evaluate('() => document.activeElement.id'))->toBe('add-visit-line')
        ->and($browser->evaluate('() => document.activeElement.checked'))->toBeTrue();

    draftVisitLineKeyboardPress($browser, 'Space');

    $browser->locator('#add-visit-line:not(:checked)')->waitFor(['state' => 'attached']);

    expect($browser->evaluate('() => document.activeElement.checked'))->toBeFalse();
});
