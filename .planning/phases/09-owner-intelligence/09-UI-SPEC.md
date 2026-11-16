---
phase: "09"
slug: "owner-intelligence"
status: approved
shadcn_initialized: false
preset: none
created: "2026-11-14"
reviewed_at: "2026-11-14"
---

# Phase 09: UI Design Contract

> This file records the contract Phase 9 already had under D-29 (`09-CONTEXT.md`): stock Filament 5, no design work. It
> exists because GSD's ui-safety-gate scans every commit in the phase and needs a contract to check. It covers two
> lines and asks for no new design work. Layout and styling belong to Phase 11 (02.1 D-48).

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

Could not enumerate: Filament is not a shadcn registry. The table lists only what the two Phase 9 surfaces use; it is
not an allowlist.

| Component | Import path | Notes |
|-----------|-------------|-------|
| Text entry | `Filament\Infolists\Components\TextEntry` | The contact screen's Time to booked line, in the Contact section of `app/Filament/Resources/Contacts/Schemas/ContactInfolist.php` |
| Widget and section | `<x-filament-widgets::widget>`, `<x-filament::section>` | The room's dashboard section, drawn by the shared view `resources/views/filament/rooms/section.blade.php` (`<p>` per line, as 05's sections do); no new view is needed because the room owns no file and the section holds lines only |

---

## Spacing Scale

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Typography

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Color

Filament 5 defaults; layout and styling belong to Phase 11 (02.1 D-48).

---

## Copywriting Contract

All wording is a placeholder in the PlainWords style (plain sentences, no exclamation points, no em dashes) until the
wording pass in Phase 11. The wording below comes from `09-CONTEXT.md`; the formatter is one shared class, so the contact
screen, the section and `time-to-booked.md` always read alike (D-06).

| Element | Copy |
|---------|------|
| Label | "Time to booked" (D-05; not "Minutes to booked", because the value is not always minutes) |
| Value format (D-06) | Up to 90 minutes: "42 minutes". Above 90 minutes: "3 hours 10 minutes". Past 48 hours: "2 days 4 hours". Singular forms: "1 minute", "1 hour", "1 day". Less than a minute: "under a minute" |
| Summary line | Ian's wording (D-07, 2026-11-16). Two or more customers: "Half of your 12 new customers in the last 30 days booked within 42 minutes." One customer: "Your 1 new customer in the last 30 days booked within 42 minutes." The time is the median, formatted by `DurationText` (D-06); `records/time-to-booked.md` carries the same sentence |
| Empty line | "No new customers booked in the last 30 days." (Ian's wording, D-07) |
| Failure line | The shared line, "This section could not load. The rest of the dashboard is fine." (`RoomSection::FAILED_LINE`) |
| Primary CTA, error state, destructive confirmation | none: both surfaces only read and have no button, form or write |

The two Phase 9 surfaces:

- **The contact screen's Time to booked line** (D-05, D-06, D-03, D-08). One more `TextEntry` in the "Contact" section of
  `ContactInfolist::configure()` (`app/Filament/Resources/Contacts/Schemas/ContactInfolist.php`, shown by `ViewContact`).
  It goes after "Next appointment" and before "Automatic texts", so the three entries that only show sometimes stay
  together at the end of the section and the order of the entries already there does not change. Its state is the
  D-06 text from the shared formatter, worked out on read (D-04). Like "Next appointment", it is left out when its state
  is empty, through `visible()`, and it is also left out while the room is off, by asking
  `app(ActiveConfig::class)->hasRoom('owner_intelligence')`. The line is not on the customer card (D-05, `CustomerCard`
  is unchanged).
- **The owner intelligence room's dashboard section** (D-07, D-24, D-08). A `RoomSection` subclass whose class name is
  the planner's choice in the house style (Claude's Discretion), with `roomKey()` returning `owner_intelligence` and
  `contentLines()` returning the one summary line or the empty line. It sits beside its siblings in
  `app/Rooms/OwnerIntelligence/`, registered by its provider in `bootstrap/providers.php` (D-21). All room sections
  keep `RoomSection`'s default sort of 20, so they draw in room-key order (`RoomRegistry::widgetClasses()`, `all()`
  sorts by key): `owner_intelligence` lands after `money` and before `schedule_and_booking`. The planner confirms that
  position in the boot test and does not set a `$sort`. The section holds this line only; today's visits stay in the
  schedule room's section (D-24, 08 D-40) and are not repeated.

Where each state shows:

| State | Contact screen | Dashboard section |
|-------|----------------|-------------------|
| Contact has a number | "Time to booked" and the D-06 value | not applicable (the section reads the business, not one contact) |
| No number: no qualifying inbound before the earliest `booked_at`, or never booked (D-01, D-03) | no line at all, never zero or negative | the contact is left out of the median |
| Room off (D-08) | no line | the section is hidden: `RoomSection::canView()` answers from `hasRoom()` |
| Room on, no contact first booked in the last 30 days (D-07) | not affected | the empty line, "No new customers booked in the last 30 days." |
| Room on, at least one such contact | not affected | the summary line, with the count |
| Building the line throws | the entry is left out and the error is reported to the log | the shared failure line (`RoomSection::getViewData()`) |

The section is never shown as an empty room: the room owns no file, so `RoomSection::emptyLine()` returns null and the
platform's "nothing set up yet" line never appears (D-24, 04 D-21). The empty line above is a statement about the last
30 days, not a room with nothing set up.

Accessible names:

- **Contact line.** The screen reader reads the label and the value together, as it does for "Next appointment": "Time
  to booked", then "42 minutes". The value carries its own unit, so it needs no extra aria text.
- **Section.** Named by its heading, the room's label with the first letter in capitals, drawn by
  `<x-filament::section :heading="$heading">` the way the leads and schedule sections are. The room's `label` is the
  planner's choice and is expected to read "owner intelligence", so the heading reads "Owner intelligence". The line sits
  in a `<p>` inside the section, so it is read after the heading and as one sentence.

---

## UI Considerations

| Category | Element(s) | Status | Resolution / Reason |
|----------|------------|--------|---------------------|
| empty | contact line | ✅ covered | No number, or the room off: no line at all, never zero or negative (D-03, D-08) |
| error | contact line | ✅ covered | Building the line throws: the entry is left out and the error is reported to the log, so a broken line is never silent (Ian, 2026-11-14) |
| overflow | contact line | 🧪 backstop | The short value wraps inside Filament's own layout, with no sideways scroll at phone width; checked at the UAT phone walk |
| long-text | contact line | ✅ covered | The value comes only from the D-06 formatter, with no owner text, so its length is bounded |
| empty | section | ✅ covered | No contact first booked in the last 30 days: the empty line from the Copywriting Contract (D-07) |
| loading | section | ⛔ dismissed | Drawn with the dashboard like the sibling room sections; no loading state of its own (looks belong to Phase 11) |
| error | section | ✅ covered | Building the line throws: the shared failure line (`RoomSection::FAILED_LINE`), and the rest of the dashboard still shows |
| partial | section | ✅ covered | Contacts with no number are left out of both the median and the count (D-03) |
| overflow | section | 🧪 backstop | The line wraps inside the section, with no sideways scroll at phone width; checked at the UAT phone walk |
| long-text | section | ✅ covered | The line is built from two numbers and one formatted time, so it cannot grow with owner text |
| accessibility | both surfaces | ✅ covered | WCAG 2.2 AA: wording and order gate the phase; the section gets the axe check in the room's boot test with `RoomSectionAxe` (D-24, 04 D-27, A11Y-01); the line is plain text, not colour or an icon, so it does not rely on contrast beyond Filament's defaults |
| accessibility | both surfaces | 🧪 backstop | Keyboard-only and VoiceOver walk at UAT, recorded as an entry in `.planning/WINDOWS.md` (D-29); the contact screen has no axe check of its own because 04 D-27 sets it per room section |
| responsive | contact line, section | ✅ covered | Both are short text in Filament's own layout; a sideways scroll on a phone would be a gating finding, and the planner checks it at the phone walk. The customer-facing page rule does not apply: neither surface is a customer page |
| look only | spacing, colour, type size, column width, visual weight | 🧪 deferred | Not specified here; any such finding goes to `.planning/todos/pending/2026-10-17-phone-design-pass.md` (the 2026-11-11 visual rule) |

Phase gates (D-29): wording, what each screen shows and in what order, keyboard use, focus, screen-reader names,
contrast, and the automated axe check in the room's boot test. Neither surface has an action, so focus has nothing to
land on beyond Filament's own page order; the check is that adding the entry does not reorder or trap focus on the
contact screen.

Out of scope, so a checker does not ask for it:

- The customer card is unchanged (D-05).
- Today's visits are not repeated in this section (D-24; they show in the schedule room's section, 08 D-40).
- `records/today.md` and `records/time-to-booked.md` are files for the owner's AI, not screens (D-12, D-23).

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| none | none | No third-party UI registry or package |

---

## Checker Sign-Off

gsd-ui-checker run 2026-11-14: all 7 dimensions pass, with one non-blocking flag on Component Inventory provenance (the
table is not an allowlist). This file records an existing contract and asks for no design work. Layout and styling
belong to Phase 11 (02.1 D-48).

**Approval:** approved 2026-11-14 (Ian, D-29; UI Considerations resolved with Ian, who added the contact line's error row)
