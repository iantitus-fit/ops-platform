# Phase 04 — UI Review

**Audited:** 2026-10-22
**Baseline:** abstract 6-pillar standards (no UI-SPEC.md)
**Screenshots:** not captured (no live render: only the test-only pretend room renders; code-only audit)
**Interaction captures:** off (workflow.ui_interaction_capture is false)

Scope: resources/views/filament/rooms/section.blade.php, app/Rooms/RoomSection.php, ContactInfolist room-entry badge, CustomerCard text. Styling and D-21 wording are held for the phone design pass (02.1 D-48) and the Phase 11 wording pass; findings below are inputs to those.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 2/4 | Empty line is stilted and gives no next step; "Room entry" fallback badge is jargon |
| 2. Visuals | 2/4 | Bare paragraphs in a Filament section; no hierarchy or empty-state affordance |
| 3. Color | 3/4 | Nothing custom: Filament defaults only; no hardcoded colors; nothing to contradict |
| 4. Typography | 3/4 | Inherits Filament section heading and body; no overrides |
| 5. Spacing | 2/4 | Consecutive `<p>` lines rely on browser defaults inside the section |
| 6. Experience Design | 2/4 | No loading/error state; empty state is text only; but visibility is sync-driven and axe passes |

**Overall: 14/24**

---

## Top 3 Priority Fixes

1. **Empty-state line is awkward and not actionable** — an owner sees "Pretend: nothing set up yet. Add a file to pretend/ or x to start." and must work out where and how to add files; the "a file to folder/" and "A or B" joins read like generated text. WARNING. Fix in Phase 11 wording pass: name the file kind in plain words, say where it goes, and link to setup help.
2. **Section content has no structure** — `section.blade.php:5-9` renders bare `<p>` lines, so a non-empty room has no list, no emphasis, no spacing control. WARNING. In the phone design pass render `$lines` as a `<ul>` (or Filament description/stat component) with explicit gap utilities, and give the empty state an icon and heading treatment (`x-filament::section` supports an icon).
3. **Generic "Room entry" badge in the contact timeline** — `ContactInfolist.php:93` falls back to "Room entry" when the room's code is no longer registered; owners get a meaningless label with no hint why. WARNING. Use wording such as "Entry from a room that is switched off" in the wording pass, or add a tooltip.

Further minor items: (4) no error or "could not load" state if `ActiveConfig` throws inside `getViewData`; (5) the `data-room-section` wrapper div adds a nesting layer with no visible purpose beyond test targeting; (6) `ucfirst($label)` is applied twice (heading and again inside `emptyLine`, producing "Pretend: nothing set up yet" with the heading already shown directly above, so the room name repeats); (7) the CustomerCard text is markdown for AI and file readers, not UI, but its "Generated from the database. Read-only" sentence is clear and fine.

---

## Detailed Findings

### Pillar 1: Copywriting (2/4)
- RoomSection.php:55 — `"{label}: nothing set up yet. Add {things} to start."`. Plain and without exclamation points or em dashes (D-21 met). Weaknesses: repeats the heading shown above it (section.blade.php heading + line prefix); "Add a file to rooms/x/" exposes folder paths to the owner without saying what the file is for; the two-or-more join ("a, b or c") has no Oxford-style clarity. Marked as placeholder by D-21, so WARNING not BLOCKER.
- ContactInfolist.php:93 — "Room entry" fallback is internal vocabulary.
- ContactInfolist placeholders "(no name)", "(no phone)" are pre-existing and consistent.
- No generic Submit/OK labels introduced by this phase.

### Pillar 2: Visuals (2/4)
- section.blade.php uses `x-filament::section` with a heading only: no icon, no description, no focal element. Empty and populated states look alike (plain text).
- No icon-only buttons; no aria gaps found (no aria/role attributes needed or present; axe passes per RoomAccessibilityTest).
- Held for the phone design pass; not a defect against the phase contract.

### Pillar 3: Color (3/4)
- No custom color classes, no hex or rgb in the room view or RoomSection. Inherits Filament theme. Contact badges use default badge color (`->badge()` with no color mapping), so room entries are not visually distinguished from other timeline types (minor).

### Pillar 4: Typography (3/4)
- No font-size or weight classes. Heading comes from Filament section. Only one text level in body, so hierarchy between room lines is flat. Minor.

### Pillar 5: Spacing (2/4)
- Multiple `<p>` lines with no spacing utilities (section.blade.php:8). Filament's reset may collapse paragraph margins, so lines could run together; unverified without a render. Not confirmed visually, hence 2 not 1.
- `columnSpan = 'full'` and sort 20 are sensible placement choices.

### Pillar 6: Experience Design (2/4)
- Good: visibility is driven live by `canView()` against the active version (RoomSection.php:28), `$isLazy = false` so the first HTML carries the section, D-21 empty state is rendered by the platform for every room, and axe WCAG 2.2 AA check exists.
- Gaps: no loading or error handling in `getViewData` (RoomSection.php:74-87); no action or link from the empty state; no confirmation-needed actions exist here. Room-entry badge degrades gracefully when a room is removed, which is good.
- Interaction states were not captured; findings are code-derived.

---

## Files Audited
- /Users/iantitus/Projects/ops-platform/resources/views/filament/rooms/section.blade.php
- /Users/iantitus/Projects/ops-platform/app/Rooms/RoomSection.php
- /Users/iantitus/Projects/ops-platform/app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
- /Users/iantitus/Projects/ops-platform/app/Records/CustomerCard.php (first 80 lines)
- /Users/iantitus/Projects/ops-platform/.planning/phases/04-room-standard/04-CONTEXT.md
