---
phase: 02-text-front-desk
plan: 24
subsystem: messaging
tags: [sms, opt-out, stop, twilio, laravel, pest, compliance]

requires:
  - phase: 02-text-front-desk
    provides: OutboundSender chokepoint (02-15, 02-21), RecordInbound and DispatchInbound (02-08), owner reply box (02-23)
provides:
  - contacts.sms_opted_out_at with Contact::isOptedOut() and ContactFactory::optedOut()
  - InboundText::$optOutType and meta opt_out_type on inbound texts
  - STOP branch in RecordInbound (opt-out recorded, no reply, front desk bypassed)
  - OutboundSender::whyBlocked() refuses every send origin for an opted-out contact
  - ActivityType OptedOut and OptedIn with customer card labels
affects: [02-25 START/YES/HELP and opted-out alert, 02-26 Twilio channel reads the same OptOutType]

actuals:
  tokens: 6000
  tasks: 1
  commits: 2
plan_head_before: 8529f61a92d8e72f69be628ff62164d3b0854342
plan_head_after: 4e7a8e10b35bbee1b3914351c684f132bb43401f

tech-stack:
  added: []
  patterns:
    - "Opt-out is a nullable timestamp on the contact; the single outbound chokepoint checks it before every other rule"
    - "A keyword text is stored already dispatched and handled inside the record transaction, so no job or sweep can pick it up"

key-files:
  created:
    - database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php
    - tests/Feature/Inbound/OptOutTest.php
  modified:
    - app/Models/Contact.php
    - database/factories/ContactFactory.php
    - app/Channels/InboundText.php
    - app/Channels/FakeTextChannel.php
    - app/Actions/InboundData.php
    - app/Actions/RecordInbound.php
    - app/Actions/OutboundSender.php
    - app/Enums/ActivityType.php
    - app/Records/CustomerCard.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "STOP is recorded inside the same transaction that stores the message, with dispatched_at and handled_at set at creation, rather than patched afterwards, so a crash cannot leave a STOP queued for the front desk"
  - "A repeated STOP keeps the first opt-out date and writes no second opted_out entry"
  - "OutboundSender takes ActiveConfig in its constructor to print the date in the business time zone"

patterns-established:
  - "whyBlocked() puts compliance refusals ahead of status, pause and phone checks"

requirements-completed: [TXT-01]

coverage:
  - id: D1
    description: "A STOP text opts the contact out, is answered with empty TwiML and no text of our own, and never reaches the front desk"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#records a STOP on the contact with no reply of our own and keeps the keyword away from the front desk (D-39)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#answers the STOP webhook with empty TwiML (D-39)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every outbound origin (owner, automated, life-safety, approved draft) is refused for an opted-out number, with the sentence 'Opted out by texting STOP on <date>.'"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#refuses every origin once the contact has opted out, with the date in the business time zone (D-40)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#refuses to approve a live draft after STOP and sends nothing"
        status: pass
    human_judgment: false
  - id: D3
    description: "The owner's reply box shows the opt-out sentence in place of the form, and the contact card shows the opted_out entry"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#shows the owner why they cannot reply, in place of the form, and refuses the reply (D-40)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#shows the opt-out on the contact card"
        status: pass
    human_judgment: false

duration: 20min
completed: 2026-10-12
status: complete
---

# Phase 02 Plan 24: STOP opt-out Summary

**A customer who texts STOP is recorded as opted out on their contact with no reply of ours, skips the front desk, and is refused by every outbound path (owner, agent, approved draft, life-safety line) with the reason shown in the reply box.**

## Performance

- **Duration:** about 20 min
- **Completed:** 2026-10-12
- **Tasks:** 1 (tracer, TDD)
- **Files modified:** 12 (2 created, 10 modified)

## Accomplishments

- `contacts.sms_opted_out_at` plus `Contact::isOptedOut()` and `ContactFactory::optedOut()`.
- `InboundText::$optOutType` (read from Twilio's `OptOutType`, upper-cased) carried into message meta as `opt_out_type`.
- `RecordInbound` stores a STOP already dispatched and handled, opts the contact out (first date kept), adds an `opted_out` timeline entry, announces the record change, and returns before the safety check and the queue. The webhook still answers empty TwiML.
- `OutboundSender::whyBlocked()` checks opt-out before anything else for every origin, so owner replies, automated texts, approved drafts and the life-safety line are all refused with `Opted out by texting STOP on Oct 13.` (date in the business zone). The owner's reply box already renders `whyBlocked(Owner)`, so it now shows that sentence in place of the form.
- `ActivityType::OptedOut` / `OptedIn` and customer card labels `Opted out of texts` / `Opted back in to texts`.

## Task Commits

1. **Task 1: STOP end to end** (tracer, TDD)
   - RED: `c34ffd2` test(02-24): add failing STOP opt-out tests
   - GREEN: `4e7a8e1` feat(02-24): record STOP and block every text to that number

**Plan metadata:** committed with this SUMMARY (docs: complete plan).

## TDD Gate Compliance

- **RED:** `tests/Feature/Inbound/OptOutTest.php` (11 tests). Run before any implementation: 7 failed and 3 errored, 1 passed (the empty TwiML test, which asserts behavior that already holds and is kept as a regression guard). Failures were on the planned behavior: `whyBlocked()` returned null for all three origins, the owner reply box and draft approval were not refused, the card had no opted-out entry, and `Contact::isOptedOut()` and `ContactFactory::optedOut()` did not exist. Semantic assessment: the target tests executed and failed for the intended reasons, not from fixture or syntax faults. (Pest, not a TAP/JUnit runner, so the `tdd-red-evidence` classifier was not applicable; evidence is the unmodified Pest JSON summary.)
- **GREEN:** same file passes (11/11), plus `tests/Feature/Records` (52 passed) and the full suite (816 passed).
- **REFACTOR:** none needed.

Tracer verification: both `<automated>` commands re-run end to end after the GREEN commit and passed; no expansion tasks follow in this plan.

## Files Created/Modified

- `database/migrations/2026_10_12_000009_add_sms_opt_out_to_contacts_table.php` - nullable `sms_opted_out_at` timestamp (no foreign key, so no SQLite table rebuild)
- `tests/Feature/Inbound/OptOutTest.php` - STOP, empty TwiML, repeat STOP, three origins, send chokepoint, reply box, draft approval, card, factory
- `app/Models/Contact.php`, `database/factories/ContactFactory.php` - fillable, cast, `isOptedOut()`, `optedOut()`
- `app/Channels/InboundText.php`, `app/Channels/FakeTextChannel.php`, `app/Actions/InboundData.php` - `optOutType` carried from webhook to message meta
- `app/Actions/RecordInbound.php` - STOP branch inside the record transaction, early return in `handle()`
- `app/Actions/OutboundSender.php` - opt-out refusal first in `whyBlocked()`; `ActiveConfig` injected for the date
- `app/Enums/ActivityType.php`, `app/Records/CustomerCard.php` - new timeline types and labels
- `docs/sprints/sprint-1.md` - walk-through log line for this plan

## Decisions Made

- The STOP is recorded inside the same transaction as the message (dispatched_at and handled_at set at creation), so there is no window where a STOP is stored but still claimable by `DispatchInbound`, `ProcessInbound` or the sweep. A webhook retry of the same STOP finds the message already recorded and `DispatchInbound` no-ops on the set `dispatched_at`.
- A second STOP keeps the first date and adds no second timeline entry.
- Only an exact `STOP` from the provider triggers the branch here; START and HELP, the keyword backup matcher, the fake channel's simulation and the opted-out owner alert belong to plan 02-25.

## Deviations from Plan

None - plan executed exactly as written. One minor note: the plan listed `OutboundSender::whyBlocked()` as the only change in that file; it also gained an `ActiveConfig` constructor dependency to format the date in the business time zone, which `displayTimezone()` (named in the plan's interfaces) requires. No call site constructs `OutboundSender` by hand, so nothing else changed.

## Issues Encountered

None. `php artisan migrate` ran cleanly on the local dev database.

## Known Stubs

None.

## Threat Flags

None. T-02-47 (opt-out overrides every sender) and T-02-48 (no app-sent confirmation) are mitigated and covered by tests.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for 02-25. Known follow-up that 02-25 is planned to cover: an opted-out customer's life-safety line is now refused by the chokepoint; `SendLifeSafetyLine` already catches that refusal, pauses the conversation and alerts the owner with "Safety message could not be sent", but the dedicated "customer opted out, call them" alert wording is 02-25's.

## Self-Check: PASSED

- Created files found on disk: the migration and `OptOutTest.php`.
- Commits `c34ffd2` and `4e7a8e1` are ancestors of HEAD; `git rev-list --count` from the recorded base gives 2.
- Acceptance criteria: migration contains `sms_opted_out_at`; `InboundText.php` contains `optOutType`; `OutboundSender.php` contains `Opted out by texting STOP on`; OptOutTest asserts nothing was sent after STOP and passes; both automated commands exit 0.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
