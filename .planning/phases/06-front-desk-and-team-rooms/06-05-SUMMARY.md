---
phase: 06-front-desk-and-team-rooms
plan: 05
subsystem: database
tags: [laravel, eloquent, unique-index, createOrFirst, owner-alerts, sqlite, postgres]

requires:
  - phase: 06-front-desk-and-team-rooms
    provides: 06-04 send claim on owner alerts (the sender side of sent-exactly-once)
provides:
  - owner_alerts.dedupe_key, a nullable string under a unique index
  - OwnerAlert::dedupeKey(), the one place the key is built
  - RaiseOwnerAlert::handle() storing through createOrFirst, sending and returning only the alerts this call created
  - A backfill that keys stored conversation alerts, first of each duplicate group only
affects: [06-06, 06-07, team room, owner alerts]

actuals:
  tokens: 7700
  tasks: 2
  commits: 4
plan_head_before: c31d3826ee1bad79aea9269f5de9ffb416d23fbe
plan_head_after: b0e6cea04f09b59d6aac06826c761888215ebf04

tech-stack:
  added: []
  patterns:
    - "A unique index on a nullable string key built in code, with 0 standing in for a missing id (NULLs never collide)"
    - "createOrFirst plus wasRecentlyCreated: only the call that created the row sends and returns it"
    - "Race simulated by a text-builder subclass that runs the other worker inside build(), between the check and the insert (D-21)"

key-files:
  created:
    - database/migrations/2026_10_27_000002_add_dedupe_key_to_owner_alerts_table.php
    - database/migrations/2026_10_27_000003_backfill_dedupe_key_on_owner_alerts.php
    - database/migrations/2026_10_27_000004_add_unique_dedupe_key_to_owner_alerts_table.php
    - tests/Feature/Alerts/AlertDedupeKeyTest.php
  modified:
    - app/Actions/RaiseOwnerAlert.php
    - app/Models/OwnerAlert.php
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The key is one string, c<conversation>:m<message or 0>:<reason>:u<user or 0>, because a unique index treats every NULL as different"
  - "alreadyRaised() stays as the cheap check before the text is built; the unique key is the guarantee"
  - "forBusiness() stays a plain create() with no key, so business-wide alerts can repeat (D-08)"
  - "The backfill writes the key inline instead of calling OwnerAlert::dedupeKey(), and a test compares the two"

patterns-established:
  - "Duplicate-tolerant backfill: lowest id keeps the key, later duplicates stay NULL so the unique index can be created"

requirements-completed: [ALRT-01]

coverage:
  - id: D1
    description: "Two workers raising the same conversation alert at once store one row and send one text; the loser returns no alert"
    requirement: ALRT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#stores one alert and sends one text when two workers raise the same alert at once"
        status: pass
    human_judgment: false
  - id: D2
    description: "A refused duplicate does not break the surrounding transaction (SQLite here)"
    requirement: ALRT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#keeps working in the same transaction after the duplicate is refused"
        status: pass
    human_judgment: true
    rationale: "The point of the savepoint is Postgres, where a refused insert spoils the transaction; the CI pgsql leg has not run (WINDOWS entry 31)"
  - id: D3
    description: "Business-wide alerts carry no key and repeat freely"
    requirement: ALRT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#stores business-wide alerts with no key and sends every one of them"
        status: pass
    human_judgment: false
  - id: D4
    description: "Alerts that touch but differ (reason, person, message) get different keys; no subject or no phone still de-duplicates"
    requirement: ALRT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#gives each reason its own alert on one message"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#stores one failed alert when nobody has a phone and two workers raise it at once"
        status: pass
    human_judgment: false
  - id: D5
    description: "Backfill keys stored conversation alerts, first of each duplicate group only, and is idempotent"
    requirement: ALRT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Alerts/AlertDedupeKeyTest.php#gives stored alerts their keys and keeps the key on the first of each duplicate group only"
        status: pass
    human_judgment: false

duration: 7min
completed: 2026-10-28
status: complete
---

# Phase 6 Plan 05: Alert De-duplication Key Summary

**Every conversation alert is stored under a unique `dedupe_key` through `createOrFirst`, so two workers raising one alert store and text it once; business alerts carry no key and a duplicate-tolerant backfill keys old rows.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-28T15:40:18Z
- **Completed:** 2026-10-28T15:47Z
- **Tasks:** 2
- **Files modified:** 8 (4 created, 4 modified)

## Accomplishments
- `owner_alerts.dedupe_key` (nullable string, unique index) and `OwnerAlert::dedupeKey()`, the single builder of `c<conversation>:m<message|0>:<reason>:u<user|0>`.
- `RaiseOwnerAlert::handle()` stores with `createOrFirst`, sends only when `wasRecentlyCreated`, and returns only the alerts it created; `alreadyRaised()` stays as the cheap pre-check. `forBusiness()` still uses a plain `create()` with no key (D-08).
- Backfill migration keys each stored conversation alert in id order, keeps the key on the lowest id of a duplicate group, and leaves later duplicates and business alerts NULL; running it twice changes nothing.
- 13 new tests; full suite green on SQLite (2243 passed). No existing test changed.

## TDD Gate Compliance

- **RED (Task 1):** `test(06-05)` commit `30182ad`. Target: "stores one alert and sends one text when two workers raise the same alert at once". `gsd_run check tdd-red-evidence` on the JUnit report returned `RED_EVIDENCE_OK` (`target_test_failed`). Semantic assessment: the target ran and failed on the planned assertion ("Failed asserting that an object is empty", line 108): today's code let the losing worker insert a second row and send a second text (WR-04). Not a load or fixture fault. Other cases in the file failed on the missing `OwnerAlert::dedupeKey()` / missing column, which is expected for the same missing feature.
- **GREEN (Task 1):** `feat(06-05)` commit `bbeb490`; the four Task 1 test files passed (54 tests).
- **RED (Task 2):** `test(06-05)` commit `a85dea9`. Target: "gives stored alerts their keys and keeps the key on the first of each duplicate group only". Classifier returned `RED_EVIDENCE_OK`. Semantic assessment: it ran and failed on the first key assertion (null instead of the expected key) because the scaffolded `up()` was empty. The adjacency and no-phone cases passed already, as the plan expected, since they describe Task 1's behavior.
- **GREEN (Task 2):** `feat(06-05)` commit `b0e6cea`.
- **REFACTOR:** none needed.

## Task Commits

1. **Task 1 (tracer): two workers, one row, one text** - `30182ad` (test), `bbeb490` (feat)
2. **Task 2: backfill and adjacency cases** - `a85dea9` (test), `b0e6cea` (feat)

**Plan metadata:** recorded in the docs commit that follows this summary.

The tracer gate: automated-only verify under `end-of-phase` mode, re-run after Task 1 (54 passed), then expanded.

## Files Created/Modified
- `database/migrations/2026_10_27_000002_add_dedupe_key_to_owner_alerts_table.php` - the nullable key column
- `database/migrations/2026_10_27_000003_backfill_dedupe_key_on_owner_alerts.php` - keys stored conversation alerts, first of each duplicate group
- `database/migrations/2026_10_27_000004_add_unique_dedupe_key_to_owner_alerts_table.php` - the unique index
- `app/Models/OwnerAlert.php` - `dedupe_key` fillable, `dedupeKey()` builder
- `app/Actions/RaiseOwnerAlert.php` - `createOrFirst`, send and return only what this call created
- `tests/Feature/Alerts/AlertDedupeKeyTest.php` - the simulated race, adjacency, no-phone, no-subject and backfill cases
- `docs/BLUEPRINT.md` - section 6 Built bullet and glance row 6
- `docs/sprints/sprint-1.1.md` - walk-through line (plan 06-05)

## Decisions Made
- Followed the plan: one string key with 0 for a missing message or person (Pitfall 6), `createOrFirst` rather than try/catch around `create()` (Pitfall 7), business alerts keyless (D-08).
- Migration timestamps use the plan's `2026_10_27_00000N` names so the column, backfill and index sort in that order.

## Deviations from Plan

None - plan executed exactly as written. Two small test-file adjustments during RED, not code deviations: the "second message" case uses an inbound text as the second subject because the messages table allows only one draft per conversation.

## Issues Encountered
- The first RED record was refused-path-sensitive: the evidence classifier only reads records inside the project directory, so records were staged in a temporary project folder and removed afterwards.

## Hand Check Left Unrun (for UAT)

Per Ian's standing rule for GSD runs, the Postgres check was not paused for: after the phase branch is pushed, the CI pgsql leg should run `tests/Feature/Alerts/AlertDedupeKeyTest.php` green (the refused duplicate inside the test's transaction survived through `createOrFirst`'s savepoint, and many NULL keys under the unique index). Recorded as `unrun-verify` entry 31 in `.planning/WINDOWS.md`. Local option: `docker run -d --rm --name ops-pg -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=ops_test -p 55432:5432 postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Feature/Alerts`.

## Known Stubs

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- ALRT-01 is complete with 06-04's send claim: each alert is stored once and texted once.
- Ready for 06-06.

## Self-Check: PASSED

All created files exist; commits 30182ad, bbeb490, a85dea9, b0e6cea are on the branch; the full suite passed (2243 tests).

---
*Phase: 06-front-desk-and-team-rooms*
*Completed: 2026-10-28*
