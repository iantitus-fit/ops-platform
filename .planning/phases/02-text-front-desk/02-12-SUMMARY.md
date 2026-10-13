---
phase: 02-text-front-desk
plan: 12
subsystem: filament-auth
tags: [filament, profile, login, remember-me, owner-alert, business-folder, pest]

requires:
  - phase: 02-text-front-desk
    provides: OwnerAlert with failed_at and failure (plan 02-10); alert timing and sweep (plan 02-11); PhoneNumber::toE164() (Phase 1); BusinessFolder page (plans 02-02, 02-04)
provides:
  - App\Filament\Pages\Auth\EditProfileWithPhone: profile page with a "Mobile phone for alerts" field stored as E.164 (D-18)
  - App\Filament\Pages\Auth\RememberedLogin: login page with remember me ticked by default; Filament's login response returns to the intended URL (D-31)
  - AdminPanelProvider wiring ->login(RememberedLogin::class) and ->profile(EditProfileWithPhone::class)
  - BusinessFolder::alertWarning() and lastFailedAlert() plus an "Alerts" section in the page (D-19)
  - docs/sprints/sprint-1.md Notes for testing and two walk-through lines (D-53)
affects: [02-23 phone check of D-29/D-30, 02.1 owner loop (sign out everywhere, approve by replying)]

plan_head_before: e4c695c06c4a29a7385c9080083cabf535be84a4
plan_head_after: df7d37e52efc9bef5232fb710e02680d06c932ef

actuals:
  tokens: 4200
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Subclass a Filament auth page and override one builder method (form() or getRememberFormComponent())"
    - "Closure rule on a Filament TextInput that calls $fail() with a plain sentence"

key-files:
  created:
    - app/Filament/Pages/Auth/EditProfileWithPhone.php
    - app/Filament/Pages/Auth/RememberedLogin.php
    - tests/Feature/Alerts/ProfileTest.php
  modified:
    - app/Providers/Filament/AdminPanelProvider.php
    - app/Filament/Pages/BusinessFolder.php
    - resources/views/filament/pages/business-folder.blade.php
    - tests/Feature/Filament/BusinessFolderPageTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The phone field uses inputMode('tel') and autocomplete('tel') instead of Filament's ->tel(), because ->tel() adds its own regex rule whose 'format is invalid' message appeared beside the plain-words sentence"
  - "The Alerts section sits inside the live-version block above Files and renders only when there is a warning or a failed alert"

patterns-established:
  - "Owner-facing alert problems are read from the alert row (failed_at/failure) by the Business Folder page, so no alert fails without a trace the owner can see"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "Owner sets a phone on the profile page; it is stored as E.164, junk is refused with 'Use a real-format number like +12025550123.', empty saves null (D-18, T-02-21)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/ProfileTest.php#saves the owner phone as E.164"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/ProfileTest.php#refuses a phone it cannot read, in plain words, and keeps the old one"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/ProfileTest.php#saves an empty phone as null"
        status: pass
    human_judgment: false
  - id: D2
    description: "Login page has remember me ticked, and a signed-out owner who opens an alert link signs in and lands on that conversation (D-31)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/ProfileTest.php#ticks remember me on the login page by default"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/ProfileTest.php#sends a signed-out owner to the conversation an alert link pointed at after they sign in"
        status: pass
    human_judgment: true
    rationale: "The 400-day recaller cookie and the behaviour on a real phone are checked by hand in the D-29/D-30 check of plan 02-23"
  - id: D3
    description: "Business Folder warns when alerts go by text and no one has a phone, and shows the last alert that was not sent with its reason, escaped (D-19)"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#warns when alerts go by text but no one has a phone number on their profile"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#shows the last alert that was not sent with its reason"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/BusinessFolderPageTest.php#escapes the reason an alert failed"
        status: pass
    human_judgment: false
  - id: D4
    description: "Sprint-1 Notes for testing say the owner's own phone is not exempt from the front desk in Phase 2, approve-by-reply is Phase 2.1, and STOP to an alert stops every alert (D-53)"
    requirement: GATE-01
    verification: []
    human_judgment: true
    rationale: "Documentation wording; no test asserts it beyond the acceptance greps"

duration: 25min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 12: Owner phone, remembered login and alert warnings Summary

**Filament profile page with an E.164 alert phone, a remember-me-ticked login that returns to the alert's conversation, and a Business Folder Alerts section that warns when no one has a phone and shows the last failed alert**

## Performance

- **Duration:** about 25 min (start time was not captured at executor start; estimated from the session)
- **Started:** 2026-10-12T01:53:00Z (approximate)
- **Completed:** 2026-10-12T02:18:12Z
- **Tasks:** 1 (tracer)
- **Files modified:** 8

## Accomplishments

- `EditProfileWithPhone` keeps Filament's name, email, password, confirmation and current-password fields and adds "Mobile phone for alerts". A closure rule refuses anything `PhoneNumber::toE164()` cannot read with the DevChat sentence, and the saved value goes through `toE164()`, so an empty box stores null.
- `RememberedLogin` returns the parent's remember checkbox with `->default(true)`. Filament's own `LoginResponse` already ends in `redirect()->intended(...)`, and the test proves a guest who requests a conversation URL lands on it after signing in.
- `BusinessFolder::alertWarning()` returns the D-19 sentence when the live `owner_alert.channels` include `text` and no user has a phone; `lastFailedAlert()` returns the newest alert with `failed_at`. The view shows both under an "Alerts" heading above "Files", with a link to the profile page, escaped echo only.
- `docs/sprints/sprint-1.md` now has the Notes for testing section (D-53, Phase 2.1 mention) and two walk-through lines.

## Task Commits

1. **Task 1 RED: failing profile, login and alert warning tests** - `44d7c28` (test)
2. **Task 1 GREEN: owner phone on the profile, remembered login, alert warnings** - `df7d37e` (feat)

**Plan metadata:** recorded in the docs(02-12) commit that follows this file.

## TDD Gate Compliance

- **RED:** `ProfileTest` (8 tests) and five new `BusinessFolderPageTest` tests were committed first. Of the 10 that failed, 3 failed on planned assertions (`/admin/profile` returned 404 instead of 200; the no-phone warning and the failed-alert line were absent from the page) and 7 errored with "Unable to find component" because `EditProfileWithPhone` and `RememberedLogin` did not exist yet. Semantic assessment: the class-not-found errors are the expected failure for tests whose target is a new class, and each names the planned class; they would not satisfy a strict "assertion failure only" reading, and Pest's JSON summary is not one of the report formats `gsd_run check tdd-red-evidence` supports, so the classifier was not run. Three of the new negative tests (no warning once a phone exists, no failed line when all sent, escaped reason) passed in RED by design as regression guards.
- **GREEN:** the same tests pass after `df7d37e` (80 tests in `tests/Feature/Alerts` and `tests/Feature/Filament`; full suite 532 passed).
- **REFACTOR:** none needed.

## Files Created/Modified

- `app/Filament/Pages/Auth/EditProfileWithPhone.php` - profile page with the alert phone field
- `app/Filament/Pages/Auth/RememberedLogin.php` - login page with remember me ticked
- `app/Providers/Filament/AdminPanelProvider.php` - `->login(RememberedLogin::class)` and `->profile(EditProfileWithPhone::class)`
- `app/Filament/Pages/BusinessFolder.php` - `alertWarning()` and `lastFailedAlert()`
- `resources/views/filament/pages/business-folder.blade.php` - "Alerts" section
- `tests/Feature/Alerts/ProfileTest.php`, `tests/Feature/Filament/BusinessFolderPageTest.php` - tests
- `docs/sprints/sprint-1.md` - Notes for testing and walk-through log

## Decisions Made

- Used `->inputMode('tel')->autocomplete('tel')` rather than `->tel()`: Filament's `tel()` attaches a regex rule that added "The mobile phone for alerts field format is invalid." next to our plain sentence. `PhoneNumber::toE164()` is the single judge of a valid number.
- The Alerts section renders only when there is something to say, so a healthy page stays as it was.

## Deviations from Plan

None - plan executed exactly as written. (The `->tel()` swap above was a design choice inside the plan's "a rule that rejects a value PhoneNumber::toE164() cannot read", found while making the plain-words test pass.)

## Issues Encountered

- The first GREEN run showed two messages on a bad phone because of `->tel()`; fixed as described. The BSD `sed` call used to add an import to the test file failed once and the import was added with the edit tool instead.

## Authentication Gates

None.

## Known Stubs

None.

## Threat Flags

None. The profile phone field is the T-02-21 mitigation (validation and normalisation through `PhoneNumber::toE164`); T-02-19 (400-day recaller) stays accepted per D-31.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ready for plan 02-13. The profile page, remember me and the no-phone warning still need a hand check on a real phone in the D-29/D-30 check of plan 02-23.
- The 400-day remember-me cookie has no "sign out everywhere" yet; that arrives in Phase 2.1.

## Self-Check: PASSED

- Created files exist: EditProfileWithPhone.php, RememberedLogin.php, ProfileTest.php, this SUMMARY.
- Commits `44d7c28` and `df7d37e` are on `gsd/phase-02-text-front-desk`.
- Acceptance criteria greps pass; `php artisan test --compact tests/Feature/Alerts tests/Feature/Filament` (80 passed) and `php artisan test --compact` (532 passed) exit 0.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
