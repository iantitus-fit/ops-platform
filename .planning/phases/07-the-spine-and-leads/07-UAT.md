---
status: diagnosed
phase: 07-the-spine-and-leads
source: [07-VERIFICATION.md]
started: 2026-10-31T18:05:00Z
updated: 2026-10-31T21:32:16.651Z
---

## Current Test

[testing complete]

## Tests

### 1. Postgres leg of CI green for the whole phase (WINDOWS rows 34, 35, 36, 37, 39, 40, 42, 43)
expected: After Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular ProjectWordTest, ProjectRecordTest ('refuses a second project opened by a new lead for one contact'), ProjectCardTest, CardInjectionTest, ProjectActionsTest, ProjectWritesTest, ProjectsRelationManagerTest, LeadsBootTest, LeadsSectionTest, and OpenOnNewLeadTest ('loses a race without an error and leaves one project'). Roadmap criterion 4 asks for both databases; SQLite is green at 2417 passed.
result: pass

### 2. Keyboard-only and VoiceOver walk of a contact screen with two projects (WINDOWS row 38)
expected: The list is reachable; New, Close, Reopen and Rename work without a mouse; the New and Close confirmations and the rename form take focus and return it; every button has an accessible name.
result: issue
reported: |
  Issues, all on the contact screen's Jobs list:
  1. Not reachable by Tab: the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page. Every dashboard widget and room section already sets $isLazy = false; ProjectsRelationManager does not. Blocks keyboard use (WCAG 2.1.1).
  2. Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same. Focus should land on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3).
  3. Ian wants each row button to name its job for screen readers: "Close Johnny", "Reopen Job 1", "Rename Job 2", using the job's label, with the visible text unchanged.
  Working: New job and its confirmation by keyboard with focus returning to New job; the Rename form takes focus and returns it to Rename; Reopen works; VoiceOver names every button.
severity: blocker

### 3. Keyboard-only and VoiceOver walk of /admin with three open projects (WINDOWS row 41)
expected: Each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date.
result: pass

## Summary

total: 3
passed: 2
issues: 1
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-07-2.1
  truth: "The contact screen's project list is reachable by Tab from the top of the page (WCAG 2.1.1)"
  status: failed
  reason: "User reported: Not reachable by Tab: the Jobs list is a lazy-loaded relation manager that loads only when scrolled into view, so tabbing from the top goes from the breadcrumb straight out of the page. Every dashboard widget and room section already sets $isLazy = false; ProjectsRelationManager does not. Blocks keyboard use (WCAG 2.1.1)."
  severity: blocker
  test: 2
  root_cause: "ProjectsRelationManager has no $isLazy override, so it inherits Filament's default true (vendor/filament/support/src/Concerns/CanBeLazy.php:9) and is mounted lazy (RelationManager::getDefaultProperties). The first response carries only Livewire's placeholder (role=status, x-intersect __lazyLoad, nothing focusable), so the table and its buttons exist only once scrolled into view and Tab leaves the page first. Probe: a real GET of the contact page lacks Job 1, Job 2 and Rename; with only $isLazy=false they are present. The widgets and RoomSection already set $isLazy = false (02-23, 04-RESEARCH); 07-05 did not carry it over. Tests missed it because Livewire::test never passes lazy and the page test only asserts assertSeeLivewire."
  artifacts:
    - path: "app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php"
      issue: "missing protected static bool $isLazy = false"
    - path: "tests/Feature/Filament/ProjectsRelationManagerTest.php"
      issue: "no assertion that the list is in the first response; lazy-load comment at line 104 goes stale"
  missing:
    - "protected static bool $isLazy = false on ProjectsRelationManager"
    - "test: real GET of the contact view page sees both project labels and Rename, and no __lazyLoad"
  debug_session: .planning/debug/projects-list-not-tab-reachable.md

- gap_id: G-07-2.2
  truth: "After Close or Reopen is confirmed, focus lands on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3)"
  status: failed
  reason: "User reported: Focus lost after Close: confirming Close replaces the row's Close button with Reopen, so focus has nowhere to return and drops to the top of the page (the next Tab lands on Skip to content). Reopen likely does the same. Focus should land on the row's replacement action (Reopen after Close, Close after Reopen), never the top of the page (WCAG 2.4.3)."
  severity: major
  test: 2
  root_cause: "Close and Reopen are two actions toggled by ->visible(). Filament keys each row action's button by wire:key = {id}.actions.{actionName}.{md5(context)} (vendor/filament/actions/src/Action.php:378-393), and Livewire's morph uses wire:key as identity, so after Close or Reopen the morph removes the focused button and inserts a different node. Close: filamentActionModals (vendor/filament/actions/resources/js/components/modals.js:132-178) calls .focus() on the stored, now detached, Close button, so focus falls to body. Reopen: no modal, the focused node itself is removed. Real Chromium: Close+Confirm and Reopen both land on BODY; New, Rename and Close+Cancel keep focus. A shared wire:key via extraAttributes does not work (the generated key wins)."
  artifacts:
    - path: "app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:96-113"
      issue: "two differently named, therefore differently keyed, row actions swap on each toggle"
    - path: "tests/Feature/Filament/ProjectsRelationManagerTest.php:117-131"
      issue: "close/reopen test names the two actions; nothing guards the key or focus"
  missing:
    - "one stable row action (e.g. toggle) whose label, confirmation and action follow isOpen(), so its wire:key survives the morph (recommended); fallback: per-record ids plus $this->js focus call"
    - "decision on the single action's colour (Close was gray)"
    - "Pest browser test with real Enter keys: Close+Confirm lands on Reopen, Enter on it lands on Close; Livewire guard that the button's wire:key is unchanged across toggles"
    - "VoiceOver/Safari retest of the walk stays a hand check (WINDOWS row 38)"
  debug_session: .planning/debug/focus-lost-after-close-reopen.md

- gap_id: G-07-2.3
  truth: "Each row action's accessible name names its project by label (\"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\"), visible text unchanged"
  status: failed
  reason: "User reported: Ian wants each row button to name its job for screen readers: \"Close Johnny\", \"Reopen Job 1\", \"Rename Job 2\", using the job's label, with the visible text unchanged."
  severity: major
  test: 2
  root_cause: "The row actions carry only a label. Filament renders table row actions as link buttons whose content is an aria-hidden icon plus the label span and emits no aria-label (vendor/filament/actions/src/Action.php:872-929; aria-label only for hiddenLabel(), vendor/filament/support/src/View/Concerns/CanGenerateLinkHtml.php:103-106), so every row's buttons are named just Close/Reopen/Rename. The project label is computed only for the first column. extraAttributes(Closure) receives the row record and gives the wanted name (probe: 'Close Job 1', 'Rename Job 3'; Label in Name passes since the name starts with the visible word) without changing wire:key, so it composes with G-07-2.2. Filament does not escape extraAttributes (HasExtraAttributes.php:21-22; merged escape:false at Action.php:1172): without e() a hostile project name injected a script element, so e() is mandatory. The record carries number via withNumber(), no N+1."
  artifacts:
    - path: "app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:95-135"
      issue: "row actions have no per-record accessible name"
  missing:
    - "->extraAttributes(fn (Action $action, Project $record) => ['aria-label' => e(verb.' '.label)]) on the toggle and Rename actions, the label from one shared helper with the label column"
    - "tests: aria-label=\"Close Job 1\" / Reopen / Rename per row, a hostile renamed project escaped with no injected markup, name updates after rename, and the name starts with the visible verb"
    - "VoiceOver hearing 'Close Johnny, button' stays a hand check; the confirmation heading naming the project is optional (note only)"
  debug_session: .planning/debug/row-actions-unnamed-project.md
