<?php

use App\Models\User;

/**
 * The spike behind the room standard's accessibility check (A11Y-01, D-27): a browser test reaches the signed-in
 * dashboard with the pretend room's section on it, and axe runs on that section. Signed in with actingAs().
 */
it('reaches the signed-in dashboard in a browser and runs axe on the pretend section', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();
    $this->actingAs(User::factory()->create());

    $page = visit('/admin');

    $page->assertSee('Pretend: nothing set up yet.');

    $report = $page->script(<<<'JS'
async () => {
  const results = await window.axe.run(
    { include: [['[data-room-section="pretend"]']] },
    { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
      rules: { 'target-size': { enabled: true } } }
  );
  return {
    checked: results.passes.length + results.incomplete.length + results.violations.length,
    violations: results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) })),
  };
}
JS);

    expect($report['checked'])->toBeGreaterThan(0);
});
