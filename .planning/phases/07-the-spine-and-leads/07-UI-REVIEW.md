---
phase: 07-the-spine-and-leads
audited: 2026-11-01
previous_audit: 2026-10-31 (19/24, before gap plan 07-09)
baseline: 07-UI-SPEC.md (stock Filament 5, no design work, D-21 as amended; amended 2026-11-01 by 07-09)
screenshots: not usable (the only server answering was Vite on :5173, whose root is Vite's placeholder page, not the app; code-only audit)
interaction_captures: off
overall: 20/24
---

# Phase 7 — UI Review

**Audited:** 2026-11-01 (re-audit after gap plan 07-09)
**Baseline:** 07-UI-SPEC.md (stock Filament 5, no design work, D-21 as amended)
**Screenshots:** captured 3/3 from `http://localhost:5173`, but that is the Vite dev server's placeholder page, not the app (APP_URL is `http://localhost:8000` and the contact screen sits behind login), so the visual pillars are graded from code
**Interaction captures:** off

Phase 7 adds two screens: the contact screen's projects list (`ProjectsRelationManager`) and the leads room's dashboard section (`LeadsSection`, `leads-section.blade.php`). Both keep to the contract: Filament components only, with no custom CSS, tokens or colours, escaped output, keyboard-reachable row links, and absolute dates in `<time datetime>`. The scores grade conformance to that contract plus basic UX and accessibility. They do not grade visual polish, which belongs to Phase 11 (02.1 D-48).

Gap plan 07-09 changed only `ProjectsRelationManager.php` (69 lines added, 13 removed). The leads section is unchanged since the first audit.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | The new confirmations name the job in plain words ("Close Johnny?"). "Closed", "Reopened" and "Renamed" still do not name the project. |
| 2. Visuals | 3/4 | Unchanged. Each leads row is one run-on line in a bare `<ul>`, and the Open/Closed badge looks the same for both states. |
| 3. Color | 3/4 | The per-row colour keeps the as-built look: gray for Close, primary for Reopen. The status badge still has no colour per state. |
| 4. Typography | 4/4 | Filament defaults only. |
| 5. Spacing | 3/4 | Unchanged. The bare `<ul>` and `<p>` in the leads section rely on Preflight defaults. |
| 6. Experience Design | 4/4 (was 3/4) | All three UAT gaps are closed and proven by tests: the list is drawn with the page, focus stays on the row button (real-browser Enter test), and every row button names its project. A press from a stale page is safe. |

**Overall: 20/24** (was 19/24; Experience Design +1)

---

## Top 3 Priority Fixes

All three are warnings, none blocking, and all fall in Phase 11's wording and styling pass unless Ian pulls one forward.

1. **Success notifications do not say which project changed.** The Close and Reopen toggle sends "Closed" or "Reopened" (`ProjectsRelationManager.php:136`, `:143`), and Rename sends "Renamed" (`:165`). An owner with several jobs on one contact has to read the table to see which row moved, and a screen reader announces the bare word. Fix: title them `e('Closed '.$this->rowLabel($record, $word))` and the same for the other two, as "Opened {label}" already does at `:100`.
2. **Each leads row reads as one run-on line.** `resources/views/filament/rooms/leads-section.blade.php` prints "Name: Job 3, opened 2026-10-31 14:02, source X" in an unstyled `<ul>`. Fix in Phase 11 (D-48): a definition-style row or a Filament table. The VoiceOver walk (WINDOWS entry 41) should confirm the row reads sensibly until then.
3. **The status badge does not differ by state.** The `status` column (`:71-73`) is `->badge()` with no `->color()`. Text alone meets WCAG 1.4.1. A per-state colour (for example success for Open, gray for Closed) is a Phase 11 styling choice.

---

## Detailed Findings

### Pillar 1: Copywriting (3/4)

New in 07-09:
- **Confirmations (`:121-125`)** follow the contract's Destructive confirmation row word for word:
  - "Close {label}?" with "{label} will be marked closed. You can reopen it at any time."
  - "Reopen {label}?" with "{label} will be marked open again. You can close it at any time."
  - The submit buttons read Close or Reopen. There are no exclamation points or em dashes in any string in the file.
- **Visible button text** stays Close, Reopen and Rename. The accessible name adds the project ("Close Job 1"), with the visible word first, so voice control's "click Close" still matches (WCAG 2.5.3).

Carried:
- "Closed", "Reopened" and "Renamed" do not name the project (Top fix 1).
- The Rename modal's heading is Filament's stock "Rename", without the job. The button's accessible name does carry it.
- "Open", "Closed", "Opened by", "New lead" and "(open)" are fixed English placeholders. That is consistent with the contract until the wording pass.
- First audit's finding 3, the too-long rename message, was fixed 2026-10-31. The field carries `validationMessages(['max' => 'A name can be at most 100 characters.'])` (`:152`).

### Pillar 2: Visuals (3/4)

There is no change on the contact screen beyond one button per row where there used to be two in turn (the same button now changes its label). The leads section is unchanged (Top fix 2), and the badge looks the same in both states (Top fix 3). The screenshots could not show the app (see header).

### Pillar 3: Color (3/4)

`->color(fn ... => $record->isOpen() ? 'gray' : 'primary')` (`:118`) keeps the look the two buttons had before 07-09, as the UI-SPEC Color line records. The colour closure does not change the button's `wire:key`, and the wire:key guard test proves it. There are no hardcoded colours, inline styles or arbitrary Tailwind values (`style=`, hex colours and `text-[`/`bg-[` searches find nothing). The status badge has no colour per state (Top fix 3).

### Pillar 4: Typography (4/4)

Filament defaults only. 07-09 adds no markup.

### Pillar 5: Spacing (3/4)

Unchanged. There is no custom spacing anywhere. The leads section's bare `<ul>` and `<p>` rely on Preflight resets (Phase 11).

### Pillar 6: Experience Design (4/4, was 3/4)

The first audit kept this at 3/4 because the keyboard and screen-reader behaviour was unproven and carried to UAT, and UAT test 2 then found three gaps. 07-09 closes all three, each with tests.

**G-07-2.1 (WCAG 2.1.1): the list is drawn with the page.**
- `protected static bool $isLazy = false;` (`:40`).
- The page's first response holds the rows and the buttons, with no `__lazyLoad` placeholder.
- Tests: "shows on the contact page in its first response, so Tab can reach the list", "is not lazy" and "carries the names in the contact page's first response".

**G-07-2.2 (WCAG 2.4.3): focus stays on the row button.**
- One `toggle` action replaces `close` and `reopen`, so each row has one stable `wire:key`.
- `ProjectsRelationManagerKeyboardTest.php` presses real Enter keys in Chromium. After Close, focus lands on "Reopen Job 1"; after Reopen, on "Close Job 1". It never falls to `BODY`.

**G-07-2.3 (WCAG 2.5.3, 4.1.2): every row button names its project.**
- `rowButtonName()` (`:190-193`) builds the name from the action's own label and the shared `rowLabel()`, so the label column and the buttons cannot disagree.
- The name is escaped once with `e()`, because Filament prints extra attributes unescaped. The test "escapes an owner-typed name in a button's name" covers it.

**A press from a stale page is safe (T-07-26).**
- The confirmation is decided from the project's state when the button is pressed.
- The direction it states rides to the submit in a `Hidden` field (`:126-131`).
- A page left open in a second tab therefore shows what will actually happen, and the submit does what the dialog said or nothing. Two tests cover this.

**Other states are unchanged and still covered:** empty states, the leads section's failure line, the 20-row cap with its total line, and the rename refusal shown as plain text.

**Residual:** the live VoiceOver and Safari retest is open as WINDOWS entry 44, and CI is open as entry 45. By project rule those are UAT hand checks, not audit defects. Since 07-09, a failing projects query fails the whole contact page rather than one lazy panel. That was accepted as AR-7-04 (T-07-27).

---

## Positives

- Row links are `<x-filament::link>`, reachable by keyboard. Dates are absolute, inside `<time datetime>` (D-15).
- All values are escaped with `{{ }}`. Notification titles and the new aria-labels that can hold owner text go through `e()`.
- There is no Filament create, edit, delete or associate action. Every write goes through the four project actions (D-11).
- 07-09 used no custom view, CSS, JavaScript string or published Filament file (D-21).

## Registry

shadcn is not initialized, and there is no third-party registry or UI package. The registry audit was skipped.

---

## Files Audited

- `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php` (07-05, changed by 07-09)
- `resources/views/filament/rooms/leads-section.blade.php`, `app/Rooms/Leads/LeadsSection.php` (07-07, unchanged since the first audit)
- `tests/Feature/Filament/ProjectsRelationManagerTest.php`, `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php`
- `.planning/phases/07-the-spine-and-leads/07-UI-SPEC.md`, `07-09-PLAN.md`, `07-09-SUMMARY.md`, and the first audit (2026-10-31)
