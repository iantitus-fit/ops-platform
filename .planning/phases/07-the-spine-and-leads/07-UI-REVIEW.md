---
phase: 07-the-spine-and-leads
audited: 2026-10-31
baseline: 07-UI-SPEC.md (stock Filament 5, no design work, D-21 as amended)
screenshots: not captured (no dev server answered; code-only audit)
overall: 19/24
---

# Phase 7 UI Review

## Headline

Phase 7 adds two screens: the contact screen's projects list (`ProjectsRelationManager`) and the leads room's dashboard section (`LeadsSection`, `leads-section.blade.php`). Both keep to the contract: Filament components, no custom CSS, tokens or colors, escaped output, keyboard-reachable row links and absolute dates in `<time datetime>`. Scores grade conformance to that contract and basic UX and accessibility, not visual polish, which belongs to Phase 11 (02.1 D-48).

## Scores

| Pillar | Score | Note |
|---|---|---|
| 1. Copywriting | 3/4 | Contract strings present. "Closed", "Reopened" and "Renamed" do not name the project; the too-long rename shows Filament's stock message (finding 3). |
| 2. Visuals | 3/4 | Leads rows are a bare `<ul>` of run-on prose; the Open and Closed badge looks the same for both states. |
| 3. Color | 3/4 | No hardcoded colors; Close is gray as specified. The status badge has no color per state. |
| 4. Typography | 4/4 | Filament defaults only. |
| 5. Spacing | 3/4 | No custom spacing; the bare `<ul>` and `<p>` rely on Preflight defaults. |
| 6. Experience Design | 3/4 | Empty, failure, 20-row cap and close confirmation all handled. Keyboard and screen reader checks are carried to UAT. |

## Findings (all WARNING, none blocking)

1. **Leads rows read as one run-on line.** `resources/views/filament/rooms/leads-section.blade.php` prints each row as "Name: Job 3, opened 2026-10-31 14:02, source X" in an unstyled `<ul>` (Preflight strips bullets and spacing). Layout is Phase 11's (D-48); log it there. The VoiceOver walk (WINDOWS 41) should confirm the row reads sensibly.
2. **The status badge does not differ by state.** The `status` column in `ProjectsRelationManager.php` is `->badge()` with no `->color()`, so Open and Closed differ by text only. Text alone meets WCAG 1.4.1; a per-state color is a Phase 11 styling choice.
3. **The too-long rename message is Filament's, not the contract's.** `TextInput::maxLength(RenameProject::MAX_LENGTH)` refuses 101 characters on the form with Filament's stock validation message (`tests/Feature/Filament/ProjectsRelationManagerTest.php:156` asserts the `max` error), so the owner never sees "A name can be at most 100 characters." That line is only reachable by calling `RenameProject` directly. 07-UI-SPEC.md's Error state row names it as the screen's copy, so the spec and the screen disagree. Either the field gets `->validationMessages(['max' => ...])` or the spec row is corrected; Ian's call.

## Minor

- Notifications: "Opened {label}" names the project; "Closed", "Reopened" and "Renamed" do not.
- "Open", "Closed", "Opened by", "New lead" and "(open)" are fixed English placeholders, consistent with the contract until the wording pass.
- The NewLeads widget's "No new leads this week" is outside Phase 7.

## Positives

- Row links are `<x-filament::link>`, reachable by keyboard; dates are absolute inside `<time datetime>` (D-15).
- All values are escaped `{{ }}`; notification titles that can hold owner text pass through `e()`.
- No Filament create, edit, delete or associate action; every write goes through the four project actions.

## Registry

shadcn not initialized; no third-party registry or UI package.
