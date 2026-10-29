---
phase: 06-front-desk-and-team-rooms
audited: 2026-10-28
baseline: abstract 6-pillar standards (no UI-SPEC.md)
screenshots: not captured (dashboard is behind login; code-only audit)
overall: 18/24 (standing-state grade; Phase 6 adds nothing to it)
---

# Phase 6 UI Review

## Headline

Phase 6 changed no screen. `git diff main...HEAD` touches no file in `app/Filament/` or `resources/`. The four dashboard widgets (`DraftsWaiting` sort 1, `PausedConversationsWaiting` sort 2, `NewLeads` 3, `TodaysConversations` 4) were not edited. `Room::$claimedWidgets` is a pointer only: nothing reads it at run time and it never reaches the panel. Widget order and headings come from the existing `$sort` and `->heading()` values.

The scores grade the widgets as they stand, not Phase 6's work.

## Scores

| Pillar | Score | Note |
|---|---|---|
| 1. Copywriting | 3/4 | Plain, owner-facing headings and empty states. "(no name)" is a stand-in label. |
| 2. Visuals | n/a (3/4) | Nothing phase-specific. Full-width tables with headings; rows link through `recordUrl`. |
| 3. Color | n/a (3/4) | No color code in the phase; Filament defaults. |
| 4. Typography | n/a (3/4) | Filament defaults. |
| 5. Spacing | n/a (3/4) | Filament table defaults. |
| 6. Experience Design | 3/4 | Empty states on all four widgets; `$isLazy = false` avoids a loading flash; no error state beyond Filament's defaults. |

## Findings (none are Phase 6 regressions)

1. **Row click may be the only route into a conversation (WCAG 2.1.1, 4.1.2).** `PausedConversationsWaiting` and `DraftsWaiting` use `recordUrl` with no explicit link column. Check at the keyboard-only walk that each row exposes a focusable, named link; if not, put a link on the name column. Not verified in a browser.
2. **"Waiting" column is relative time only (`->since()`).** Add absolute-time context (tooltip or fallback). Minor.
3. **Phone column is plain text.** A `tel:` link would let the owner call from the dashboard. Enhancement, not a defect.

Already known and not re-reported: the 10 unnamed Alert-column links in Drafts waiting (Phase 11, A11Y-03) and the developer-oriented room empty lines.

## Top fixes (optional, none block Phase 6)

1. Keyboard-only check of row links in the Paused and Drafts widgets; add a named link column if missing.
2. Absolute-time context for "Waiting".
3. Fold both into the Phase 11 A11Y-03 pass with the Alert-link names.
