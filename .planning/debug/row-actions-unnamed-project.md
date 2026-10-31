---
status: diagnosed
trigger: "Gap G-07-2.3 (UAT test 2): Each row action on the contact screen's project list has an accessible name that names its project by label (\"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\"), with the visible text unchanged (\"Close\", \"Reopen\", \"Rename\"). Ian: 'Ian wants each row button to name its job for screen readers: \"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\", using the job's label, with the visible text unchanged.'"
created: 2026-10-31T23:10:00Z
updated: 2026-10-31T23:55:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: CONFIRMED. The three row actions in ProjectsRelationManager are plain label-only Filament table actions. Filament renders a table row action as a link-styled `<button>` whose only text is the action label, and sets no `aria-label` unless `hiddenLabel()` is used (which removes the visible text). So the accessible name is the bare verb ("Close", "Reopen", "Rename"), identical on every row. Nothing in the class supplies the row's label to the control.
test: Rendered the real relation manager (Livewire::test html()) with 3 projects (one renamed to a hostile name, one closed) as shipped and again with a throwaway subclass using `->extraAttributes(fn ($record) => ['aria-label' => e(...)])`; parsed with the PHP 8.5 spec HTML parser; then injected both renders into a real headless Chromium page and asked Playwright `getByRole('button', {name, exact})`.
expecting: Shipped: names are the bare verbs, repeated across rows. Variant: names are "Close Job 1", "Reopen Job 3", "Rename Job 3" and the hostile row matches exactly. Got exactly that (see Evidence).
next_action: none (diagnosis complete); hand ROOT CAUSE FOUND to the gap-closure planner.
bug_class: bohrbug
reasoning_checkpoint: null
tdd_checkpoint: null

## Symptoms

expected: Each row action on the contact screen's project list has an accessible name that names its project by label ("Close Johnny", "Reopen Job 1", "Rename Job 2"), with the visible text unchanged ("Close", "Reopen", "Rename"). The label is the project's name when it has one, else "{Word} {number}" (Project::label(), app/Models/Project.php).
actual: "Ian wants each row button to name its job for screen readers: \"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\", using the job's label, with the visible text unchanged." VoiceOver names every button today, but only "Close" / "Reopen" / "Rename", the same on every row.
errors: None.
reproduction: Test 2 in .planning/phases/07-the-spine-and-leads/07-UAT.md.
started: Since the relation manager was built (07-05); found at UAT 2026-10-31.

## Eliminated

- hypothesis: Filament has a built-in per-record aria-label or tooltip that only needs switching on.
  evidence: The only aria-label Filament emits for an action is the action's own label when `hiddenLabel()` is set (CanGenerateLinkHtml.php:103-106, CanGenerateButtonHtml.php:100-101, CanGenerateIconButtonHtml.php:91-92), and that mode also removes the visible text (`if (! $isLabelSrOnly)` around the label span, CanGenerateLinkHtml.php:172-176), the opposite of what Ian asked. `->tooltip()` only adds an `x-tooltip` hover popover (CanGenerateLinkHtml.php:158-163), not a name. Neither is per record.
  timestamp: 2026-10-31T23:20:00Z
- hypothesis: A visually-hidden `fi-sr-only` span inside an Htmlable label is the better mechanism.
  evidence: It works in the link view and the class is in the compiled CSS (public/css/filament/filament/app.css, utilities.css:453), but the action label is reused as the default modal heading (CanOpenModal.php:634-637 `getCustomModalHeading() ?? getLabel()`) and by `getLabel()` in tests, so the hidden span leaks into the confirmation heading; the owner text must be escaped by hand inside the HtmlString; and the hidden text becomes part of the button's textContent (probe C: textContent "Close Job 1") so it is found by Find-in-page and copy. More moving parts for the same result.
  timestamp: 2026-10-31T23:35:00Z
- hypothesis: Filament escapes extraAttributes values, so the owner's text is safe in an aria-label without help.
  evidence: Refuted. HasExtraAttributes.php:21-22 says "Attribute values are not escaped when rendered". The bag is merged with `escape: false` (Action.php:1172), and Laravel's `ComponentAttributeBag::__toString()` (vendor/laravel/framework/src/Illuminate/View/ComponentAttributeBag.php:502) only does `str_replace('"', '\\"', trim($value))`, which is not an HTML escape (the quote still ends the attribute). Probe B (no `e()`): the hostile name put a real `<script>` element in the parsed DOM and the row's button lost its `wire:key`.
  timestamp: 2026-10-31T23:38:00Z
- hypothesis: Adding `extraAttributes` changes the generated `wire:key`, which would break the G-07-2.2 fix.
  evidence: Refuted. The key is `{livewireId}.actions.{actionName}.{md5(serialize(context))}` (Action.php:378-393) and never reads extra attributes. Probe D: keys before and after identical (`actions.toggle.<md5>` unchanged across Close, Reopen and Rename). An extra `wire:key` could not override the generated one anyway: existing attributes win in `merge()` (vendor/filament/support/src/View/ComponentAttributeBag.php:279-282).
  timestamp: 2026-10-31T23:40:00Z

## Evidence

- timestamp: 2026-10-31T23:12:00Z
  checked: vendor/filament/tables/src/Table/Concerns/HasRecordActions.php:58-59 and vendor/filament/actions/src/Action.php:793-814, 816-840, 872-929
  found: Every table row action is given `defaultSize(Size::Small)` and `defaultView(LINK_VIEW)`. `toHtml()` takes the optimized-link path (`canRenderOptimizedLink()`) when none of badge/tooltip/icon-position/hidden-label/disabled/url-options/auth/**extra attributes**/etc. is set, and `toOptimizedLinkHtml()` writes `<button type="button" wire:loading.attr="aria-disabled" wire:click=... wire:key=... class="fi-ac-link-action fi-link fi-size-sm ..."><svg aria-hidden="true" .../><span class="fi-link-label">{label}</span></button>` (lines 926-927; label is `e($this->getLabel())` at line 890). No aria-label anywhere on that markup.
  implication: The accessible name today is computed from content: the aria-hidden spinner is ignored and the span text is the name, "Close" / "Reopen" / "Rename". The `! $this->hasExtraAttributes()` gate is Action.php:835.
- timestamp: 2026-10-31T23:15:00Z
  checked: Rendered HTML of the shipped relation manager (Livewire::test html()), 3 projects. Scratch probe, deleted.
  found: Six row buttons, each `<button ... wire:key="{id}.actions.{close|rename|reopen}.{md5}" class="fi-ac-link-action fi-link fi-size-sm ..."><svg aria-hidden=true .../><span class="fi-link-label">Close</span></button>`; no `aria-label` attribute on any.
  implication: Direct observation of the bug at the markup level.
- timestamp: 2026-10-31T23:25:00Z
  checked: tables index.blade.php:168-188 (`$reduceVisibleRecordActions`) and Action.php:575 (`'record' => [$this->getRecord()]`), HasExtraAttributes.php:19-50
  found: Each row gets a clone of each action with `->record($record)`; `extraAttributes(array|Closure)` is evaluated per clone in `getExtraAttributes()` and a closure receives the row model (`Project $record`) and `Action $action` (both verified by probe). `Action.php:1172` (toLinkHtml) merges the result with `escape: false`. This is the only per-record attribute hook Filament offers for an action.
  implication: `->extraAttributes(fn (Project $record): array => ['aria-label' => ...])` is the smallest stock mechanism: no custom view, no CSS, no JS (07-CONTEXT D-21 stock-Filament contract holds).
- timestamp: 2026-10-31T23:28:00Z
  checked: Variant A probe (throwaway subclass of ProjectsRelationManager). One `toggle` action (label closure, `->requiresConfirmation(fn ($r) => $r->isOpen())`, the G-07-2.2 shape) plus `rename`, each with `->extraAttributes(fn (Action $action, Project $record): array => ['aria-label' => e($action->getLabel().' '.$record->label($word, (int) $record->number))])`.
  found: Rendered tags carry `aria-label="Close Job 1"`, `aria-label="Rename Job 1"`, `aria-label="Reopen Job 3"`, `aria-label="Rename Job 3"`. Visible text content stays "Close" / "Rename" / "Reopen". The numbers are right (Job 1, Job 3) because the table query's `withNumber()` select puts `number` on the row model. The hostile row (`Johnny "the <b>boss</b>" & co's &amp; <script>x</script>`) renders as `aria-label="Close Johnny &quot;the &lt;b&gt;boss&lt;/b&gt;&quot; &amp; co&#039;s &amp;amp; &lt;script&gt;x&lt;/script&gt;"`; parsed with the spec HTML parser the attribute value equals the raw string exactly and there are 0 `<script>` and 0 `<b>` elements.
  implication: Mechanism works, escaping with `e()` (default double-encode, because the owner's text is raw) is correct and required.
- timestamp: 2026-10-31T23:32:00Z
  checked: Variant B probe: same but without `e()`.
  found: Parsed DOM contains a `<script>` element and the hostile row's button is missing its `wire:key` (the quote in the name ended the attribute).
  implication: Without `e()` the owner's project name is injectable markup (stored XSS, authenticated owner only, but still). RenameProject refuses only hidden characters and more than 100 characters (RenameProject.php:67-84), so `<`, `>`, `"`, `'`, `&` are all legal in a name.
- timestamp: 2026-10-31T23:42:00Z
  checked: Real headless Chromium (Pest browser plugin, Playwright). Navigated to /admin, replaced body with each render, queried `getByRole('button', {name, exact: true}).count()`.
  found: |
    SHIPPED: "Close" x2, "Reopen" x1, "Rename" x3 exact matches; "Close Job 1", "Reopen Job 3", "Rename Job 3" = 0; the hostile name = 0.
    VARIANT A: "Close" x0, "Reopen" x0, "Rename" x0; "Close Job 1" = 1, "Reopen Job 3" = 1, "Rename Job 3" = 1, and the hostile `Close Johnny "the <b>boss</b>" & co's &amp; <script>x</script>` = 1 exact match.
  implication: In a real browser the computed accessible name is the aria-label, it starts with the visible word, and the owner's text round-trips with no markup injected. WCAG 2.5.3 Label in Name passes: the name "Close Job 1" contains the visible text "Close" and begins with it (the SC's own best practice), so speech-recognition users saying "click Close" still match, and nothing about the visible text changed.
- timestamp: 2026-10-31T23:44:00Z
  checked: Probe D: `callTableAction('toggle', $one)`, then `('toggle', $three)`, then `('rename', $two, data: ['name' => 'Renamed'])`, re-reading the keys and aria-labels each time.
  found: wire:keys byte-identical before and after each action. aria-label follows the toggle in the same render ("Close Job 1" becomes "Reopen Job 1"; "Reopen Job 3" becomes "Close Job 3").
  implication: Composes with the G-07-2.2 single `toggle` action: the name tracks the label, the node identity (and so focus) is untouched. A Rename re-render updates the row's names because the closure reads the model each render.
- timestamp: 2026-10-31T23:46:00Z
  checked: Mounted-action record and the confirmation modal (probe E): `mountTableAction('toggle', $two)` then `getMountedAction()`.
  found: `getRecord()->number` = 2 on the mounted record (Table::getQuery applies modifyQueryUsing even when resolving a record: HasQuery.php:59-78, HasRecords.php:194-204). Default modal text for Close today: heading = the action label "Close" (CanOpenModal.php:634-637), description "Are you sure you would like to do this?" (filament-actions::modal.confirmation, CanOpenModal.php:650-652), submit "Confirm". A `->modalHeading(fn (Action $action, Project $record): string => ...)` closure gets the numbered record and the heading renders through Blade `{{ $heading }}` (support modal/index.blade.php:293), which escapes.
  implication: No N+1 and no extra query to get `number`: it rides on the one table select (Project::withNumber is a correlated subselect). Naming the project in the confirmation heading is possible with the same record, but nothing today names it.
- timestamp: 2026-10-31T23:48:00Z
  checked: Livewire test helpers against the probe (Pest + Livewire 4.4.7).
  found: `->assertSeeHtml('aria-label="Close Job 1"')`, `->assertDontSeeHtml('<script>x</script>')` and `->assertTableActionExists('toggle', fn (Action $a): bool => ($a->getExtraAttributes()['aria-label'] ?? null) === 'Close Job 1', $record)` all pass against the variant and read the per-row clone.
  implication: The whole attribute contract is provable in the existing fast Livewire test file with the existing `projectsListFor()` helper; no browser needed for the names themselves.
- timestamp: 2026-10-31T23:50:00Z
  checked: Tried to drive the contact page's real morph (Close-confirm, read activeElement aria-label) with the variant swapped in via the container and via `Livewire::component(...)`.
  found: Not achieved: the browser server kept rendering the shipped class (buttons showed "(none)" aria-label), so the swap did not take. Abandoned; no data from this.
  implication: Blind spot recorded below. The G-07-2.2 diagnosis already proved focus stays on one node through the real morph with the toggle shape; an aria-label is a plain attribute on that same keyed node, and the server render (probe D) shows the new value, so the morph only has to patch an attribute.

## Resolution

root_cause: |
  app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:95-135 declares the Close, Reopen and Rename row actions with a label only. Filament renders every table row action as a link-styled button (vendor/filament/tables/src/Table/Concerns/HasRecordActions.php:58-59) via `toOptimizedLinkHtml()` (vendor/filament/actions/src/Action.php:872-929, markup at 926-927), whose content is `<svg aria-hidden="true"/><span class="fi-link-label">{label}</span>` and which emits no `aria-label` (Filament only sets one for `hiddenLabel()`, which hides the visible text: CanGenerateLinkHtml.php:103-106). The accessible name is therefore computed from content: the bare verb, the same on every row. The row's label (`$record->label($word, (int) $record->number)`) is computed only for the first table column (ProjectsRelationManager.php:58-60), never handed to the buttons. Verified in real Chromium (Playwright role+name matching): shipped gives "Close" x2, "Reopen" x1, "Rename" x3 with nothing row-specific.
  The smallest stock mechanism is `->extraAttributes(fn (Action $action, Project $record): array => ['aria-label' => e($action->getLabel().' '.$record->label($word, (int) $record->number))])` on each row action (Rename uses the literal verb). It needs no view, CSS or JS, the record closure receives the row model with `number`, and it does not change the generated `wire:key`. One hard requirement: Filament does NOT escape extraAttributes values (HasExtraAttributes.php:21-22; merged with `escape: false` at Action.php:1172; Laravel's bag only swaps `"` for `\"`, ComponentAttributeBag.php:502), and project names are owner text that may hold `<`, `>`, `"`, `'`, `&` (RenameProject.php:67-84), so the value must be wrapped in `e()` or the name becomes injectable markup (probe B).
  Single cause. AND-gate: no. Candidate causes across categories: code (label-only actions with no per-row name: confirmed); config (a Filament table/panel setting that adds record context to action names: none exists, only per-action `extraAttributes`); environment (browser or screen-reader naming differences: not needed, the rendered markup has no per-row attribute and Chromium's role+name query confirms identical names); data (project count, status or number: irrelevant, every row is affected equally).
fix: (not applied - diagnose-only)
verification: (not applicable - diagnose-only; the variant probe, the escaping probe and the Chromium role+name query above are the pre-fix confirmation)
oracle_type: specified (WCAG 2.5.3 Label in Name plus Ian's stated names); derived for the invariant "every row action's aria-label starts with its own visible text and ends with that row's label"
files_changed: []

### Notes for the gap-closure plan (non-binding)

Composes with G-07-2.2 (one stable `toggle` action) and G-07-2.1 (`$isLazy = false`); one pass over the class does all three. Q-by-Q:

1. Where the name comes from today: content of `<span class="fi-link-label">` inside a link-styled `<button>` (see Resolution). Filament actions have no built-in per-record aria-label; `hiddenLabel()` emits aria-label but hides the visible text; `tooltip()` is a hover popover. `extraAttributes(Closure)` is the one per-record hook.
2. Mechanism: `extraAttributes` closure returning `aria-label`. Compared with an sr-only span: aria-label leaves the action label untouched (modal heading, test assertions, Find-in-page unaffected), needs no CSS class, and is one line per action. WCAG 2.5.3 passes ("Close Job 1" begins with "Close"). Build the verb from `$action->getLabel()` for the toggle so the name can never drift from the visible text; Rename can use the literal "Rename". Compute the label once (a small private method or a shared closure) and use it in the label column too, so the column and the buttons cannot disagree. One cost: extraAttributes makes Filament skip `toOptimizedLinkHtml()` for those actions (Action.php:835) and use `toLinkHtml()` (the general path Filament uses for any link with an icon or tooltip); the loading spinner, `wire:loading`, `wire:key` and classes are the same (compare shipped and variant tags in Evidence). Eyeball once that spacing is unchanged.
3. Number: `$record->number` is present on both the rendered rows and the mounted record because `withNumber()` is applied through `modifyQueryUsing` (probe: Job 1, Job 3, mounted record number 2). No N+1, no extra query. Escaping: Filament does not escape; use `e()` with the default double-encode.
4. wire:key: unchanged by extraAttributes (Action.php:378-393 ignores them; merge precedence at ComponentAttributeBag.php:279-282); probe D confirms keys identical across toggle and rename. Does not disturb G-07-2.2.
5. Confirmation modals: today Close's heading is just "Close", description "Are you sure you would like to do this?", submit "Confirm"; Rename's heading is "Rename". Neither names the project. Ian asked only about row buttons. If wanted later, `->modalHeading(fn (Action $action, Project $record): string => $action->getLabel().' '.$label)` works with the numbered mounted record, rendered escaped through Blade. Note only; not part of this gap.
6. Tests (tests-first, in tests/Feature/Filament/ProjectsRelationManagerTest.php using `projectsListFor()`):
   - Three projects: an open one, one renamed to a hostile name such as `Johnny "the <b>boss</b>" & co`, one closed. Assert `->assertSeeHtml('aria-label="Close Job 1"')`, `'aria-label="Rename Job 1"'`, `'aria-label="Reopen Job 3"'`, and for the hostile one `'aria-label="'.e('Close '.$name).'"'`, plus `->assertDontSeeHtml('<b>boss</b>')`. All verified to work as helpers.
   - A DOM invariant over every row action button (parse `->html()` with `Dom\HTMLDocument::createFromString`, PHP 8.5): `str_starts_with(aria-label, trim(textContent))` (WCAG 2.5.3) and `str_ends_with(aria-label, that row's label)`; the visible text is exactly "Close"/"Reopen"/"Rename".
   - After `callTableAction('rename', $project, data: ['name' => 'Renamed'])` the next render shows `aria-label="Rename Renamed"` and no longer `Rename Job 1`.
   - If the G-07-2.2 toggle test compares `wire:key` before/after, extend it to run with the aria-label in place (it will pass; it guards the composition).
   - `->assertTableActionExists('toggle', fn (Action $a): bool => ($a->getExtraAttributes()['aria-label'] ?? null) === 'Close Job 1', $record)` is an alternative per-row check.
   - A GET of the contact page asserting `aria-label="Close Job 1"` needs G-07-2.1 fixed first (today the first response holds only the lazy placeholder).
   - Browser (optional, the repo already uses `visit()` in tests/Feature/Rooms/*BootTest.php): extend the G-07-2.2 focus test to read `document.activeElement.getAttribute('aria-label')` after Close-confirm and expect "Reopen Job 1", which proves the name follows the swap through a real morph. The Pest `Webpage` hides the Playwright page, so a role+name query needs reflection; the attribute read is simpler.
   - Stays a hand check: VoiceOver in Safari actually announcing "Close Johnny, button" / "Reopen Job 1, button" and the Rename name on each row, and what it says when the toggled button changes name under focus (the "Closed" / "Reopened" notification is what announces the outcome); voice-control "click Close" behaviour on a page with many Close buttons; a quick look that button spacing is unchanged from the optimized render.

Blind spots: I could not drive the full contact page with the variant swapped in (the in-process server kept rendering the shipped class), so the aria-label update through a real Livewire morph after Close-confirm is inferred from the server render plus the earlier focus diagnosis, not observed in a browser. The Chromium check injected rendered HTML into a blank admin page, so it proves how Chromium names those buttons, not the live page. No screen reader was run. Chromium only.
