---
phase: "07"
slug: "the-spine-and-leads"
status: approved
shadcn_initialized: false
preset: none
created: "2026-10-31"
---

# Phase 07: UI Design Contract

> This file records the contract Phase 7 already had under D-21 as amended (`07-CONTEXT.md`): stock Filament 5, no
> design work. It exists because GSD's ui-safety-gate scans every commit in the phase and needs a contract to check.
> It asks for no new design work. Layout and styling belong to Phase 11 (02.1 D-48).

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (stock Filament 5 panel, `filament/filament` v5.10.1) |
| Preset | not applicable |
| Component library | Filament 5 components and panel defaults; no custom CSS, tokens or new components |
| Icon library | Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48) |
| Font | Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48) |

---

## Component Inventory

Could not enumerate: Filament is not a shadcn registry. The table lists only what the two Phase 7 surfaces use, as
built; it is not an allowlist.

| Component | Import path | Notes |
|-----------|-------------|-------|
| Relation manager table | `Filament\Resources\RelationManagers\RelationManager`, `Filament\Tables\Table`, `Filament\Tables\Columns\TextColumn` | The contact screen's projects list (`ProjectsRelationManager`, D-14) |
| Actions | `Filament\Actions\Action` | New (with its own confirmation), Close (with Filament's confirmation), Reopen, Rename |
| Text input | `Filament\Forms\Components\TextInput` | Rename's one field, capped at 100 characters (`RenameProject::MAX_LENGTH`) |
| Notification | `Filament\Notifications\Notification` | The line after each action, and the rename refusal |
| Widget and section | `<x-filament-widgets::widget>`, `<x-filament::section>` | The leads room's dashboard section (`LeadsSection`, D-15) |
| Link | `<x-filament::link>` | Each leads row's link to the contact's screen |

---

## Spacing Scale

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Typography

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Color

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48). Close uses Filament's gray action color.

---

## Copywriting Contract

All wording is a placeholder in the PlainWords style (plain sentences, no exclamation points, no em dashes) until the
wording pass in Phase 11. "Project" is the word in code; the screen uses the business's own word (`{word}` and
`{words}` below, in lower case: "job", "matters").

| Element | Copy |
|---------|------|
| Primary CTA | "New {word}" on the contact screen's projects list, confirmed before it opens: heading "New {word}", "Open a new {word} for this customer? It takes the next number and cannot be deleted, only closed.", button "Open {word}" (review WR-03) |
| Empty state heading | Contact screen: "No {words} yet". Leads section: "No open {words}." |
| Empty state body | none |
| Error state | Rename refusals: "A name can be at most 100 characters." and the hidden-character line. Leads section: the shared failure line, "This section could not load. The rest of the dashboard is fine." |
| Destructive confirmation | Close: Filament's default confirmation. Reopen undoes it. New: its own confirmation (Primary CTA row), because a project cannot be deleted. |

The two Phase 7 surfaces as built:

- **The contact screen's projects list** (D-14). The tab is titled with the business's plural word. Columns: the
  project's label, Open or Closed, Opened, Opened by ("New lead" or the user's name), Closed. Actions: New and Close, each
  with a confirmation, Reopen, and Rename capped at 100 characters. Every write goes through the four project actions (D-11).
- **The leads room's dashboard section** (D-15, D-16). One row per open project, newest first: a link to the contact's
  screen, the project's label, "opened" with an absolute date in a `time` element, and its origin ("source {name}" or
  "opened by {name}"). It shows at most 20 rows (`LeadsSection::SHOWN`) and then "Showing the newest 20 of {n} open
  {words}."; with none open it shows its empty line.

---

## UI Considerations

| Category | Element(s) | Status | Resolution / Reason |
|----------|------------|--------|---------------------|
| empty | projects list, leads section | ✅ covered | Each shows its empty line from the Copywriting Contract |
| overflow | leads section | ✅ covered | At most 20 rows, then the total line |
| long-text | rename | ✅ covered | A name over 100 characters is refused |
| accessibility | both surfaces | ✅ covered | WCAG 2.2 AA: row links reachable by keyboard and dates shown as absolute dates (D-15); the axe check runs in the leads boot test (A11Y-01); the contact screen's project list is drawn with the page, not loaded on scroll, so Tab reaches it from the top (WCAG 2.1.1, gap G-07-2.1, plan 07-09) |
| accessibility | both surfaces | 🧪 backstop | Keyboard-only and VoiceOver walk at UAT (`.planning/WINDOWS.md` entries 38 and 41, from waves 5 and 7) |

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| none | none | No third-party UI registry or package |

---

## Checker Sign-Off

No gsd-ui-checker run: this file records an existing contract and asks for no design work. Layout and styling belong to
Phase 11 (02.1 D-48).

**Approval:** approved 2026-10-31 (Ian, D-21 as amended)
