---
phase: "10"
slug: "walk-tests-and-the-gate"
status: approved
shadcn_initialized: false
preset: none
created: "2026-11-20"
reviewed_at: "2026-11-21"
---

# Phase 10: UI Design Contract

> This file records the contract Phase 10 already has under `10-CONTEXT.md` (D-04, D-05, D-10, D-11): stock Filament 5,
> no design work, no custom CSS, tokens or components. It exists because GSD's ui-safety-gate scans every commit in the
> phase and needs a contract to check. It covers the owner-facing refusal lines on a screen that already exists and
> asks for no new design work. Layout and styling belong to Phase 11 (02.1 D-48), and a looks-only finding goes to
> `.planning/todos/pending/2026-10-17-phone-design-pass.md` under the 2026-11-11 visual rule.
>
> Six wording questions were not settled by `10-CONTEXT.md` or the code. Ian ruled on them on 2026-11-21; they are
> recorded under "Rulings by Ian" below and applied in the copy.

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (stock Filament 5 panel, `filament/filament` v5.10.1, read with `composer show filament/filament`) |
| Preset | not applicable |
| Component library | Filament 5 components and panel defaults; no custom CSS, tokens or new components |
| Icon library | Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48) |
| Font | Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48) |

---

## Component Inventory

Could not enumerate: Filament is not a shadcn registry and `components.json` does not exist. The table lists only what
the Phase 10 surfaces use; it is not an allowlist.

| Component | Import path | Notes |
|-----------|-------------|-------|
| Section | `<x-filament::section>` | The Business Folder page's existing "Last sync" section (`resources/views/filament/pages/business-folder.blade.php`); no new section is added (D-11) |
| Notification | `Filament\Notifications\Notification` | The Sync Now result, built in `BusinessFolder::runSync()`; Filament's own, unchanged |

No new view, component or page is built for the walk lines. Each one is one more `<p>` in the existing refusal list.

---

## Spacing Scale

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Typography

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Color

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48). The existing inline `style="color: #dc2626"` on
the refusal lines in the "Last sync" section is unchanged and is not this phase's to fix. Meaning is never carried by
that colour alone: the section's status word ("Rejected") and the wording of every line (each ends by saying which
version stays live, or that nothing is live yet) say the same thing in text.

---

## Copywriting Contract

All wording is a placeholder in the house refusal style (plain sentences naming the file and the field, no exclamation
points, no em dashes) until the wording pass in Phase 11. The exact wording of each refusal line is Claude's Discretion
in `10-CONTEXT.md`; D-11's example sentence and Ian's 2026-11-21 rulings are Ian's own. The existing house style is
`<path>: <message>`, as `ValidationReport` builds every sync refusal line today.

| Element | Copy |
|---------|------|
| Walk failure line (D-11), page, Sync Now notification and changelog | Locked example from D-11: "tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live." Shape: `{file}, turn {n}: {the check, in plain words}. {version} stays live.` One line per failed walk, naming the first check that failed, in the order the walk states its checks (ruling 2). When nothing is live yet, the closing clause is "Nothing is live yet." (ruling 3). The same line goes to all three places (ruling 6) |
| Unrecorded-walk refusal, owner form (D-05), page and Sync Now notification | Placeholder: "tests/dana.md: this walk has no recording yet. Recording it is Ian's step. v3 stays live." Names the file and says the recording is Ian's step. No command, no console wording. The "Ian's step" words are D-05's. When nothing is live yet, the closing clause is "Nothing is live yet." (ruling 3) |
| Unrecorded-walk refusal, Ian form (D-05), changelog.md only | Placeholder: "tests/dana.md: no recording. Run `walks:record` for tests/dana.md. v3 stays live." Carries the command and the file name. When nothing is live yet, "Nothing is live yet." (ruling 3). The command's argument syntax is the planner's, not set here |
| Walk needs a new recording, owner form (D-27), page and Sync Now notification | Ian's wording (ruling 8): "tests/dana.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live." Used when the candidate needs a model reply or a Jev reading the recording does not hold, for a stale walk and a fresh one alike. No command, no console wording. When nothing is live yet, "Nothing is live yet." (ruling 3) |
| Walk needs a new recording, Ian form (D-27), changelog.md only | Placeholder: "tests/dana.md: the recording no longer answers this walk (turn 3 needed a reply it does not hold). Run `walks:record` for tests/dana.md. v3 stays live." Carries the command and the file name. When nothing is live yet, "Nothing is live yet." (ruling 3) |
| Walk-file refusal (D-10), page, Sync Now notification and changelog | Placeholder: "tests/dana.md: expect names a time (3pm) but the walk has no start. Name a start for the walk, or word the check as the earliest time offered." Names the file and the field, and offers D-10's relative wording as the way out. The field names (`expect`, the start field) follow the walk file's syntax, which is the planner's (Claude's Discretion); the check can name a day, a date or a clock time, and the line echoes the one the owner wrote |
| Stale walk, owner form (D-04) | None. The owner sees no stale line anywhere: not on the page, not in the Sync Now notification. A sync that went live with a stale recording shows exactly what any passing sync shows today ("Live" and the time), so the owner cannot tell anything differed. This is the reading of D-04's owner-facing line: the existing "Live" line is that line (ruling 1) |
| Stale walk, Ian form (D-04), changelog.md only | Placeholder: "tests/dana.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record` to refresh it." One line per stale walk, each naming the file, plus the command. Carried by a "Live as vN" entry and by a "Rejected" entry from a sync whose walks ran; not by a "No changes" entry, and not by a sync refused at validation, where no walk ran (ruling 5) |
| "Last sync" heading words | Unchanged: "Rejected", "No changes", "Live" |
| Primary CTA | none new: Sync Now is the existing header action and keeps its label |
| Empty state | none: a sync with no walk line shows the section as it does today |
| Destructive confirmation | none: nothing in this phase deletes or overwrites anything the owner sees |

The contract needs two forms for one case, and today it has one. The page's "Last sync" list, the Sync Now notification
body and the changelog entry all print the same `$result->errors`. D-05 needs the owner-facing text and the changelog
text to differ for the unrecorded walk. The stale walk adds a changelog-only line with no owner-facing counterpart
(ruling 1). Both are recorded above. How the code carries them is the planner's.

Where each state shows:

| State | "Last sync" section (page) | Sync Now notification | changelog.md entry |
|-------|----------------------------|-----------------------|--------------------|
| Validation fails, walk file included (D-10) | The refusal list, one red `<p>` per validation line, "Rejected" | Danger notification, lines in the body, escaped | "Rejected" entry: the lines, then the existing "vN stays live" bullet. No walk ran, so no stale line |
| Validation passes, a walk fails (D-11) | The refusal list, one `<p>` per failed walk, "Rejected" | Danger notification, same lines | "Rejected" entry: the same lines, then the existing "vN stays live" bullet, kept (ruling 6); plus one Ian-form stale line per stale walk, if any (ruling 5) |
| Validation passes, a walk has no recording (D-05) | One `<p>` per unrecorded walk, owner form, "Rejected" | Danger notification, owner form | "Rejected" entry: the Ian form, with the command, then the existing bullet; plus stale lines, if any (ruling 5) |
| Validation passes, a walk needs a new recording (D-27) | One `<p>` per such walk, owner form ("needs a new recording"), "Rejected" | Danger notification, owner form | "Rejected" entry: the Ian form, with the command, then the existing bullet; plus stale lines, if any (ruling 5) |
| Validation passes, every walk passes, a recording is stale (D-04) | "Live" and the time, exactly as any passing sync today; no stale line (ruling 1) | Success notification, as today | "Live as vN" entry: one Ian-form bullet per stale walk |
| Validation passes, every walk passes, nothing stale | No walk line | Success notification, as today | "Live as vN" entry, as today |
| No changes | As today | As today | "No changes" entry, as today; no stale line (ruling 5). No walk runs on an unchanged sync (ruling 7) |

Walks run only when validation passes (D-11), so a sync never shows walk lines and validation lines together. An
unrecorded walk and a failed walk are both gate-stage lines and can appear together in one refusal list. Lines appear in
the order the gate ran the walks, which the planner makes stable (the same folder gives the same order on every sync).

Accessible names:

- **Walk and refusal lines.** Each is text in a `<p>` inside the existing "Last sync" section, so a screen reader reads
  them in order after the section heading and the status line ("Rejected" and the time). Each line names the file, the
  turn or field, and the check or reason in words, so it makes sense read alone. No line is carried by colour, an icon
  or an abbreviation.
- **Stale walk.** Nothing is added to the page, so there is nothing new to name or reach.
- **Sync Now notification.** Filament's own notification; this phase adds no markup to it. Its body is the same escaped
  lines (`e()` then `<br>`), so a file name or check text from a walk file is never read as HTML.
- **Controls.** No new button, link, form field or focus target. Sync Now keeps Filament's own keyboard and focus
  behaviour.

---

## UI Considerations

Applicable state considerations resolved: 12 covered, 4 backstop, 0 unresolved. The UI-consideration probe (2026-11-21)
raised 38 element and category rows across the five surfaces (walk failure line, unrecorded line, walk-file refusal,
stale walk, Sync Now); each category is answered below at the level of the refusal list, which all five share.

| Category | Element(s) | Status | Resolution / Reason |
|----------|------------|--------|---------------------|
| empty | refusal list | ✅ covered | A sync with no walk line shows the "Last sync" section exactly as today; no new empty copy (Copywriting Contract, "Empty state") |
| populated | walk failure, unrecorded and walk-file lines | ✅ covered | Each shows as one `<p>` in the refusal list, in the copy and the places the Copywriting Contract's state table gives; the stale walk has no populated owner state (ruling 1) |
| error | walk failure line | ✅ covered | One plain line per failed walk naming the file, the turn and the first check that failed, and saying which version stays live; the previous version stays live (D-11, WALK-02, rulings 2 and 3) |
| error | unrecorded-walk line | ✅ covered | Owner form on the page and in the Sync Now notification names the file, says the recording is Ian's step, shows no command; the Ian form in changelog.md carries `walks:record` and the file name (D-05) |
| error | walk-file refusal line | ✅ covered | A check naming a day, date or clock time with no named start is refused in plain words naming the file and the field, in the existing validation style (D-10) |
| error | first sync, nothing live | ✅ covered | The walk failure and unrecorded lines close with "Nothing is live yet.", the words the sync already uses (ruling 3) |
| partial | stale walk | ✅ covered | Never refused and never shown to the owner: the page and the notification read exactly as any passing sync; changelog.md names each stale walk and the command (D-04, ruling 1) |
| partial | stale note on a rejected or unchanged sync | ✅ covered | A "Rejected" entry from a sync whose walks ran carries the stale lines; a "No changes" entry and a sync refused at validation do not (ruling 5) |
| partial | validation and walk lines in one sync | ✅ covered | Walks run only when validation passes, so a sync shows validation lines or walk lines, never both (D-11). Unrecorded and failed lines can share one list |
| many | refusal list | ✅ covered | Every failed walk gets its own line, none cut off, because D-11 is one line per failed walk; the section grows downward inside Filament's layout |
| many | rejected changelog entry | ✅ covered | Each walk line keeps its "stays live" clause and the entry keeps its closing "vN stays live" bullet, so the same line goes everywhere and the entry keeps today's shape (ruling 6) |
| long-text | file path, check wording | ✅ covered | A line holds a file path under `tests/` and a plain-words check; it wraps inside the section, and all text is escaped on the page, in the notification (`e()`) and in changelog.md |
| loading | Sync Now | 🧪 backstop | Sync Now runs the gate inline, not queued. Replays use recordings, so no live model call is made (D-01), but the run is longer than today's. Checked at UAT that the shipped example walk set (D-12) finishes in the page's request with Filament's own action loading state and no timeout |
| overflow | walk lines at phone width | 🧪 backstop | Lines wrap inside the section with no sideways scroll at phone width; checked at the UAT phone walk. Looks-only findings go to the phone design pass todo (Phase 11) |
| accessibility | walk and refusal lines | ✅ covered | WCAG 2.2 AA in the markup: plain text in `<p>`, read in order after the section heading and status line, meaning never carried only by the existing red colour (each line says in words that it is a refusal and which version stays live). Contrast is the exception: the inherited `#dc2626` falls below 4.5:1 in dark mode, a known pre-existing gap that is a Phase 11 must-fix (see "Out of scope") |
| accessibility | Business Folder page | 🧪 backstop | Keyboard-only and screen reader walk at UAT over a rejected sync with walk lines and an unrecorded line; `BusinessFolderPageTest` has no automated accessibility check today, and this phase adds no markup beyond `<p>` lines, so the walk is the check |
| look only | spacing, colour, type size, column width, visual weight | 🧪 deferred | Not specified here; any such finding goes to `.planning/todos/pending/2026-10-17-phone-design-pass.md` (the 2026-11-11 visual rule) |

Phase gates (CLAUDE.md, the 2026-11-11 visual rule): wording, what each screen shows and in what order, a page fitting a
phone with no sideways scroll, keyboard use, focus, screen-reader names, contrast and the automated accessibility check.
This phase adds no control, so focus has nothing to land on beyond Filament's own page order; the check is that adding
lines to the list does not reorder the page's sections or add a tab stop.

Out of scope, so a checker does not ask for it:

- `walks:record` console output (a command for Ian, not a screen).
- The calibration report for Ian (D-13, D-17; not a screen).
- The operator notice that tells Ian a client's walks are stale or unrecorded (D-25, before-first-client list).
- Automatic re-recording (D-03).
- Any new page section, a new page, or a change to the customer card.
- The wording of walk-file refusals other than D-10's (a missing persona, channel, script or expect). They travel the same
  path in the same style, and their wording is the planner's under Claude's Discretion.
- Phone layout of the Business Folder page (Phase 11).
- The inline `style="color: #dc2626"` on refusal lines is a known WCAG 2.2 AA contrast gap, not only a look item. The
  panel does not turn dark mode off (`AdminPanelProvider` never calls `darkMode()`, and Filament leaves it on by
  default), and that red on Filament's dark section background falls below 4.5:1. It is pre-existing, in three views
  with seven uses: `resources/views/filament/pages/business-folder.blade.php` (3),
  `resources/views/livewire/dev-chat.blade.php` (2) and `resources/views/livewire/conversation-thread.blade.php` (2).
  It goes to Phase 11 as a must-fix under the AA rule (Ian, 2026-11-21). Phase 10's walk lines inherit it until then,
  because they render through the same refusal `<p>`.

---

## Rulings by Ian (2026-11-21)

`10-CONTEXT.md` did not settle these. Ian ruled on each; the copy above applies them.

| # | Question | Ruling | Decision it reads |
|---|----------|--------|-------------------|
| 1 | What does the owner see when a change goes live with a stale recording? | No stale line at all. The page and the Sync Now notification show exactly what any passing sync shows today ("Live" and the time), so the owner cannot tell anything differed. The stale walks are named only in the changelog line for Ian. The existing "Live" line is D-04's owner-facing line | D-04 |
| 2 | When one walk fails several checks, which check does its line name? | The first check that failed, in the order the walk states them. Still one line per failed walk | D-11, D-09 |
| 3 | What does a walk line say when nothing is live yet? | "Nothing is live yet." in place of "v3 stays live", the words the sync already uses. Applies to the walk failure line and both forms of the unrecorded line | D-11, D-05 |
| 4 | Does the Sync Now success notification carry a stale line? | No. Settled by ruling 1: there is no owner-facing stale line, so the notification is as today | D-04 |
| 5 | Which changelog.md entries carry Ian's stale note? | "Live as vN" and "Rejected" entries from a sync whose walks ran. Not "No changes" | D-04, D-25 |
| 6 | A rejected changelog entry says "v3 stays live" in each walk line and again in `Changelog::liveBullet()`. Keep both? | Keep both. The same line goes to the page, the notification and the changelog, and the entry keeps its shape | D-11 |
| 7 | Does the gate run when a sync finds the folder unchanged? (ruled during plan-phase) | No. The gate guards activation: walks run only on a sync that would activate a candidate, and "No changes" stays as today. A `config/gate.php` bar change is a code change tested in CI; on a client it is next exercised by the next sync that activates a candidate | D-02 (clarified) |
| 8 | What does the owner see when the candidate needs a reply the recording does not hold? (ruled during plan-phase) | Refused as unrecorded, stale or fresh alike. The owner line says the walk needs a new recording: "tests/dana.md: this walk needs a new recording. Recording it is Ian's step. v3 stays live." The changelog line is Ian's form with `walks:record` | D-27, D-05 |

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| none | none | No third-party UI registry or package |

---

## Checker Sign-Off

gsd-ui-checker run 2026-11-21: approved, 0 blocking. Copywriting, Visuals, Color, Typography, Spacing and Registry
Safety pass; one non-blocking flag on Inventory Provenance (the table is not an allowlist, so an executor may use other
stock Filament components). The checker read this file and `10-CONTEXT.md` only; the code claims (the
`<path>: <message>` refusal shape in `ValidationReport`, `Changelog::liveBullet()`, "Nothing is live yet" in
`FolderSync` and `Changelog`, the notification body escaped with `e()`, no automated accessibility check in
`BusinessFolderPageTest`) were checked against the code by the orchestrator.

This file records an existing contract and asks for no design work. Layout and styling belong to Phase 11 (02.1 D-48).

**Approval:** approved 2026-11-21 (Ian; six rulings recorded above, and the inherited `#dc2626` contrast gap recorded as
a Phase 11 must-fix)
