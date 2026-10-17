---
phase: quick-261016-t5v
plan: 01
subsystem: front-desk
tags: [review-fixes, owner-alerts, send-rule, agent-parsing]
requires: [phase 02.1 gap run]
provides:
  - back-up alert headed "Safety check is back" under the safety_check_down reason
  - possible_danger parsed fail-closed
  - blank-words code holds keep their reason; priceAlone refuses promise, discount and complaint labels
  - added-info reply (not silence) when every model is down during a current wait
affects: [app/Actions/RunFrontDesk.php, app/Ai/AgentReply.php, app/SendRule/HeldReasons.php, app/SendRule/SafetyCheckStatus.php]
key-files:
  modified:
    - app/Actions/OwnerAlertText.php
    - app/Actions/RaiseOwnerAlert.php
    - app/SendRule/SafetyCheckStatus.php
    - app/Ai/AgentReply.php
    - app/Actions/RunFrontDesk.php
    - app/SendRule/HeldReasons.php
    - .planning/phases/02.1-faster-answers/02.1-REVIEW-DISPOSITION.md
    - .planning/phases/02.1-faster-answers/02.1-CONTEXT.md
decisions:
  - "No new AlertReason: the back-up alert passes a title to forBusiness(), so the forced-urgent list is unchanged (Ian, 2026-10-16)"
  - "Every model down during a current wait sends the added-info reply once (Ian, 2026-10-16; D-29, D-51)"
metrics:
  tasks: 3
  tests: 1677 passing (14709 assertions)
status: complete
actuals:
  tokens: 40000
  tasks: 3
  commits: 5
plan_head_before: 1376832ad87511785d3a593f7d2ef5c476707310
plan_head_after: b254c33e8ae7caaef8cae849161a6471b7db5032
---

# Quick 261016-t5v: Phase 02.1 review fixes Summary

WR-01, WR-02, WR-03 and Ian's item 6 are fixed tests first, and the disposition and context files record the outcome. The full suite passes: 1677 tests, 14709 assertions.

## Commits (local, branch gsd/phase-02.1-faster-answers, nothing pushed)

| Commit | What |
|--------|------|
| f1f3aa8 | test: back-up alert headline, fail-closed possible_danger (RED) |
| 3e45c87 | fix: WR-01 and WR-02 |
| 3165084 | test: blank promise words, waiting customer never silent (RED) |
| e07267f | fix: WR-03 and item 6 |
| b254c33 | docs: disposition and context |

## What changed

- **WR-01.** `OwnerAlertText::forBusiness()` and `RaiseOwnerAlert::forBusiness()` take an optional `?string $title`. `SafetyCheckStatus::BACK_UP_HEADLINE` is "Safety check is back", and the back-up words dropped their own "The safety check is back." sentence. The stored reason is still `safety_check_down` (urgent, sent at once). `app/Enums/AlertReason.php` is unchanged.
- **WR-02.** `AgentReply::fromStructured()` throws when `possible_danger` is missing or not a boolean, so `RunFrontDeskAgent` tries the next model and all-models-failing ends in the D-51 holding reply and `front_desk_failed`.
- **WR-03.** The blank-words branch in `RunFrontDesk::handle()` now keeps whatever `codeHoldReason()` returned (label:promise reads "Held: makes a promise"). `HeldReasons::priceAlone()` also refuses a recorded promise, discount or complaint label, whether the label check passed, failed or never ran.
- **Item 6.** `replyWhenFrontDeskCannotAnswer()` sends `sendAddedInfoReply()` with empty meta when `holdingAlreadySent()` is true, otherwise the holding or complaint holding reply as before. A complaint reading in that state still raises the complaint alert and sends the added-info reply, not the complaint reply. The D-46 matrix lost its silent-by-design exemption and passes.

## Customer-visible side effects

- **WR-03.** A blank-words discount ask with no live offer that also has another code-hold reason (for example a complaint, Jev down or unsure about a person) now records that code-hold reason and gets the general holding reply. Before, it recorded `discount:no_live_offer`. The complaint and Jev-down cases already worked this way (HeldCodeAnswerTest, JevDownTest).
- **Item 6.** A customer who writes while every model is down and whose earlier wait is current now gets one added-info text. Before, they got nothing. A complaint reading gets the added-info reply, not the complaint reply.

## Deviations from Plan

**1. [Rule 1 - tests pinned the old behavior] `PossibleDangerTest` "reads a missing, null or malformed possible_danger as false and never throws"**
- Found during: Task 1, full suite after the fix.
- Fix: the test now expects `UnexpectedValueException` for the same four inputs, under a WR-02 title. It was modified, not deleted.
- Commit: 3e45c87.

**2. [Test expectation] The WR-03 end-to-end test**
- The text that goes out for the later sentence includes the partial line ("I've passed your other question to Mike, and he'll follow up on it."), because the draft stays live. The expectation was set to sentence plus partial line in e07267f.

Planned test changes made as described: the D-51 second-text test now expects the added-info reply, and the `FrontDeskInvariantTest` exemption was removed. No existing test pinned the old price:no_sentence or discount:no_live_offer fall-through under a code-hold reason.

## Walk-through log

No line added to `docs/sprints/sprint-1.1.md`. A nullable parameter with a default (`?string $title = null`) and an `is_bool` check are plain PHP and already appear in earlier log lines. The log line "A flag read strictly (plan 02.1-21)" describes the old `=== true` read of `possible_danger`; it is history and was left as written.

## Follow-ups

- **map/ cards** cite `RunFrontDesk` and `AgentReply` line numbers and describe the D-51 path and the `possible_danger` parse as they were at cd1bc79. The map says the source wins; a refresh is Ian's call.
- No live or hand check was needed, so nothing was added to `.planning/WINDOWS.md`.
- A Postgres run (`DB_CONNECTION=pgsql php artisan test --compact`) was not done; CI covers it (DEC-06).

## Known Stubs

None.

## Threat Flags

None. The new reply path goes through `SendCombinedReply` and the outbound sender with the run's reading, as the plan's T-261016-t5v-04 requires.

## Self-Check: PASSED

Commits f1f3aa8, 3e45c87, 3165084, e07267f and b254c33 are on the branch. `app/Enums/AlertReason.php`, `app/Ai/Agents/FrontDeskAgent.php` and `app/Ai/RunFrontDeskAgent.php` are unchanged from 1376832. Task 3's verify checks pass.
