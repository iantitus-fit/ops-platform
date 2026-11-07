---
phase: 08-schedule-and-booking
audited: 2026-11-07
baseline: 08-UI-SPEC.md (approved 2026-11-02)
screenshots: not captured (no dev server answered on 3000, 5173, 8000 or 8080; code-only audit)
interaction_captures: off
overall: 18/24
---

# Phase 8 — UI Review

**Audited:** 2026-11-07, at phase close, after plan 08-16
**Baseline:** 08-UI-SPEC.md (the public booking page in the customer layout; owner screens in stock Filament)
**Method:** code-only audit by gsd-ui-auditor; nothing was run in a browser. The orchestrator checked the auditor's concrete code claims before writing this file and corrected two (marked below).

## Pillar Scores

| Pillar | Score | Key finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | Customer strings match the contract word for word; owner-side copy drifts in four places |
| 2. Visuals | 3/4 | Focal point, icons and Label in Name are right; the footer disclosures have no visible hover state |
| 3. Color | 4/4 | Tokens match the contract exactly; no hex, raw palette or `style=` in the customer views |
| 4. Typography | 4/4 | Only `text-body`, `text-heading`, `text-display`, and only `font-normal` and `font-semibold` |
| 5. Spacing | 3/4 | On scale with no arbitrary values; the 320 px max-width and padding rules need a browser to confirm |
| 6. Experience Design | 3/4 | Good state coverage and focus management; several states were not seen rendered |
| **Overall** | **18/24** | |

## Priority Fixes (all warnings, no blocker)

1. **Footer disclosures have no visible hover state.** `resources/views/components/booking/footer.blade.php:5,13`: `hover:border-accent` sits on the `<summary>`, which has no border, so nothing changes on hover. The contract says every disclosure's border turns `accent` on hover; the day disclosures in `day.blade.php` do this correctly. Fix: put the hover on the `<details>` (or `group-hover`), or move the border onto the summary.
2. **Owner copy drifts from the contract in four places.** Reconcile in the spec or the code; Ian approves the extra variant either way.
   - The hand-booking status line has a fourth variant, "...Customer texts for booking are off until rooms/schedule_and_booking/booking.md is added." (`AppointmentsRelationManager.php:236`). The spec lists three.
   - The job select option reads "New job" in lowercase (`mb_strtolower`, near line 203). The spec says "New {job}".
   - The "Pick a job." validation message is not in the copy contract.
   - The Schedule dialog's helper text prints the zone from `displayTimezone()`; the spec wants "Times are in {zone}." with the zone's plain name. Check which name it prints (a raw id such as "America/Chicago" would drift).
3. **The phone layout has no screenshot or browser evidence in this audit.** From the code, the 48 px floor comes from `min-h-12` and time buttons are `w-full`, so it should hold. The UAT keyboard, VoiceOver and 200% zoom walk of the booking page (A11Y-02, CLAUDE.md) stays required; it is already in 08-VALIDATION.md's manual-only list.

## Minor Notes

- **The in-flight "Book this time" button** (`booking-page.blade.php:56`, `wire:loading.attr="aria-disabled"`). *Orchestrator correction:* the auditor said Livewire writes an empty `aria-disabled=""`, which ARIA reads as false. Livewire 3 sets the value to `"true"` (`vendor/livewire/livewire/dist/livewire.esm.js:13317`), so assistive tech is told the button is busy. The button stays clickable, since `aria-disabled` blocks nothing. A second tap does not book twice, though: `BookingPage::book()` returns early once `chosen` is cleared, and the link books once, under a lock (T-08-28). There is no browser test that double-taps; one would pin U-20 ("ignores further taps") end to end.
- **Dashboard section row** (`schedule-section.blade.php:11`). *Orchestrator correction:* the auditor flagged a possible dangling comma when the job is empty. The job is never empty: `ScheduleSection.php:105` falls back to the job word when a visit has no project. No fix needed.
- **Owner screens** (Schedule page, Appointments tab, add-contact page, dashboard section) are stock Filament, as the spec intends, and were not audited visually. The held-draft checkbox in `conversation-thread.blade.php:38-40` sits in the Phase 2 inline styles, including a hard-coded `#dc2626` for errors (line 106). That styling predates this phase; the spec defers its restyle to Phase 11.

## Detailed Findings

### Pillar 1: Copywriting (3/4)

Every customer string checked matches the contract word for word: the dead-end headings and lines, "Check your time", "Book this time", "Booking your time", "Choose a different time", the taken and failed errors, the noscript line, and the privacy and accessibility placeholders. No exclamation points or em dashes in the customer views. Owner side matches on "Cancel visit", "Cancel appointment" with "Keep appointment", "Remove block" with "Customers can book that time again.", "Add contact", "New contact", "Nothing scheduled", "No visits in this view" with "Switch the Show filter to Upcoming.", and the "has not texted the business or agreed to texts" reply-box line. The drifts are priority fix 2.

### Pillar 2: Visuals (3/4)

One h1 per state; the booked view's h1 has a decorative check icon. All three icons are inline SVG with `aria-hidden` and `currentColor`; there is no `<x-heroicon-`. The time button name pattern (U-10) is right: the visible time is `aria-hidden` and the `sr-only` text starts with the same time, so Label in Name holds. The held-draft checkbox has a real `<label for>` whose text is the line. Deduction: priority fix 1.

### Pillar 3: Color (4/4)

`resources/css/app.css` declares the `@theme inline` tokens exactly as the contract does. The only hex or raw palette hit in the customer files was the DOCTYPE line, a false match. Accent is used only on the primary button, `sms:` links and hover borders, as reserved; the error colour only on the error line and its icon. Contrast was not recomputed; it rests on the spec's table and the axe checks in CI.

### Pillar 4: Typography (4/4)

Only the three named sizes and the two weights appear in the customer views. The `dl` labels use `text-ink-muted` at 400 and the values use 600, as specified.

### Pillar 5: Spacing (3/4)

Only scale classes: `space-y-2`, `space-y-6`, `gap-2`, `px-4`, `py-4`, `py-8`, `pt-6`, `pb-8`, `min-h-12`, `min-w-12`. No arbitrary values. The picker's `hidden` attribute works with `space-y-6`, since `space-y` sets no display. Time buttons are `w-full` in a `grid-cols-2 sm:grid-cols-3` grid with `gap-2`, which fits the contract. The max-width and padding rules can only be confirmed at 320 px in a browser.

### Pillar 6: Experience Design (3/4)

Focus moves use `x-init="$el.focus()"` on the check h2, the booked h1 (only when `$justBooked`) and the error line. "Choose a different time" returns focus to the tapped time through `lastTime` and `$nextTick` (`booking-page.blade.php:57`). `wire:ignore.self` on each `<details>` keeps open days across re-renders. The rate-limited state, a title per state (`titleFor`), the noscript line, noindex and no-referrer are all present. Owner side: Mark done and Cancel visit are hidden, not disabled, when they do not apply; row aria-labels start with the action's visible words; focus-target events exist for every action that removes its own button; the automatic-texts switch confirms first; the Appointments tab is `$isLazy = false`. Not seen rendered: the dashboard section's empty and error states and the DST-day entry (the suite covers them).

## Files Audited

- `.planning/phases/08-schedule-and-booking/08-UI-SPEC.md` (lines 1-553 of 666)
- `resources/views/layouts/customer.blade.php`
- `resources/views/livewire/booking-page.blade.php`
- `resources/views/components/booking/*.blade.php`, including the three icon partials
- `resources/css/app.css`
- `app/Livewire/BookingPage.php` (partial)
- `app/Filament/Pages/Schedule.php`
- `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php`
- `app/Filament/Resources/Contacts/Pages/CreateContact.php`
- `app/Filament/Resources/Contacts/Pages/ViewContact.php` (grep only)
- `app/Rooms/ScheduleAndBooking/ScheduleSection.php`
- `app/Rooms/ScheduleAndBooking/VisitActions.php` (grep only)
- `resources/views/filament/rooms/schedule-section.blade.php`
- `resources/views/livewire/conversation-thread.blade.php` (lines 36-110)

The auditor did not read the 16 plans and summaries, 08-CONTEXT.md, or all of `ContactInfolist.php` and `ConversationThread.php`; the scores rest on the UI-SPEC and the current code.
