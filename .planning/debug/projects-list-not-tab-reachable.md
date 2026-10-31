---
status: diagnosed
trigger: "Gap G-07-2.1 (UAT test 2): The contact screen's project list (the Jobs relation manager) is not reachable by Tab from the top of the page (WCAG 2.1.1). Ian: 'the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page. Every dashboard widget and room section already sets $isLazy = false; ProjectsRelationManager does not.'"
created: 2026-10-31T21:20:00Z
updated: 2026-10-31T21:35:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: CONFIRMED. ProjectsRelationManager inherits Filament's default `$isLazy = true`, so the first server response for the contact page holds only a non-focusable loading placeholder, and the real list (table, New/Close/Reopen/Rename buttons) is fetched only when that placeholder scrolls into view.
test: Rendered the real contact page (GET, two projects) as shipped and again with `ProjectsRelationManager::$isLazy` flipped to false in-process (reflection, no file edits); compared the first HTML.
expecting: As shipped, no project rows or buttons and a placeholder with x-intersect; with the flag false, rows and buttons in the first HTML. Got exactly that.
next_action: none (diagnosis complete); hand ROOT CAUSE FOUND to the gap-closure planner.
bug_class: bohrbug
reasoning_checkpoint: null
tdd_checkpoint: null

## Symptoms

expected: The project list is reachable by Tab from the top of the page; New, Close, Reopen and Rename work without a mouse; confirmations and the rename form take focus and return it; every button has an accessible name.
actual: "Not reachable by Tab: the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page." (Ian, UAT test 2, 2026-10-31)
errors: None reported.
reproduction: Test 2 in .planning/phases/07-the-spine-and-leads/07-UAT.md - keyboard-only walk of a contact's view page with two projects.
started: Since the relation manager was built (07-05); found at UAT 2026-10-31.

## Eliminated

- hypothesis: Another relation manager or lazy component on the contact screen shares the problem.
  evidence: ContactResource::getRelations() returns only [ProjectsRelationManager::class] (ContactResource.php:56-61). ViewContact.php has no overrides. ContactInfolist.php contains no Livewire/lazy components (grep clean). Only one placeholder appears in the page HTML.
  timestamp: 2026-10-31T21:30:00Z
- hypothesis: The list's actions are missing or hidden (e.g. read-only) rather than deferred.
  evidence: `isReadOnly()` returns false (ProjectsRelationManager.php:44-47) and the counterfactual render with lazy off shows Rename in the first HTML; the actions exist and render once the component is not lazy.
  timestamp: 2026-10-31T21:31:00Z
- hypothesis: Livewire's placeholder is itself a tabbable element, so the cause is something else (e.g. missing tabindex).
  evidence: Placeholder is `<div role="status" aria-busy="true" class="fi-section fi-loading-section" style="height: 8rem">` containing `<span class="fi-sr-only">Loading...</span>`. No a, button, input, or tabindex. It is not in the Tab order.
  timestamp: 2026-10-31T21:32:00Z

## Evidence

- timestamp: 2026-10-31T21:22:00Z
  checked: vendor/filament/support/src/Concerns/CanBeLazy.php:9-16 and vendor/filament/filament/src/Resources/RelationManagers/RelationManager.php:57,319-328
  found: RelationManager uses CanBeLazy, which declares `protected static bool $isLazy = true;` and `isLazy()` returning `static::$isLazy`. `RelationManager::getDefaultProperties()` returns `['lazy' => true]` when `static::isLazy()`.
  implication: Every relation manager is lazy unless the subclass overrides the static. Filament 5.10.1 (composer.lock:1191) behaves as the Filament 3/4 docs describe.
- timestamp: 2026-10-31T21:24:00Z
  checked: vendor/filament/filament/src/Resources/Pages/Concerns/HasRelationManagers.php:122-205 (getRelationManagersContentComponent)
  found: With exactly one manager (no tabs), the page mounts `Livewire::make($class, [...$managerLivewireData, ...$manager::getDefaultProperties()])`, so `lazy => true` flows into the Livewire mount params.
  implication: The lazy flag reaches Livewire's SupportLazyLoading for the contact page's single manager.
- timestamp: 2026-10-31T21:26:00Z
  checked: vendor/livewire/livewire/src/Features/SupportLazyLoading/SupportLazyLoading.php:40-110, 153-194
  found: With `lazy` true, mount() calls skipMount() and skipRender(placeholderHtml). The placeholder root gets `x-intersect="$wire.__lazyLoad('...')"` (line 192; `x-init` only for `lazy => 'on-load'` or `defer`). The real component renders only after the browser fires `__lazyLoad` on intersection.
  implication: The table and its buttons do not exist in the DOM until the placeholder enters the viewport.
- timestamp: 2026-10-31T21:27:00Z
  checked: vendor/filament/support/resources/views/components/loading-section.blade.php
  found: Placeholder is a `div role="status" aria-busy="true"` with a `fi-sr-only` span. No focusable markup.
  implication: Tab skips it. With nothing focusable after the breadcrumb, Tab leaves the page, and nothing scrolls the placeholder into view, so the intersect never fires for a keyboard-only user. (WCAG 2.1.1)
- timestamp: 2026-10-31T21:28:00Z
  checked: `php artisan tinker`: ProjectsRelationManager::isLazy(), ::getDefaultProperties(), NewLeads::isLazy()
  found: `{"isLazy":true,"props":{"lazy":true},"widgetLazy":false}`
  implication: ProjectsRelationManager is lazy; the widget with the explicit override is not. Confirms the convention gap.
- timestamp: 2026-10-31T21:33:00Z
  checked: Scratch Pest probe (run from a temporary file under storage/framework, deleted afterwards; no app/tests/resources/config edits, git status clean). Real HTTP GET of ContactResource view page, owner logged in, contact with two projects.
  found: AS SHIPPED: "Job 1"=false, "Job 2"=false, placeholder (fi-loading-section)=true, `x-intersect="$wire.__lazyLoad(...)"`=true, "Rename"=false. COUNTERFACTUAL (ReflectionProperty sets ProjectsRelationManager::$isLazy=false): "Job 1"=true, "Job 2"=true, placeholder=false, x-intersect=false, "Rename"=true.
  implication: Direct, repeatable proof that the flag alone decides whether the list is in the first HTML. Setting `protected static bool $isLazy = false;` on the class is sufficient for this gap.
- timestamp: 2026-10-31T21:34:00Z
  checked: Convention in the repo (grep isLazy): app/Filament/Widgets/{TodaysConversations,DraftsWaiting,PausedConversationsWaiting,NewLeads}.php and app/Rooms/RoomSection.php all set `protected static bool $isLazy = false;`. .planning/milestones/v1.0-phases/02-text-front-desk/02-23-SUMMARY.md:52 and .planning/phases/04-room-standard/04-RESEARCH.md:354,570 record the reason (lazy widgets send a placeholder first).
  implication: The project already knew the lazy default is wrong for first-response content; 07-05 added the relation manager without carrying the convention over.
- timestamp: 2026-10-31T21:35:00Z
  checked: tests/Feature/Filament/ProjectsRelationManagerTest.php (all 12 tests); grep for withoutLazyLoading / disableWhileTesting in tests/ and app/
  found: `projectsListFor()` uses `Livewire::test(ProjectsRelationManager::class, ['ownerRecord'=>..., 'pageClass'=>...])`, which passes no `lazy` param, so the placeholder is never exercised. 'shows on the contact page' (lines 98-109) does `$this->get(...)->assertOk()` and `Livewire::test(ViewContact::class)->assertSeeLivewire(ProjectsRelationManager::class)`; both pass on a placeholder, and the comment on line 104 states the manager is lazy-loaded as if that were fine. No test greps the first response for a project row.
  implication: Why not caught: no test asserts that the list is in the first HTML. Also, automated checks cannot see keyboard reachability; this is the class UAT is for.

## Resolution

root_cause: |
  app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php (class declaration, line 28; no `$isLazy` override anywhere in the class) inherits Filament's default `protected static bool $isLazy = true` (vendor/filament/support/src/Concerns/CanBeLazy.php:9). ContactResource::getRelations() (ContactResource.php:56-61) registers it as the page's only relation manager, so HasRelationManagers::getRelationManagersContentComponent() mounts it with `lazy => true`. Livewire's SupportLazyLoading then sends a non-focusable `div role="status" aria-busy="true"` placeholder carrying `x-intersect="$wire.__lazyLoad(...)"` and renders the table and its New/Close/Reopen/Rename buttons only after the placeholder scrolls into view. A keyboard user cannot Tab to a placeholder, and nothing focusable lies between the breadcrumb and it, so Tab leaves the page and the list never loads or becomes reachable. Single cause; AND-gate answer: no (counterfactual with only that flag flipped puts the list and buttons in the first HTML).
  Candidate causes considered across categories: code (the missing `$isLazy = false` - confirmed); config (a panel-wide lazy default - none exists, Filament has only the per-class static); environment (browser/IntersectionObserver differences - not needed to explain it, the server HTML already lacks the list); data (number of projects - irrelevant, two projects or none renders the same placeholder).
fix: (not applied - diagnose-only)
verification: (not applicable - diagnose-only; counterfactual render above is the pre-fix confirmation)
oracle_type: specified (WCAG 2.1.1 plus the repo convention that first-response content is not lazy); a rendered-first-response assertion is a derived/behavioural oracle
files_changed: []

### Notes for the gap-closure plan (non-binding)
- Fix direction: add `protected static bool $isLazy = false;` to ProjectsRelationManager, matching the four widgets and RoomSection (docblock-style comment saying why: first response must carry the list so Tab can reach it).
- Proof test (tests-first, in tests/Feature/Filament/ProjectsRelationManagerTest.php): create a contact with two projects (OpenProject::byHand twice), then `$this->get(ContactResource::getUrl('view', ['record' => $contact]))->assertOk()->assertSee('Job 1')->assertSee('Job 2')->assertDontSee('__lazyLoad', escape: false)` (and optionally `assertSee('Rename')` / `assertDontSee('fi-loading-section')`). Use the real HTTP GET, not `Livewire::test(...)`: the Livewire test mount passes no `lazy` param, and `Livewire::withoutLazyLoading()` is not used anywhere in the repo, so the GET path is the one that sees the placeholder. A cheap secondary assertion is `expect(ProjectsRelationManager::isLazy())->toBeFalse()` and `getDefaultProperties()` not having a `lazy` key. The probe confirmed the GET assertion fails today (no Job rows, `__lazyLoad` present) and would pass with the flag false.
- Update the now-misleading comment at tests/Feature/Filament/ProjectsRelationManagerTest.php:104 ("Filament draws a relation manager as its own lazy-loaded component, so the page itself holds only its place"); the 'shows on the contact page' test should assert the rows, not just `assertSeeLivewire`.
- Gaps G-07-2.2 (focus after Close/Reopen) and G-07-2.3 (per-row accessible names) are in the same file but are separate mechanisms (row-action replacement and `extraAttributes`/aria-label); this fix does not address them. Once the list is in the first HTML, the keyboard walk should be repeated.
- Blind spot: I did not drive a real browser. The claim that Tab never scrolls the placeholder into view rests on the placeholder having no focusable element (verified in HTML), Alpine's x-intersect semantics, and Ian's reproduction. If the contact page were short enough for the placeholder to be in the initial viewport it would load on its own, so reproduction depends on page length; the fix removes the dependency either way.
