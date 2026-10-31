---
status: diagnosed
trigger: "Gap G-07-2.2 (UAT test 2): Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same. Focus should land on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3)."
created: 2026-10-31T22:00:00Z
updated: 2026-10-31T22:55:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: CONFIRMED. Close and Reopen are two Filament actions with different names. Filament stamps every table row action with `wire:key = {livewireId}.actions.{actionName}.{md5(context)}`, and Livewire's morph uses `wire:key` as the element identity, so when the row flips from Close to Reopen the Close button node is destroyed and a new Reopen node is inserted. Whatever held focus (or a reference for focus-return) points at a removed node, so focus falls to <body>.
test: Drove the real contact page in headless Chromium (Pest browser plugin / Playwright 1.63) with real Enter key presses and read document.activeElement after each step. Then repeated on throwaway probe subclasses to test candidate fixes. All probes deleted.
expecting: Close-confirm and Reopen leave activeElement = BODY on the shipped code; New, Rename and Close-cancel (trigger survives) return focus to the trigger; a fix that keeps one DOM node across the flip keeps focus on it.
next_action: none (diagnosis complete); hand ROOT CAUSE FOUND to the gap-closure planner.
bug_class: bohrbug
reasoning_checkpoint: null
tdd_checkpoint: null

## Symptoms

expected: After Close or Reopen is confirmed on the contact screen's project list, keyboard focus lands on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3).
actual: "Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same." (Ian, UAT test 2, 2026-10-31). Also reported working: New's confirmation returns focus to New; the Rename form takes focus and returns it to Rename. Reopen works functionally.
errors: None reported.
reproduction: Test 2 in .planning/phases/07-the-spine-and-leads/07-UAT.md - keyboard-only walk of a contact view page with two projects.
started: Since the relation manager was built (07-05); found at UAT 2026-10-31.

## Eliminated

- hypothesis: The button is disabled during the Livewire request, which blurs it, so focus is lost before the response.
  evidence: The rendered row buttons use `wire:loading.attr="aria-disabled"` (Action.php:926 toOptimizedLinkHtml), not `disabled`. Rename and the Close-cancel path keep and regain focus through the same request cycle (browser probe), so a request alone does not drop focus.
  timestamp: 2026-10-31T22:20:00Z
- hypothesis: The Alpine modal's own focus return (`filamentModal.restorePreviouslyFocusedElement`, vendor/filament/support/resources/js/components/modal.js:180-189) is broken or missing for this modal.
  evidence: Action modals pass `:restores-focus="false"` (vendor/filament/actions/resources/views/action-modal.blade.php:45) and the trap is `x-trap.noreturn`, so that mechanism is intentionally off. Focus return for action modals is done by a different component (`filamentActionModals`, actions/resources/js/components/modals.js), which works when its stored node survives (cancel path and New in the probe).
  timestamp: 2026-10-31T22:25:00Z
- hypothesis: Lazy loading (G-07-2.1) causes the focus loss.
  evidence: Probe waited for the lazy list to load before any key press; the New, Rename and Cancel paths work on the same lazy page. Unrelated to this gap.
  timestamp: 2026-10-31T22:28:00Z
- hypothesis (as a fix route): give Close and Reopen the same `wire:key` through `->extraAttributes(['wire:key' => ...])` so the morph patches one node.
  evidence: Does not work. Rendered HTML still carries the generated key `...actions.close.<hash>` (extra attribute does not override the generated `wire:key`), and the browser probe still ended on BODY. Not a usable lever.
  timestamp: 2026-10-31T22:45:00Z

## Evidence

- timestamp: 2026-10-31T22:05:00Z
  checked: Rendered HTML of the relation manager (Livewire::test html()) for an open and a closed project, before and after callTableAction('close'). Scratch probe, deleted.
  found: Each row action button carries `wire:key="{id}.actions.{name}.{md5}"`. Row 1 before: `...actions.close.a7db...` and `...actions.rename.a7db...`. Row 1 after Close: `...actions.reopen.a7db...` and `...actions.rename.a7db...`. The Rename key is identical before and after; the Close key is replaced by a Reopen key.
  implication: The two toggled buttons are different keyed elements. The Rename button (same key) is the same element across the re-render, which matches Ian's "Rename works".
- timestamp: 2026-10-31T22:10:00Z
  checked: vendor/filament/actions/src/Action.php:378-393 and :885-886, :926-927
  found: `getLivewireKey()` returns null unless the action has a record AND a table, otherwise `"{$livewire->getId()}.actions.{$this->getName()}.{$key}"` with `$key = md5(serialize($this->getContext()))`. The key is emitted as the `wire:key` attribute on the button. The action NAME is part of the key.
  implication: Two actions named close and reopen can never share a key. Header actions (New) have no record, so their key is null, no wire:key, and they are matched by position and survive the morph, which is why New returns focus fine.
- timestamp: 2026-10-31T22:12:00Z
  checked: vendor/livewire/livewire/dist/livewire.js:16006-16010 (morph `key` callback)
  found: `key: el => el.hasAttribute('wire:id') ? ... : el.hasAttribute('wire:key') ? el.getAttribute('wire:key') : el.id`.
  implication: wire:key is the identity used by the morph. Different key means old node removed, new node inserted, no in-place patch.
- timestamp: 2026-10-31T22:15:00Z
  checked: vendor/filament/actions/resources/js/components/modals.js:26-41, 89-93, 132-178
  found: On `sync-action-modals` going from no action to action 0, `rememberPreviouslyFocusedElement()` stores `this.$focus.focused()` (the clicked row button) as target -1. When the stack empties (confirm finished, `newActionNestingIndex === null`) or the modal is cancelled, `restorePreviouslyFocusedElement(-1)` runs two requestAnimationFrames plus $nextTick later and calls `previouslyFocusedElement.focus({ preventScroll: true })` on that stored node.
  implication: After Close is confirmed the stored node is the old Close button, which the morph has already removed. `.focus()` on a detached node does nothing, and the modal that held focus is hidden, so focus ends on <body>. After a cancel, or after New or Rename, the stored node still exists and focus returns.
- timestamp: 2026-10-31T22:17:00Z
  checked: vendor/filament/actions/src/Concerns/InteractsWithActions.php:221-234, 902-909
  found: A modal-less action (Reopen has no confirmation, no schema) runs inside `mountAction()`: `callMountedAction()` then `unmountAction()`. `syncActionModals()` fires with newActionNestingIndex null while the JS component already holds null, so `syncActionModals` returns at modals.js:55 and nothing is remembered or restored.
  implication: For Reopen there is no focus management at all. The focused node is the Reopen button; the morph removes it (key reopen.<hash> becomes close.<hash>), so the browser moves focus to <body>.
- timestamp: 2026-10-31T22:30:00Z
  checked: Real headless Chromium, contact page with 3 projects, real Enter key presses, document.activeElement read after each step (probe tests, deleted). SHIPPED CODE.
  found: |
    - New (header action) then confirm: activeElement = BUTTON "New job". Works.
    - Rename then Escape: activeElement = BUTTON "Rename" (same wire:key as before). Works.
    - Close then Cancel: activeElement = BUTTON "Close" (same wire:key, node survived). Works.
    - Close then Confirm: activeElement = BODY (outerText begins "Skip to content"); row buttons now read Reopen. FAILS.
    - Reopen (Enter on the Reopen button): activeElement = BODY; row buttons now read Close. FAILS.
  implication: Direct, repeatable reproduction of the gap for both Close and Reopen, and the controls separate "trigger node survives" from "trigger node replaced". Ian's "Reopen likely does the same" is confirmed.
- timestamp: 2026-10-31T22:40:00Z
  checked: Counterfactual A in the same browser harness. Throwaway subclass of ProjectsRelationManager replacing the Close and Reopen actions with ONE action `toggle`: label closure (Close or Reopen from isOpen()), `->requiresConfirmation(fn ($record) => $record->isOpen())`, one `->action()` that closes or reopens.
  found: Rendered key stays `...actions.toggle.<hash>` across the flip. Close then Confirm: activeElement = BUTTON "Reopen" with key `actions.toggle.a7db...`. Enter again (no modal): activeElement = BUTTON "Close", same key. Focus stayed on the one node through both transitions.
  implication: One stable action is sufficient on its own, with no JS and no ids. Filament's existing restore code then finds a live node, and the Reopen case needs no restore because the focused node is patched in place.
- timestamp: 2026-10-31T22:48:00Z
  checked: Counterfactual C in the same harness. Keep two actions; give each an id with `extraAttributes(['id' => 'probe-close-{id}' / 'probe-reopen-{id}'])`; in each `->action()` call `$this->js("setTimeout(() => document.getElementById('<other id>')?.focus(), N)")`.
  found: With N = 0 and N = 400, Close confirm ends on BUTTON "Reopen" and Reopen ends on BUTTON "Close". Filament's later detached-node focus() is a no-op and does not steal focus back. Livewire runs `js` effects after the morph (livewire.js:15846-15850 `onMorphed(() => evaluateJsEffects(...))`).
  implication: Works, but needs per-record ids, a JS string in PHP, and a timing wrapper. Larger and more fragile than A.
- timestamp: 2026-10-31T22:50:00Z
  checked: Tooling for a proof test. vendor/livewire/livewire/src/Features/SupportJsEvaluation/TestsJsEvaluation.php:9 (assertJs); existing `visit()` use in tests/Feature/Rooms/*BootTest.php; Pest browser plugin and Playwright chromium installed (the probes above ran under `php artisan test`).
  found: Livewire's tester cannot see focus, but `->html()` exposes the `wire:key` of each row button, `assertJs()` exists for option C, and the Pest browser plugin already in the repo can press Enter and read `document.activeElement`.
  implication: The focus return can be asserted automatically in Pest; only the screen-reader experience stays a hand check.
- timestamp: 2026-10-31T22:52:00Z
  checked: Blast radius for option A. grep for the action names in tests and app.
  found: Only tests/Feature/Filament/ProjectsRelationManagerTest.php lines 117-131 ('closes and reopens from the row') call assertTableActionVisible/Hidden and callTableAction with 'close' and 'reopen' for this list. tests/Feature/Filament/ConversationResourceTest.php uses 'close'/'reopen' for the conversation page's header actions (different class, untouched).
  implication: Option A changes one Phase 7 test (a test 07-05 wrote, not one of D-22's pre-phase tests). The conversation page's close/reopen pair is a header-action pair (no record, so no wire:key) and is out of scope; not browser-checked here.

## Resolution

root_cause: |
  app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:96-113 defines Close and Reopen as two separate row actions toggled by `->visible()`. Filament stamps each table row action with `wire:key = {livewireId}.actions.{actionName}.{md5(context)}` (vendor/filament/actions/src/Action.php:378-393, emitted at :885-886 and :926-927), and Livewire's morph keys elements on `wire:key` (vendor/livewire/livewire/dist/livewire.js:16006-16010). Because the action name is part of the key, the re-render after Close or Reopen removes the old button node and inserts a different one instead of patching it. The control that held focus, and the node Filament stored for focus return, no longer exist:
    - Close: Filament's action-modal focus return (vendor/filament/actions/resources/js/components/modals.js:132-178) stored the Close button when the modal opened, and calls `.focus()` on it after the modal closes (modals.js:174). The node was removed by the morph, so the call does nothing and focus falls to <body> (the next Tab starts at "Skip to content"). Modal-level restore in vendor/filament/support/resources/js/components/modal.js:180-189 is off for action modals (action-modal.blade.php:45 `:restores-focus="false"`, trap is `x-trap.noreturn`).
    - Reopen: no modal, so Filament does no focus management at all (InteractsWithActions.php:221-234; modals.js:55 early return). The focused Reopen button is itself the node the morph removes, so the browser drops focus to <body>.
  New and Rename work because their buttons keep the same node across the re-render (header action has no wire:key; Rename's key `actions.rename.<hash>` is unchanged). Confirmed in real Chromium on the shipped code: Close+Confirm and Reopen end on BODY; New, Rename-Escape and Close-Cancel end back on the trigger.
  Single cause. AND-gate: no. Candidate causes across categories: code (two differently named, differently keyed actions: confirmed); config (a Filament setting to restore focus differently: none exists, action modals hard-code restores-focus=false); environment (browser quirk: ruled out, the cause is the node being removed, which holds in every browser); data (number of projects, status: irrelevant, any row flip does this). Counterfactual with only the action shape changed (one stable `toggle` action) moves focus correctly on both transitions.
fix: (not applied - diagnose-only)
verification: (not applicable - diagnose-only; the counterfactual browser runs above are the pre-fix confirmation)
oracle_type: specified for the browser assertion (WCAG 2.4.3: focus lands on the row's replacement action); derived for the fast guard (the row's toggle button keeps the same wire:key across the flip)
files_changed: []

### Notes for the gap-closure plan (non-binding)

Fix options, smallest first:

1. One stable action that toggles (recommended). Replace the `close` and `reopen` actions with a single `Action::make('toggle')` (any stable name): `->label(fn (Project $record): string => $record->isOpen() ? 'Close' : 'Reopen')`, `->color('gray')`, `->requiresConfirmation(fn (Project $record): bool => $record->isOpen())`, and one `->action()` that calls CloseProject or ReopenProject by `isOpen()` and sends the 'Closed' or 'Reopened' notification. No JS, no CSS, no ids, stock Filament, within D-21. Verified in Chromium: focus stays on the same button through Close-confirm and through Reopen. Cost: the visible text swaps in place, so the screen reader name changes under focus (the notification line still announces the outcome); the action name changes, so ProjectsRelationManagerTest 'closes and reopens from the row' (lines 117-131) is reworded (that test is a Phase 7 test, not a D-22 pre-phase test). Composes with G-07-2.3: the per-row aria-label ("Close Johnny", "Reopen Job 1") is `->extraAttributes(fn ($record) => ['aria-label' => ...])` on the same action; extraAttributes does not change the generated wire:key (confirmed), so it does not disturb this fix. Decide the Close and Reopen colors: one action has one color (Close is gray today; Reopen is default primary); use a color closure or accept one color.
2. Keep two actions and set focus explicitly after the action (fallback if the Close/Reopen must stay separate actions). Give each a per-record `id` through `extraAttributes` and call `$this->js("setTimeout(() => document.getElementById('<other id>')?.focus(), 0)")` in each `->action()`. Verified in Chromium at delays 0 and 400 ms. Cost: a JS string in PHP, per-record ids, a timing wrapper; keep the Filament-generated key untouched. Provable in Livewire with `->assertJs(...)`.
3. Not viable: sharing a wire:key between the two actions with extraAttributes (generated key wins; probe still ended on BODY). Hacking Filament's modal JS or publishing views is outside the stock-Filament contract.

How a test proves it:
- Automated and the real proof: a Pest browser test (the repo already uses `visit()` and axe in tests/Feature/Rooms/*BootTest.php; the probes here ran under `php artisan test`). Sign in with actingAs, `visit()` the contact page with two projects, wait for the list, press Enter on the row's Close with `$page->keys(...)`, press Enter on the confirmation's submit, then `$page->script("() => document.activeElement.textContent.trim()")` and assert it is 'Reopen' (and that it is not BODY); then press Enter on Reopen and assert 'Close'. This is the test that fails today and passes with option 1 or 2. It takes tens of seconds per scenario, so keep it to these two scenarios. Use real key events, not `.click()`, so the keyboard path is what is proven.
- Fast guard in Livewire (no browser): render `->html()` before and after `callTableAction`, extract the row's `wire:key` values, and assert the toggling button's key is identical before and after Close and after Reopen. For option 1 also assert there is no action named 'close' or 'reopen' left and that the toggle shows 'Close' for an open project and 'Reopen' for a closed one. For option 2 use `->assertJs(...)` on the exact focus expression. Livewire cannot observe focus, so these guard the mechanism, not the outcome.
- Stays a hand check (WINDOWS row 38 / UAT retest): VoiceOver announcing the changed button name and the 'Closed' / 'Reopened' notification, and the Safari and Firefox keyboard feel. The cause (a removed node) is browser independent, but only Chromium was driven here.

Related gaps (separate, same file): G-07-2.1 (`$isLazy = false`, see projects-list-not-tab-reachable.md) and G-07-2.3 (per-row aria-label) touch the same class; a single gap-closure plan can do all three in one pass, with the Pest browser test above also proving the list is present on first load.

Blind spots: Chromium only (Playwright headless); no screen reader; the conversation page's own Close/Reopen header pair was not browser-checked (it has no wire:key, so it is expected to morph in place, but that is inference); Reopen's behavior of "where Tab goes next" varies by browser when the focused node is removed, but the focus loss itself (activeElement = BODY) was observed directly.
