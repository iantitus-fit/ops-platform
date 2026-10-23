<?php

namespace Tests\Rooms;

/**
 * The room standard's accessibility check (A11Y-01, D-27): axe, the copy the Pest browser plugin puts on every page,
 * run on one room's dashboard section with the WCAG 2.2 Level AA tags and the target-size rule switched on. Every
 * room's boot test calls this on its own section; the front desk and team rooms join in Phase 11, because 02.1 D-48
 * holds their styling until the phone walk. Nothing here touches disk.
 */
class RoomSectionAxe
{
    /**
     * Run axe on the element the shared section view marks with data-room-section="<roomKey>" and return what it found.
     * `checked` counts every rule axe evaluated (passed, incomplete or violated), so a run that judged nothing shows 0.
     *
     * @param  object  $page  the page object `visit()` returns, which runs JavaScript through script()
     * @return array{checked: int, violations: list<array{id: string, impact: ?string, help: string, targets: list<mixed>}>}
     */
    public static function report(object $page, string $roomKey): array
    {
        $context = json_encode(['include' => [['[data-room-section="'.$roomKey.'"]']]], JSON_THROW_ON_ERROR);
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

        return [
            'checked' => (int) ($report['checked'] ?? 0),
            'violations' => $report['violations'] ?? [],
        ];
    }

    /**
     * Fail unless axe judged at least one rule and found no violation. A failure prints each violated rule id with the
     * elements it hit, so the message alone says what to fix.
     *
     * @param  array{checked: int, violations: list<array<string, mixed>>}  $report
     */
    public static function assertClean(array $report): void
    {
        expect($report['checked'])->toBeGreaterThan(0, 'axe evaluated no rules, so the section was empty or missing');
        expect($report['violations'])->toBeEmpty(json_encode($report['violations'], JSON_UNESCAPED_SLASHES));
    }
}
