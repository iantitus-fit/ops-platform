---
phase: 08-schedule-and-booking
audited: 2026-11-08
baseline: 08-UI-SPEC.md (approved 2026-11-02, amended 2026-11-07 and 2026-11-08)
screenshots: not captured (no dev server answered on 3000, 5173 or 8080; code-only audit)
interaction_captures: off
overall: 22/24
---

# Phase 8 — UI Review

**Audited:** 2026-11-08, re-audit after gap plans 08-17 to 08-23 (replaces the 2026-11-07 review of 18/24)
**Baseline:** 08-UI-SPEC.md (the public booking page in the customer layout; owner screens in stock Filament)
**Method:** code-only audit by gsd-ui-auditor; nothing was run in a browser. Customer views were read in full; owner files were checked by grep for the strings and behaviours the contract names. The orchestrator checked the auditor's concrete code claims before committing this file and corrected three (marked below).

## Pillar Scores

| Pillar | Score | Key finding |
|--------|-------|-------------|
| 1. Copywriting | 4/4 | Customer and owner strings match the (amended) contract; the zone is now a plain name |
| 2. Visuals | 4/4 | Footer hover fixed; label in name, icons and one h1 per state hold; nothing seen rendered |
| 3. Color | 4/4 | Only token utilities; accent only where reserved; no hex, `style=` or raw palette in customer views |
| 4. Typography | 4/4 | Only `text-body`, `text-heading`, `text-display`; only `font-normal` and `font-semibold` |
| 5. Spacing | 3/4 | On scale, no arbitrary values; page bottom padding is `pb-8`, contract table says 48 px; 320 px unverified |
| 6. Experience Design | 3/4 | Try again and focus map now present; no rendered evidence, no double-tap test, dashboard states unseen |
| **Overall** | **22/24** | |

## Since the last review

| Earlier finding | Status | Evidence |
|-----------------|--------|----------|
| Priority 1: footer disclosures had no visible hover state | Resolved | `hover:border-accent` now sits on the `<details>` that carries the `border-2` (`resources/views/components/booking/footer.blade.php:4,12`); day summaries carry both border and hover on the summary (`components/booking/day.blade.php:3`). The spec now names the details element (08-UI-SPEC.md:363). |
| Priority 2a: fourth hand-booking status variant | Resolved in the spec | Approved by Ian and listed at 08-UI-SPEC.md:454; code matches at `AppointmentsRelationManager.php:239`. A fifth line for no phone is also in the spec and at `:240`. |
| Priority 2b: "New job" in lowercase | Resolved in the spec | Spec line 479 now says the job word in lower case; code `AppointmentsRelationManager.php:194`. |
| Priority 2c: "Pick a job." not in the contract | Resolved in the spec | 08-UI-SPEC.md:436; code `AppointmentsRelationManager.php:139`. |
| Priority 2d: Schedule/hand-booking helper might print a raw zone id | Resolved | Both dialogs print `'Times are in '.BookingSettings::zoneName(...)` (`AppointmentsRelationManager.php:132`, `Schedule.php:184`). `zoneName` formats the long generic name ("Central Time") and falls back to the id only if Intl returns nothing (`app/Rooms/ScheduleAndBooking/BookingSettings.php:121-127`). |
| Priority 3: phone layout has no browser evidence | Mostly resolved | *Orchestrator correction:* the auditor called this still open. The Pest browser suite already renders the page: it books by keyboard at 375 px (`tests/Feature/Booking/BookingPageBrowserTest.php:198`), measures every control against the 48 px floor and the 8 px gap at 375 px and 1280 px (`:325-339`), checks the footer hover (`:383`) and walks the Try again focus path (`:406`). What no test covers is 320 px reflow. The UAT keyboard, VoiceOver and 200% zoom walk stays required (08-UI-SPEC.md:544). |
| Minor: in-flight button uses `aria-disabled`, no double-tap browser test | Partly resolved | Markup unchanged and correct (`booking-page.blade.php:61`, `wire:loading.attr="aria-disabled"`). Plan 08-20 hardened refusals server side, but no end-to-end double-tap browser test was found. See finding 3 below. |
| Minor: owner screens not visually audited; held-draft styling in Phase 2 inline styles | Still open, by design | The spec defers owner restyle to Phase 11 (08-UI-SPEC.md:462). Not rescored. |
| Gap-plan items | Resolved | 08-22 added a "Try again" button to the rate-limited notice (`booking-page.blade.php:5-7`) with `tryAgain()` going through the limiter (`app/Livewire/BookingPage.php:153-155`, limiter at `:307`) and focus carried by `$retried` to the h1 or h2 (`booking-page.blade.php:11,37,72,97-109`). 08-21 made the privacy text match the code (`footer.blade.php:9`; matches 08-UI-SPEC.md:417). 08-23 added the no-phone forecast line (`AppointmentsRelationManager.php:240`) and split Past by `ends_at` (`Schedule.php:284,294`). |

## Priority Fixes (all warnings, no blocker)

1. **Add a browser test that double-taps "Book this time", and a 320 px reflow check.** The `aria-disabled` button stays clickable by design (`booking-page.blade.php:61`), and U-20 ("ignores further taps") rests on server guards alone. The 375 px and desktop layout is already pinned in `BookingPageBrowserTest.php:198,325` (orchestrator correction); 320 px is not.
2. **Settle the page bottom padding.** The spacing table gives 48 px for page bottom padding (08-UI-SPEC.md:286). The layout uses `pb-8` on `<main>` (`resources/views/layouts/customer.blade.php:24`; orchestrator correction, the auditor cited line 19) and `py-8` on the footer wrapper (`footer.blade.php:3`), so the space below the last footer row is 32 px. Either use `pb-12` on the footer wrapper or amend the table to say 32 px.
3. **UAT must walk the rendered page.** Run the keyboard-only, VoiceOver and 200% zoom walk the contract requires, with the new "Try again" focus path included. Nothing in this audit replaces it.

## Minor Notes

- The "Try again" notice button is `w-full sm:w-auto` inside a plain `<div>` (`components/booking/notice.blade.php:5-7`), so it stretches on phones and shrinks beside nothing on wider screens. Fine against U-08 (`min-h-12` from `button.blade.php:3`); only a rendered check can confirm it looks intended.
- Inline `sms:` links have no minimum target (`components/booking/sms.blade.php:2`). The contract exempts them (08-UI-SPEC.md:535). The business phone appears many times per dead end; leave it.
- Owner screens (Schedule page, Appointments tab, add-contact page, dashboard section) remain stock Filament. The held-draft checkbox still sits in Phase 2 inline styles with a hard-coded `#dc2626` (`resources/views/livewire/conversation-thread.blade.php`), deferred to Phase 11 by the spec.

## Detailed Findings

### Pillar 1: Copywriting (4/4)

Customer strings were re-read against the Copywriting Contract: "Check your time", "Book this time" and "Booking your time" (`booking-page.blade.php:37,62,63`), "Choose a different time" (`:65`), the taken line (`:17`), the failed line (`:57`), "You are booked" and the change line (`:74,94`), and every dead-end heading and line (`:3-4,97-109`). All match word for word. The noscript line is at `layouts/customer.blade.php:25-27` (orchestrator correction, the auditor cited 19-21). The privacy and accessibility disclosures match the contract text (`footer.blade.php:9,17`). Owner side: the hand-booking forecast lines (`AppointmentsRelationManager.php:236-241`) match all five variants in 08-UI-SPEC.md:454; the zone helper prints "Central Time" style names. No exclamation points or em dashes were seen in the customer views. The earlier four-point owner copy drift is closed (see table), so no deduction remains.

### Pillar 2: Visuals (4/4)

One h1 per state; the booked h1 carries a decorative check (`booking-page.blade.php:72-75`). Focal points follow U-35: h1, then the first open day (`:22`, `$loop->first`), then the check step's primary button, then the booked h1. The time button's visible text is `aria-hidden` and the `sr-only` text starts with the same time (`components/booking/time.blade.php:3`), so Label in Name holds. Icons are inline partials with the `error` and `notice` components. The footer hover defect is fixed. Not seen rendered, so this score carries the standing UAT caveat; no code defect justifies a deduction.

### Pillar 3: Color (4/4)

No `style=`, `<style>`, hex or arbitrary value in `layouts/customer.blade.php`, `livewire/booking-page.blade.php` or `components/booking/*` (grep clean). Token class counts across those files: `text-ink` 6, `text-ink-muted` 10, `text-accent` 1 (the `sms` link, `sms.blade.php:2`) plus `text-accent-strong` 1 on its hover, `text-danger` 1 (`error.blade.php:2`), `text-on-accent` 1 (primary button, `button.blade.php:4`). Accent fill appears only on the primary button, and accent borders only on hover (`button.blade.php:5`, `day.blade.php:3`, `time.blade.php:3`, `footer.blade.php:4,12`). Accent is never used on h1, body text or icons, as reserved (08-UI-SPEC.md:363).

### Pillar 4: Typography (4/4)

Across the customer views the only size classes are `text-body` (1), `text-heading` (2: day summaries `day.blade.php:3`, the check h2 `booking-page.blade.php:37`) and `text-display` (3: picker h1, booked h1, notice h1). The only weights are `font-normal` (1, on `<body>`) and `font-semibold` (17). `text-center` is alignment, not a size. This matches U-03 exactly.

### Pillar 5: Spacing (3/4)

Only scale classes: `space-y-2`, `space-y-6`, `gap-2`, `px-4`, `py-2`, `py-4`, `py-8`, `pt-2`, `pt-6`, `pb-4`, `pb-8`, `min-h-12`, `min-w-12`. No arbitrary values. The picker's `hidden` attribute works with `space-y-6` (`booking-page.blade.php:13`). Time buttons are `w-full` in a `grid-cols-2 sm:grid-cols-3` grid with `gap-2` (`:23`). Deduction: page bottom padding is 32 px, not the 48 px the table names (priority fix 2). The 375 px grid and 48 px targets are measured by the browser suite (`BookingPageBrowserTest.php:325`); 320 px reflow is not.

### Pillar 6: Experience Design (3/4)

Focus map: the check h2 and error line take focus on appearance (`booking-page.blade.php:37`, `error.blade.php:2`); "Choose a different time" returns focus to the tapped time through `lastTime` and `$nextTick` (`booking-page.blade.php:65`); the booked h1 focuses only after a booking in this visit or a retry (`:72`); the rate-limited heading takes focus and the next Tab reaches "Try again" (`:3-7`), and dead-end headings focus after a successful retry (`:97-109`). `wire:ignore.self` keeps open days across re-renders (`day.blade.php:2`). Rate limit, title per state, noscript, noindex and no-referrer are present (`layouts/customer.blade.php:6-7`). Owner side: Mark done and Cancel visit are hidden when they do not apply, the forecast line covers five cases, the Schedule splits Past and Upcoming by end time so a span over midnight appears once (`Schedule.php:284,294`). Deductions: the in-flight button is `aria-disabled` and still clickable with no end-to-end double-tap test (priority fix 1); this audit observed nothing rendered (the Pest browser suite covers the customer page at 375 px and desktop, and the feature suite covers the dashboard section's empty and error states and the DST-day entry); the UAT walk is still owed.

## Files Audited

- `.planning/phases/08-schedule-and-booking/08-UI-SPEC.md` (read in full)
- `.planning/phases/08-schedule-and-booking/08-17-SUMMARY.md` to `08-23-SUMMARY.md` (headline and accomplishments only)
- `resources/views/layouts/customer.blade.php`
- `resources/views/livewire/booking-page.blade.php`
- `resources/views/components/booking/*.blade.php` (footer, notice, button, day, time, error, sms)
- `app/Livewire/BookingPage.php` (grep: `tryAgain`, `retried`, `rateLimited`, limiter)
- `app/Rooms/ScheduleAndBooking/BookingSettings.php` (`zoneName`)
- `app/Filament/Pages/Schedule.php` (grep: helper text, Past filter)
- `app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php` (grep: job option, validation, forecast lines, helper text)

Not read this round: the 08-01 to 08-16 plans and summaries, 08-CONTEXT.md, `conversation-thread.blade.php`, `ScheduleSection.php`, `VisitActions.php`, `CreateContact.php`. The 17 to 23 plan files were not read in full; status of gap items rests on the summaries' headlines and the current code.
