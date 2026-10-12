---
phase: 02-text-front-desk
plan: 11
subsystem: alerts
tags: [owner-alert, scheduler, business-clock, timeline, customer-card, pest]

requires:
  - phase: 02-text-front-desk
    provides: RaiseOwnerAlert, SendOwnerAlert, OwnerAlert (plan 02-10); BusinessClock::isOpenAt() and nextOpening() (plan 02-05); scheduler wiring (plan 02-09)
provides:
  - RaiseOwnerAlert::dueAt(): urgent, open-hours and after_hours right_away alerts are due now; others are due at BusinessClock::nextOpening()
  - App\Jobs\SendDueAlerts: minute sweep that sends due alerts and skips ones whose draft was handled
  - SendOwnerAlert::skip(): ends a delayed alert with a stored reason and a timeline entry
  - ActivityType::OwnerAlerted and the card label "Owner alert"
affects: [02-12 profile page and warnings, 02-15 onward callers of RaiseOwnerAlert, 02.1 owner loop]

plan_head_before: ac4b3e401aae849bcde1e4f5a250aea0e543edb5
plan_head_after: 165f001fd6a9f20baf59d50a123ef2a431c263f7

actuals:
  tokens: 6400
  tasks: 1
  commits: 2

tech-stack:
  added: []
  patterns:
    - "Delayed work as a table row plus a minute sweep, not a delayed queue job"
    - "Convert business-zone dates to the app zone before storing (Eloquent stores wall-clock text)"

key-files:
  created:
    - app/Jobs/SendDueAlerts.php
    - tests/Feature/Alerts/OwnerAlertTimingTest.php
  modified:
    - app/Actions/RaiseOwnerAlert.php
    - app/Actions/SendOwnerAlert.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - routes/console.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A delayed alert is stale only when its message is an outbound message that is no longer a draft; an inbound subject message (a complaint about a customer text) never makes an alert stale"
  - "A skipped alert also gets an owner_alerted timeline entry ('Owner alert not sent: Handled before it was due'), so every alert, sent or not, is on the timeline (D-19)"
  - "nextOpening() is converted to the app time zone before storing, because Eloquent stores a date as its own wall clock"

requirements-completed: [GATE-01]

coverage:
  - id: D1
    description: "Non-urgent alerts raised while closed are stored with due_at at the next opening (Wed 2026-10-14 08:00 Chicago) and sent by the sweep at 08:00, not 07:59"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#holds a draft-waiting alert raised after hours until the shop opens, then sends it"
        status: pass
    human_judgment: false
  - id: D2
    description: "Complaint, asked-for-person and life-safety alerts go out at once while closed, life safety even when the stored folder omits it; after_hours right_away sends at once"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#sends a right-away alert at once even when the shop is closed"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#always treats life safety as urgent, even when the stored folder leaves it off right_away"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#sends at once after hours when the folder says after_hours: right_away"
        status: pass
    human_judgment: false
  - id: D3
    description: "SendDueAlerts is scheduled every minute without overlapping and skips an alert whose draft was handled before it was due"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#is on the scheduler every minute without overlapping"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#skips a delayed alert whose draft was handled before it fell due"
        status: pass
    human_judgment: false
  - id: D4
    description: "Every alert, sent, failed or skipped, adds an owner_alerted entry to the contact's timeline and the card shows it under 'Owner alert'"
    requirement: GATE-01
    verification:
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#puts a sent alert on the customer timeline and shows it on the card"
        status: pass
      - kind: unit
        ref: "tests/Feature/Alerts/OwnerAlertTimingTest.php#puts an alert that could not be sent on the timeline too"
        status: pass
    human_judgment: false

duration: 15min
completed: 2026-10-11
status: complete
---

# Phase 2 Plan 11: After-hours alert timing and timeline Summary

**Alerts raised after hours wait for BusinessClock::nextOpening() unless urgent, go out from a minute SendDueAlerts sweep that skips drafts already handled, and every alert lands on the customer card as an "Owner alert" entry.**

## Performance

- **Duration:** about 15 min
- **Started:** 2026-10-12T01:59Z
- **Completed:** 2026-10-12T02:14Z
- **Tasks:** 1 (tracer, TDD)
- **Files modified:** 8

## Accomplishments
- `RaiseOwnerAlert` computes `due_at`: now when the alert is urgent (right_away list, life safety floor), when the shop is open, or when `after_hours` is `right_away`; otherwise the next opening. Only alerts due now are sent at raise time.
- `SendDueAlerts` (scheduled `everyMinute()->withoutOverlapping()`) sends alerts that have fallen due and skips, with `Handled before it was due`, a waiting alert whose draft is no longer a draft.
- `SendOwnerAlert` writes an `owner_alerted` activity after a send, a failure or a skip, and fires `CustomerRecordChanged`; `CustomerCard::section()` renders it under `Owner alert`.

## Tracer Verification

The tracer slice was verified end to end: `php artisan test --compact tests/Feature/Alerts tests/Feature/Records` (75 passed) and the full suite (519 passed). Result: `Tracer verified end-to-end`.

## TDD Gate Compliance

- **RED** (`91f3805`): `OwnerAlertTimingTest` written first. Run result: 14 tests, 7 passed (behaviours that already held, such as the open-hours send), 7 failed. The failures were on the planned behaviour: `Class "App\Jobs\SendDueAlerts" not found`, `Undefined constant ActivityType::OwnerAlerted`, the scheduler event missing, and `due_at` still equal to now for the after-hours alert. Semantic assessment: the target tests executed and failed on their planned assertions, not on syntax, fixture or load errors.
- **GREEN** (`165f001`): implementation above; all 14 timing tests pass.
- **REFACTOR:** none needed.

## Task Commits

1. **Task 1: after-hours alert timing, minute sweep, timeline entry** - `91f3805` (test, RED), `165f001` (feat, GREEN)

**Plan metadata:** committed separately (docs: complete plan)

## Files Created/Modified
- `app/Jobs/SendDueAlerts.php` - minute sweep; docblock explains table plus sweep over a delayed queue job
- `app/Actions/RaiseOwnerAlert.php` - `dueAt()` rule, injects `BusinessClock`, sends only alerts due now
- `app/Actions/SendOwnerAlert.php` - timeline entry on send/failure/skip, new `skip()`
- `app/Enums/ActivityType.php` - `OwnerAlerted = 'owner_alerted'`
- `app/Records/CustomerCard.php` - `Owner alert` section arm (Pitfall 8)
- `routes/console.php` - schedule entry
- `tests/Feature/Alerts/OwnerAlertTimingTest.php` - 14 tests
- `docs/sprints/sprint-1.md` - two walk-through lines

## Decisions Made
- A delayed alert is stale only when its subject message is outbound and no longer a draft (see deviation 1).
- Skipped alerts also get a timeline entry, to honour "every alert, sent or not" (D-19).
- Dates are converted to the app time zone before storage (see deviation 2).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Stale check limited to outbound messages**
- **Found during:** Task 1 (SendDueAlerts)
- **Issue:** The plan says to skip a draft_waiting or complaint alert when its message "is no longer status draft". A complaint alert's subject can be the customer's inbound text, whose status is never `draft`, so a delayed complaint alert (possible when an owner removes `complaint` from right_away) would always be wrongly skipped.
- **Fix:** `draftWasHandled()` also requires the message direction to be outbound. Matches the flagged assumption that only a handled draft makes an alert stale.
- **Files modified:** app/Jobs/SendDueAlerts.php
- **Committed in:** 165f001

**2. [Rule 1 - Bug] Opening time stored in the wrong zone**
- **Found during:** Task 1 (first GREEN run)
- **Issue:** `nextOpening()` returns a Chicago-zone date; Eloquent stored its wall clock (08:00) and read it back as UTC, so the alert would fall due at 3 am Chicago.
- **Fix:** convert to `config('app.timezone')` before storing.
- **Files modified:** app/Actions/RaiseOwnerAlert.php
- **Committed in:** 165f001

**3. [Rule 2 - Missing critical] Timeline entry for skipped alerts**
- **Found during:** Task 1
- **Issue:** The plan writes the timeline entry only after a send or failure, but the D-19 truth says every alert, sent or not, is on the timeline; a skipped alert would be invisible.
- **Fix:** added `SendOwnerAlert::skip()` which stores skipped_at and the reason and adds `Owner alert not sent: Handled before it was due`; covered by a test.
- **Files modified:** app/Actions/SendOwnerAlert.php, app/Jobs/SendDueAlerts.php
- **Committed in:** 165f001

---

**Total deviations:** 3 auto-fixed (2 bugs, 1 missing critical)
**Impact on plan:** All needed for correctness; no scope creep.

## Issues Encountered
None beyond the deviations above.

## Known Stubs
None.

## Threat Flags
None. T-02-59 is mitigated as planned: delayed alerts are `owner_alerts` rows with `due_at`, sent by a `withoutOverlapping` minute sweep, and a handled draft is skipped with a reason.

## User Setup Required
None - no external service configuration required. Production still needs the one cron line running `php artisan schedule:run` every minute (noted in plan 02-09); until then delayed alerts are not sent.

## Next Phase Readiness
- Ready for plan 02-12 (profile page and warnings): failed and skipped alerts are now on the timeline and in `owner_alerts`.

## Self-Check: PASSED

- Files exist: app/Jobs/SendDueAlerts.php, tests/Feature/Alerts/OwnerAlertTimingTest.php
- Commits exist: 91f3805, 165f001
- Acceptance criteria re-run: console.php contains `Schedule::job(new SendDueAlerts)` and `everyMinute()`; SendDueAlerts contains `Handled before it was due`; ActivityType contains `case OwnerAlerted = 'owner_alerted';`; CustomerCard contains `ActivityType::OwnerAlerted`; test contains `2026-10-14 08:00`; full suite 519 passed.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-11*
