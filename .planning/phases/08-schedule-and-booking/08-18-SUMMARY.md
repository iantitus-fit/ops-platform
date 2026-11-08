---
phase: 08-schedule-and-booking
plan: 18
subsystem: safety-and-compliance
tags: [booking-link, data-migration, records, security-record, laravel]

requires:
  - phase: 08-schedule-and-booking
    provides: "08-17: every stored or printed copy of a sent text writes the booking address as /book/[link]; RecordsWriter hides every address when it writes"
provides:
  - "One-time migration that revokes every booking link sent before 08-17 that had not booked, logs the ids, and queues a card redraw per affected contact"
  - "LinksBeforeTheFixTest: the migration run on rows and card files written the old way"
  - "08-SECURITY.md with T-08-01 corrected and T-08-01 and T-08-27 closed, threats_open 0"
  - "The text provider's own log on the attorney list (STATE.md, BLUEPRINT section 10) and the host's request log as a BLUEPRINT open item"
affects: [08-19, 08-20, phase-8-uat, secure-phase-8, first-deploy]

actuals:
  tokens: 10600
  tasks: 2
  commits: 3
plan_head_before: a4c40255db9cf82ead6428b7ce485c63e4f10719
plan_head_after: 5686951a3e785e0bce365c1b14aaaf8e86aad536
commits: 3

tech-stack:
  added: []
  patterns:
    - "A data migration that ends by dispatching CustomerRecordChanged per touched contact, each dispatch in its own try with report(), so an unmounted business folder cannot stop the deploy's migrate step"
    - "Revoke instead of rewrite: a link that cannot book makes every old copy harmless without destroying what was sent"

key-files:
  created:
    - database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php
    - tests/Feature/Booking/LinksBeforeTheFixTest.php
  modified:
    - .planning/phases/08-schedule-and-booking/08-SECURITY.md
    - .planning/STATE.md
    - .planning/WINDOWS.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "Reversibility rated reversible: no row deleted and no text rewritten; putting a link back is one update of revoked_at for the logged ids. The rejected alternative, rewriting stored bodies to /book/[link], would only clean copies made afterwards, would destroy what was sent, and cannot be undone"
  - "Superseded and expired links that had not booked are revoked too: it changes nothing a customer can do and keeps WithdrawUnsentLink from ever putting one back"
  - "The security re-audit (/gsd-secure-phase 8) is still pending: it adds the gap plans' threats from T-08-70 on and sets status"

requirements-completed: [BOOK-02]

coverage:
  - id: D1
    description: "Every booking link minted before 08-17 that has not booked is revoked (open, superseded, expired), and its page says 'This link does not work' and BookAppointment::fromLink refuses it"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#revokes every link sent before the fix that has not booked (D-12)"
        status: pass
    human_judgment: false
  - id: D2
    description: "A booked link keeps working (page shows 'You are booked') and an earlier revocation keeps its first revoked_at"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#leaves a booked link and an earlier revocation as they were (D-12, U-14)"
        status: pass
    human_judgment: false
  - id: D3
    description: "No stored text is rewritten and no row deleted"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#rewrites no stored text (D-12)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Cards are redrawn so no file under the business folder holds a token from before the fix, and a card holds /book/"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#redraws the folder so no file holds a token from before the fix (D-12)"
        status: pass
    human_judgment: false
  - id: D5
    description: "The migration logs the revoked ids, a second run changes nothing, and a throwing card redraw never fails the migration"
    requirement: BOOK-02
    verification:
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#logs the ids it revoked and changes nothing when it runs again"
        status: pass
      - kind: unit
        ref: "tests/Feature/Booking/LinksBeforeTheFixTest.php#never fails on a card redraw that throws"
        status: pass
    human_judgment: false
  - id: D6
    description: "08-SECURITY.md closes T-08-01 and T-08-27 with evidence; the provider's log joins the attorney list; the host's request log becomes an open item"
    requirement: BOOK-02
    verification:
      - kind: other
        ref: "php artisan test --compact (3051 tests, green)"
        status: pass
    human_judgment: true
    rationale: "The wording of the security record and the attorney-list items is Ian's to read; the security re-audit (/gsd-secure-phase 8) is still to run"
  - id: D7
    description: "The migration's whereNotExists update and the join over messages and conversations run green on Postgres"
    requirement: BOOK-02
    verification: []
    human_judgment: true
    rationale: "Needs the CI pgsql leg after Ian pushes the phase branch (WINDOWS entry 76); only SQLite was run here"

duration: 8 min
completed: 2026-11-08
status: complete
---

# Phase 8 Plan 18: Revoke Links Sent Before the Fix Summary

**A one-time migration revokes every booking link sent before 08-17 that had not booked and queues a card redraw for each contact it touched, and 08-SECURITY.md closes T-08-01 and T-08-27 with threats_open 0.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-11-08T17:23:25Z
- **Completed:** 2026-11-08T17:31:36Z (code and record; SUMMARY follows)
- **Tasks:** 2
- **Files modified:** 7 (2 created, 5 modified)

## Accomplishments

- Gap D-12 is closed backward: after the migration runs, a copy or backup of the database or business folder made before the fix, and the text provider's log of those texts, hold no link that can book. A revoked link's page says "This link does not work" and `BookAppointment::fromLink` refuses it.
- A link that booked is untouched (its page still shows "You are booked") and a link revoked earlier keeps its first `revoked_at`. No stored text is rewritten and no row is deleted.
- The migration queues `CustomerRecordChanged` for every contact holding a revoked link or a message holding a booking address, each dispatch in its own `try` with `report()`. RecordsWriter then rewrites the card, `customers/INDEX.md` and `open-leads.md` with `/book/[link]`, so the folder holds no old token.
- 08-SECURITY.md: T-08-01 no longer claims the plain token never reaches the database; T-08-01 and T-08-27 are closed with file and line evidence and test names; `threats_open: 0`, an audit-trail row added, the open-threats section retitled and each ruling bullet marked met.
- The text provider's log (Twilio keeps each body) is on the attorney list in STATE.md and BLUEPRINT section 10; the host's request log is a BLUEPRINT open item for the first deploy.

Gap ids closed: D-12 (going forward by 08-17, backward by this plan). Reversibility: `reversible`. Rejected alternative: rewriting stored bodies to `/book/[link]` (one-way, destroys what was sent, cannot reach existing copies). New WINDOWS entry: 76. The security re-audit (/gsd-secure-phase 8) is pending.

## Task Commits

1. **Task 1: tracer, links before the fix stop working and the folder is redrawn** - `41a68ae` (test, RED: all six cases failed on the missing migration after their setup ran), `4423674` (feat, GREEN)
2. **Task 2: the record** - `5686951` (docs)

**Plan metadata:** the docs(08-18) commit that follows this file.

## Files Created/Modified

- `database/migrations/2026_11_07_000001_revoke_booking_links_sent_before_tokens_were_hidden.php` - revokes unbooked links with one update chosen by `whereNotExists`, logs `booking_link_ids`, queues the redraws; `down()` is empty on purpose
- `tests/Feature/Booking/LinksBeforeTheFixTest.php` - six cases on rows, texts and card files written the way they were before 08-17
- `.planning/phases/08-schedule-and-booking/08-SECURITY.md` - T-08-01 corrected, T-08-01 and T-08-27 closed, audit row, sign-off
- `.planning/STATE.md`, `docs/BLUEPRINT.md` - attorney list gets the provider's log; BLUEPRINT gets the Built extension and the host-log open item
- `docs/sprints/sprint-1.1.md` - one walk-through line marked `(plan 08-18)`
- `.planning/WINDOWS.md` - entry 76, the CI pgsql leg for the new test

## Decisions Made

See key-decisions: reversible, revoke not rewrite, superseded and expired links revoked too.

## TDD Gate Compliance

RED `41a68ae` (test) precedes GREEN `4423674` (feat). RED evidence: all six target cases ran their setup and failed at the migration `require` (file not found), the planned assertion point; no syntax, fixture or zero-test fault. No refactor commit.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The test's old-style card needed a timeline entry to hold the address**
- **Found during:** Task 1 (GREEN run)
- **Issue:** The plan's "conversation A contact's card holds '/book/'" assertion failed because a customer card prints the timeline (activities), not raw message rows, so a factory message alone never reached the card.
- **Fix:** The setup also writes a `ReplySent` activity for the old sent text, as `OutboundSender` does, so the redrawn card carries `/book/[link]` for real. The test also asserts that the old card and old open-leads file did hold tokens before the migration, so the folder search is not vacuous.
- **Files modified:** tests/Feature/Booking/LinksBeforeTheFixTest.php
- **Verification:** all six cases pass; the 81-test Booking verify run passes
- **Committed in:** `4423674`

**2. [Rule 2 - Missing accuracy] The security record would have overstated the provider's log**
- **Found during:** Task 2
- **Issue:** A first draft said the provider's log holds no working links. Texts sent after 08-17 still reach the provider with the working link until it books, is replaced or expires.
- **Fix:** The Closed section says only links sent before 08-17 are revoked and that later texts stay in the provider's log, which is why it is on the attorney list.
- **Files modified:** 08-SECURITY.md
- **Committed in:** `5686951`

---

**Total deviations:** 2 auto-fixed (1 bug in the test setup, 1 accuracy fix in the record)
**Impact on plan:** None on scope; both kept the test and the record honest.

## Issues Encountered

- `CustomerRecordChanged` implements `ShouldDispatchAfterCommit`, so on Postgres (where Laravel wraps a migration in a transaction) the dispatches run after the migration commits. A listener that throws at that point would surface from the commit rather than from the migration's own `try`. In practice the listener only queues a job, so a failure there is a database failure, and a second run is safe because the migration is idempotent. Not changed; noted for the first deploy.
- The full suite ran green on SQLite: 3051 tests, 1 warning (the known Pest browser rmdir warning).

## Known Stubs

None. No hard-coded empty values, placeholder text or unwired components in the files created or modified.

## Threat Flags

None. No new endpoint, auth path or file access; the migration changes only `booking_links.revoked_at` and dispatches the existing card-redraw event.

## User Setup Required

None - no external service configuration required.

## Unrun Hand Check

After Ian pushes the phase branch, the CI pgsql leg should be green for `LinksBeforeTheFixTest` (the `whereNotExists` update and the join over messages and conversations on Postgres). Recorded as WINDOWS entry 76 for end-of-phase UAT, not paused on.

## Next Phase Readiness

- Run `/gsd-secure-phase 8`: it adds the gap plans' threats from T-08-70 on (this plan's T-08-76 to T-08-79 among them) and sets the security status.
- Before the first deploy: check what the host's request log keeps of `/book/` paths (BLUEPRINT open item), and the attorney list now includes the text provider's log.
- Ready for 08-19.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-08*

## Self-Check: PASSED

Created files exist, the three commits (`41a68ae`, `4423674`, `5686951`) are ancestors of HEAD, every acceptance criterion of both tasks passed, the 81-test verify run and the full suite (3051 tests) passed.
