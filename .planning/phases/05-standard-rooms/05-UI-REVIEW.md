---
phase: 05-standard-rooms
baseline: abstract 6-pillar standards (no UI-SPEC)
screenshots: not captured
overall: 15/24
reviewed: 2026-10-26
---

# Phase 05 UI Review

Code-only audit. No dev server answered, so nothing was rendered: every visual claim is
inferred from markup. The auditor read the section view, `RoomSection` and the five
`*Section.php` files plus `05-UAT.md`; it did not read the plans, summaries or CONTEXT, so
its fixes have not been checked against locked decisions (the read-only sections and the
deferred wording pass, 04 D-21, are deliberate). Advisory only: nothing here is a Phase 5 gap.

## Scores

| Pillar | Score | Key finding |
|---|---|---|
| Copywriting | 2/4 | Terse "Label: value" lines and a developer-facing empty line. The wording pass is deferred (04 D-21). |
| Visuals | 2/4 | Every room is a flat stack of plain `<p>` lines with no hierarchy, status or icon. |
| Color | 3/4 | No custom color anywhere, so it inherits Filament's themed tokens. Nothing is wrong, and nothing signals state. |
| Typography | 3/4 | No size or weight classes, so it inherits Filament and stays consistent. Values are not set apart from labels. |
| Spacing | 3/4 | `<p>` lines inside `x-filament::section` with no spacing classes. Rhythm between lines is the browser default. |
| Experience Design | 2/4 | Read-only status lines with no next step. No state signalling and no failure path. |

Color, Typography and Spacing score 3 because the code is clean, not because the result was seen.
Treat them as unverified until screenshots exist.

## Top fixes

1. **Status is plain text with no affordance.** "FAQ: not added yet", "Not connected to Google:
   this room runs on a stand-in and sends nothing." and the empty lines describe a gap with no
   action, link or visual weight. The Google "not connected" line is the most consequential
   state and reads like any other sentence. Fix: render label/value pairs as a `dl` or a
   Filament badge or description, and give warning-type lines a distinct treatment that does
   not rely on color alone.
2. **Empty lines are developer-oriented.** `RoomSection::emptyLine()` builds "Money: nothing set
   up yet. Add notes/money.md to start." from raw contract paths, with the registry label run
   through `ucfirst`. The code comment already calls it a placeholder. Fix: owner-facing wording
   per room, and drop the repeated room name (already logged, 04 D-21).
3. **No visual hierarchy across five sibling sections.** All five are `columnSpan = 'full'` and
   the same shape, so a one-line section ("Content brief: in place") takes a full-width card.
   Fix: a two- or three-column grid for short sections, or one "Rooms" overview card.
4. **Labels and values are joined into one string in PHP.** "Lead sources: 3", "Offers live
   today: a, b". They can't be styled or announced as pairs (acceptable for AA). Fix: return
   `['label' => ..., 'value' => ...]` from `contentLines()` and render semantically.
5. **Lists are bare `<p>` elements.** "Offers live today" joins names with commas, so a long list
   wraps unstructured. Fix: a `ul` for multi-item values.
6. **No per-section error handling (minor; not checked against the code by the orchestrator).**
   `isLazy = false` is deliberate. `GoogleBusinessProfileSection` already guards a bad driver
   setting, but `RoomSection` has no catch around `getViewData()`, so another ActiveConfig
   failure could take the whole dashboard down. Fix: catch per section and show a one-line
   "could not read this room" message.

## What passes

- UAT confirmed a real H2 per region, DOM order matching screen order, 200% zoom with no
  clipping, and no focusable elements.
- Color is token-inherited, with no hardcoded colors in the section code or view.
- Type and spacing add no non-standard values.

## Not verified (no rendered output)

Contrast of the default section text, how a long offers line wraps at 375 and 768 px, dark-mode
appearance, and whether the `data-room-section` wrapper affects Filament's grid.

## Files audited

- `resources/views/filament/rooms/section.blade.php`
- `app/Rooms/RoomSection.php`
- `app/Rooms/Money/MoneySection.php`
- `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php`
- `app/Rooms/Marketing/MarketingSection.php`
- `app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php`
- `app/Rooms/ContentGeneration/ContentGenerationSection.php`
- `.planning/phases/05-standard-rooms/05-UAT.md`
