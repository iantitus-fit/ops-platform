# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — Front Desk

**Shipped:** 2026-10-20
**Phases:** 3 (1, 2, 02.1) | **Plans:** 76 | **Sessions:** not tracked

### What Was Built
- The folder drives the app: `icm:sync` validates, versions and activates; a broken change is refused in plain words and the live version stays.
- Texts, web form leads and `/dev/chat` land on one contact and one timeline, and every event re-renders the customer's card in `records/`.
- A laravel/ai text front desk from `agents/*.md`, behind a send-or-draft rule in code: the label floor, the code checks and Jev must agree before anything sends.
- Owner tools in Filament: take over and hand back, approve, edit or discard a draft, the Needs you card, STOP on every outbound path, a Twilio driver behind `TextChannel`.
- Owner-written price and offer sentences sent by code; easy answers that do not wait behind a hard one; a waiting customer who always hears back; Jev outage alerts.

### What Worked
- Every phase verified as passed: Phase 1 7/7 must-haves, Phase 2 5/5 success criteria with 6/6 UAT gaps closed, Phase 02.1 8/8 must-haves.
- UAT by hand found what the suite did not: Phase 2 UAT found six gaps, all diagnosed and fixed in 02-35 to 02-41; Phase 02.1 UAT found two, fixed in quick task 261018-dxy.
- The suite ran on SQLite and PostgreSQL 17 from Phase 2 on and grew from 263 tests (end of Phase 1) to 1659 at 02.1-28 and 1750 at the v1.0 close.
- The cartographer map was re-checked once after each round of code changes (02-34, 02-41, 02.1-15, 02.1-27), so its citations kept landing on the right symbols.

### What Was Inefficient
- Phase 2 was planned as 17 plans and re-sliced to 41 on 2026-10-11 so each fit its context budget, then needed review-fix plans (02-30 to 02-34) and UAT gap plans (02-35 to 02-41).
- Phase 02.1 needed twelve gap-closure plans (02.1-17 to 02.1-28) after its first verification.
- The D-46 invariant test ran as one 1,440-combination transaction that did not finish on PostgreSQL in over an hour; it was split into eight slices of 180.
- The Owner Loop scope was split out as Phase 02.1, renumbered 02.2, then moved to backlog 999.5, and Phase 3 left the v1 roadmap for a seed before the close.
- The six Phase 2 debug sessions stayed at status "diagnosed" after their fixes shipped, and had to be acknowledged at the close.
- GSD's `phase uat-passed 02.1` still lists UAT tests 1 and 2 as blocking, because it credits only a fix plan inside the phase directory and the fix was a quick task.
- In 02.1 UAT the queue worker crashed overnight when the Mac slept, and no health ping ran while it was down.

### Patterns Established
- One plan per wave, run in order, on one working tree (DEC-01), each plan running the full suite.
- A map re-check plan once after each block of code plans.
- Review findings and UAT gaps closed as numbered plans inside the phase, with a disposition file for what was deferred.
- Owner-written words for anything that commits the business (prices, offers, holding replies), picked and sent by code.

### Key Lessons
1. Hand UAT on the real flow found 8 gaps across Phases 2 and 02.1 that the automated suite had passed.
2. Large combination tests ran into database transaction limits on PostgreSQL.
3. Fixes made outside the phase directory (quick tasks) were not credited by GSD's UAT check, and debug session statuses were not updated when their fixes shipped.

### Cost Observations
- Model mix: not tracked (DEC-01 sets Opus for planning, Sonnet for building)
- Sessions: not tracked
- Notable: plan durations recorded in STATE.md ranged from 5 to 100 minutes in Phase 02.1; 652 commits over 10 days.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v1.0 | not tracked | 3 | GSD ingest from the pre-GSD docs; one plan per wave; review and UAT gaps closed as phase plans |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
|-----------|-------|----------|-------------------|
| v1.0 | 1750 | not measured | Twilio driver added with no SDK |

### Top Lessons (Verified Across Milestones)

1. (One milestone so far.)
