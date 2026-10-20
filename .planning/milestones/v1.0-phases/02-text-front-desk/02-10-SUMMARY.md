---
phase: 02-text-front-desk
plan: 10
subsystem: alerts
tags: [owner-alert, sms, text-channel, eloquent, pest, phishing-guard]

requires:
  - phase: 02-text-front-desk
    provides: AlertReason enum and the owner_alert block in rules/sending.md (plan 02-04); business clock and fixed replies (plan 02-05)
  - phase: 01-foundation
    provides: TextChannel interface and FakeTextChannel (plan 01-03), ConversationResource view page (plan 01-06)
provides:
  - users.phone (E.164, nullable) and UserFactory::withPhone()
  - owner_alerts table and App\Models\OwnerAlert (+ factory), morph name owner_alert
  - RaiseOwnerAlert::handle() and isUrgent(): decides who gets an alert, once per recipient per message and reason
  - OwnerAlertText::build() and clean(): code-built alert body, only our link tappable
  - SendOwnerAlert::handle(): sends through TextChannel, never throws, records failure on the alert
affects: [02-11 after-hours timing and timeline entry, 02-12 profile page and warnings, 02-15 onward callers of RaiseOwnerAlert, 02.1 owner loop]

plan_head_before: 405bebaf99307bb72456ef6cf18992651285b6c3
plan_head_after: c3c145b7f254839858787e3d9ea160208fc3314f

actuals:
  tokens: 9150
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Action that records failure on its own row instead of throwing (SendOwnerAlert)"
    - "Quoted customer words pass through one clean() before they enter an outbound text to the owner"
    - "Nullable foreign keys with nullOnDelete so an alert outlives its user or message"

key-files:
  created:
    - app/Actions/RaiseOwnerAlert.php
    - app/Actions/OwnerAlertText.php
    - app/Actions/SendOwnerAlert.php
    - app/Models/OwnerAlert.php
    - database/factories/OwnerAlertFactory.php
    - database/migrations/2026_10_12_000003_add_phone_to_users_table.php
    - database/migrations/2026_10_12_000004_create_owner_alerts_table.php
    - tests/Feature/Alerts/OwnerAlertTest.php
  modified:
    - app/Models/User.php
    - app/Providers/AppServiceProvider.php
    - database/factories/UserFactory.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "clean() order is scheme links, emails, www links, domain-with-path, bare domain with a common ending, then digit runs, so dana@example.com becomes [email] and not dana@[link]"
  - "A bare domain such as evil.com is swapped for [link] only for a list of common endings (com, net, org, io, co, us and similar), because phones link those without www or http"
  - "A contact with no name shows the phone, uncleaned (it is our own record); a contact with neither shows 'A customer'"
  - "The why text passed by the caller is trusted as code-written and is not cleaned; only the name, the customer's last text and the draft are quoted and cleaned"
  - "Cuts keep length minus three characters plus three ASCII dots, so a cut text is exactly the stated length"

patterns-established:
  - "RaiseOwnerAlert is the one door for alerts: later plans call it and never write owner_alerts rows or text the owner themselves"
  - "Failure stored on the row (failed_at plus failure cut to 200 characters), exception also sent to report()"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "Alert text is built by code: name, why, last customer text cut to 120, draft cut to 160, blank line, conversation view link last and once"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#texts the owner a short alert built by code with our link last"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#cuts a long customer text to 120 characters ending in three plain dots"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#cuts a long draft to 160 characters"
        status: pass
    human_judgment: false
  - id: D2
    description: "Links, emails and long digit runs in quoted names, customer text and drafts become [link], [email] and [number]"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#swaps a bare domain and a domain with a path for [link]"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#swaps phone numbers written with spaces, dashes and brackets for [number]"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#swaps a link inside the contact name for [link]"
        status: pass
    human_judgment: false
  - id: D3
    description: "One alert per recipient per message and reason; every user with a phone is alerted"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#sends one alert per recipient for the same message and reason however often it is raised"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#texts every user who has a phone and skips users who have none"
        status: pass
    human_judgment: false
  - id: D4
    description: "An alert never fails silently: no phone and channel failure are stored on the alert and nothing reaches the caller"
    requirement: GATE-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#stores a failed alert and sends nothing when no user has a phone on file"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#stores a channel failure on the alert and raises nothing to the caller"
        status: pass
    human_judgment: false

duration: 3min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 10: Owner Alerts by Text Summary

**Owner alerts by text: users.phone, an owner_alerts row per recipient, a code-built body that quotes the customer with links, emails and long digit runs swapped for placeholders and our dashboard link last, and a send that stores failure instead of throwing**

## Performance

- **Duration:** 3 min
- **Started:** 2026-10-12T02:00:35Z
- **Completed:** 2026-10-12T02:04:03Z
- **Tasks:** 1 (tracer)
- **Files modified:** 12

## Accomplishments

- A held draft raises an alert that reaches every user with a phone on file through the text channel. The body is exactly: `Name: why`, `They wrote: "..."` (cut to 120), `Draft: "..."` (cut to 160, only for a draft), a blank line, and the conversation view link on the last line, once (D-21, D-22).
- Customer words cannot make a second tappable link: `OwnerAlertText::clean()` swaps web addresses, `www.` forms, domain-with-path forms, bare domains with common endings and emails, then digit runs of seven or more, in the name, the quoted text and the draft (T-02-17, Pitfall 16).
- An alert is never silent (T-02-18, D-19): no phone on file stores one failed row with user_id empty and `No phone on file`; a channel error (tested with the Twilio driver, which is not built yet) stores `failed_at` and the cut message, leaves `sent_at` empty and raises nothing.
- Raising the same reason for the same message again sends nothing more, per recipient.

## Task Commits

1. **Task 1 RED:** `ca0a7c0` (test) - 20 failing owner alert tests
2. **Task 1 GREEN:** `c3c145b` (feat) - migrations, OwnerAlert, three actions, factory state, morph map

**Plan metadata:** recorded in the docs commit that follows this summary.

## Files Created/Modified

- `app/Actions/RaiseOwnerAlert.php` - who gets the alert, dedupe, urgent flag, stores and sends
- `app/Actions/OwnerAlertText.php` - builds the body; `clean()` is the phishing guard
- `app/Actions/SendOwnerAlert.php` - one send through TextChannel, never throws
- `app/Models/OwnerAlert.php`, `database/factories/OwnerAlertFactory.php` - the alert record
- `database/migrations/2026_10_12_000003_add_phone_to_users_table.php` - `users.phone`
- `database/migrations/2026_10_12_000004_create_owner_alerts_table.php` - `owner_alerts`, index on `sent_at, due_at`
- `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php` - fillable phone, `withPhone()`, morph name `owner_alert`
- `tests/Feature/Alerts/OwnerAlertTest.php` - 20 tests
- `docs/sprints/sprint-1.md` - three walk-through log lines

## Decisions Made

See key-decisions. The main one: bare domains are matched against a list of common endings rather than any `word.word`, to avoid turning "Mr.Smith" or "3.5" into links while still catching what a phone would make tappable.

## TDD Gate Compliance

The task was `type="tracer"` in a plan of `type: execute`, so no `tdd-red-evidence` record was generated. RED commit `ca0a7c0` (`test(02-10)`) precedes GREEN `c3c145b` (`feat(02-10)`). The RED run failed all 20 tests: 18 on the missing `UserFactory::withPhone()` state or the missing `RaiseOwnerAlert` class, 2 on the missing `OwnerAlert` model class. These are missing-class errors, not assertion failures, the same shape as plan 02-06's RED; the planned assertions only became reachable once the classes existed, and all passed in GREEN with 75 assertions. No REFACTOR commit.

## Deviations from Plan

None - plan executed exactly as written.

Two small additions inside the plan's scope, not deviations: `OwnerAlertText` also swaps `ftp://` addresses, and the failure path calls `report($exception)` so the log has the full error while the row keeps only the cut message.

## Issues Encountered

None.

## Known Stubs

None. Every alert is due now in this plan by design; the after-hours wait and the timeline entry are plan 02-11, and the profile page and warnings are plan 02-12 (both in the plan's stated scope).

## Threat Flags

None. The new surface (an outbound text to the owner's phone quoting customer words) is the one covered by T-02-17 and T-02-18 in the plan's threat model; both mitigations are in code and tested.

## User Setup Required

None - no external service configuration required. Until the owner's phone is saved on their user record, alerts are stored as failed with `No phone on file`. The profile page that sets it is plan 02-12; until then it can be set in a seeder or tinker.

## Next Phase Readiness

- Plan 02-11 can add the after-hours timing inside `RaiseOwnerAlert` (due_at and the unused `skipped_at`) and a timeline note on failure.
- Plans 02-15 onward call `RaiseOwnerAlert::handle($conversation, $reason, $subject, $why)` and should not write alerts themselves.
- Known edge for later: two simultaneous raises for the same message and reason are not blocked by a database constraint (nullable columns make a unique index ineffective); the inbound claim in plan 02-08 already keeps one run per message.

## Self-Check: PASSED

All created files exist; commits `ca0a7c0` and `c3c145b` are on `gsd/phase-02-text-front-desk`; `php artisan test --compact` passes (443 tests, 1820 assertions) and the plan's targeted run (OwnerAlertTest plus MoneyIsIntegerCentsTest) passes 26 tests.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
