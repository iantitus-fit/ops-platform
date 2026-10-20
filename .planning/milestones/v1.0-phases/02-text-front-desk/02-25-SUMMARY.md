---
phase: 02-text-front-desk
plan: 25
subsystem: messaging
tags: [sms, opt-out, stop, start, help, twilio, 21610, laravel, pest, compliance]

requires:
  - phase: 02-text-front-desk
    provides: sms_opted_out_at, Contact::isOptedOut(), STOP branch in RecordInbound, whyBlocked() opt-out refusal, OptedOut/OptedIn activity types (02-24); OutboundSender chokepoint (02-15); SendLifeSafetyLine (02-21); RaiseOwnerAlert and SendOwnerAlert (02-10)
provides:
  - App\Channels\TextKeywords (OPT_OUT_WORDS, OPT_IN_WORDS, HELP_WORDS, twilioOptOutType, actionFor)
  - App\Channels\RecipientOptedOut (Twilio error 21610)
  - FakeTextChannel opt-out simulation (OptOutType from the body, own opted-out list, optOut, optIn, refused, refusal on send)
  - RecordInbound keyword branch for stop, start and help; YES re-opts only an opted-out contact
  - RunFrontDesk opted_out_message alert for a customer who opted out and wrote again
  - OutboundSender stale-record path (carrier refusal marks the contact opted out, message failed, plain sentence)
  - SendOwnerAlert Pitfall 17 sentence for an owner phone that texted STOP
affects: [02-26 Twilio channel (maps 21610 to RecipientOptedOut), 02-27, Phase 3 booking copy (avoid asking for the word cancel)]

actuals:
  tokens: 11300
  tasks: 2
  commits: 4
plan_head_before: ba8fc9e1a1c539e345e08bbf47c440bb62831a59
plan_head_after: bebb701b62005c6a48d37af06e993086a83744e6

tech-stack:
  added: []
  patterns:
    - "Keyword words live in class constants and are compared in PHP, never SQL LIKE"
    - "The fake channel behaves like the carrier: it sets OptOutType from the body, keeps its own opt-out list and refuses a send to an opted-out number"
    - "A carrier refusal is a typed exception caught before the generic channel failure, rethrown as a DomainException with the original as previous"

key-files:
  created:
    - app/Channels/TextKeywords.php
    - app/Channels/RecipientOptedOut.php
    - tests/Unit/Channels/TextKeywordsTest.php
  modified:
    - app/Channels/FakeTextChannel.php
    - app/Actions/RecordInbound.php
    - app/Actions/RunFrontDesk.php
    - app/Actions/OutboundSender.php
    - app/Actions/SendOwnerAlert.php
    - app/Livewire/DevChat.php
    - tests/Feature/Inbound/OptOutTest.php
    - tests/Feature/Alerts/OwnerAlertTest.php
    - tests/Feature/DevChatTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The opted-out alert in RunFrontDesk runs after answerDanger, not first of all, so the life-safety second chance still runs and its refusal is recorded"
  - "An opted-out customer's safety text gets both alerts: the urgent life_safety one (Safety message could not be sent) and opted_out_message (call them); RaiseOwnerAlert dedupes by reason, so they are different alerts"
  - "A stale-record refusal (21610) in RunFrontDesk raises front_desk_failed with the words The customer opted out of texts, so call them; RunFrontDesk tells it from other failures by the exception's previous being RecipientOptedOut"
  - "START from a contact who is not opted out is handled (message marked handled, no reply) but writes no opted_in entry"

patterns-established:
  - "TextKeywords::actionFor(optOutType, body, optedOut) is the one place that decides stop, start, help or ordinary message"

requirements-completed: [TXT-01]

coverage:
  - id: D1
    description: "TextKeywords matches Twilio's whole-body keywords case-insensitively and a sentence containing a keyword is an ordinary message"
    requirement: TXT-01
    verification:
      - kind: unit
        ref: "tests/Unit/Channels/TextKeywordsTest.php#reads what Twilio would call a customer text"
        status: pass
      - kind: unit
        ref: "tests/Unit/Channels/TextKeywordsTest.php#decides what a text does"
        status: pass
    human_judgment: false
  - id: D2
    description: "STOP (with or without OptOutType), START, YES and HELP behave like the carrier's keywords; a plain yes stays an answer for a contact who is not opted out"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#lets YES from a contact who is not opted out reach the front desk as an ordinary answer"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#clears the opt-out on START with an opted_in entry and no reply of our own"
        status: pass
    human_judgment: false
  - id: D3
    description: "The fake channel simulates Advanced Opt-Out: OptOutType from the body, its own opted-out numbers, RecipientOptedOut on send, refused() record"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#keeps its own opted-out numbers like the carrier does and refuses a send to one (21610)"
        status: pass
    human_judgment: false
  - id: D4
    description: "An opted-out customer who writes again gets nothing and the owner is alerted with opted_out_message"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#sends nothing to an opted-out customer who writes again and tells the owner so they can call"
        status: pass
    human_judgment: false
  - id: D5
    description: "Every outbound path is blocked for an opted-out contact: agent reply, holding, handoff, life-safety line, approved draft, owner reply"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php (agent, holding and handoff, request-for-a-person, safety line, approve-draft and owner-reply cases)"
        status: pass
    human_judgment: false
  - id: D6
    description: "A carrier refusal for a stale local record marks the contact opted out, stores the message failed and tells the owner; an owner phone that texted STOP gets the Pitfall 17 sentence on the alert and the Business Folder page"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OptOutTest.php#corrects a stale local record when the carrier refuses an agent reply"
        status: pass
      - kind: integration
        ref: "tests/Feature/Alerts/OwnerAlertTest.php#stores an alert as failed with how to undo it when the owner phone has opted out"
        status: pass
    human_judgment: false

duration: 7 min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 25: Carrier keywords, simulated opt-out and the opted-out alert Summary

**TextKeywords matches Twilio's STOP, START and HELP words (YES only re-opts an opted-out contact), the fake channel acts like Advanced Opt-Out and refuses opted-out numbers with RecipientOptedOut (21610), and every outbound path is proven blocked, with the owner told to call.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-10-12T03:47:02Z
- **Completed:** 2026-10-12T03:54:00Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments

- `TextKeywords` holds the word lists and two functions: `twilioOptOutType()` (what Twilio would report) and `actionFor()` (stop, start, help or null). `stop the noise please` and `yes please` are ordinary messages; a bare YES re-opts only a contact who is opted out (Pitfall 14).
- `FakeTextChannel` sets `OptOutType` from the body when the request has none, keeps its own opted-out numbers (updated on STOP and START), refuses a send to one with `RecipientOptedOut` and records it in `refused()`. DevChat sets `optOutType` the same way.
- `RecordInbound` handles stop, start and help inside the record transaction (message stored already dispatched and handled, no reply, no front desk, no safety check); START writes an `opted_in` entry "Opted back in by texting START".
- `RunFrontDesk` alerts the owner with `opted_out_message` when an opted-out customer writes again; the agent is never asked and nothing is sent.
- `OutboundSender` catches `RecipientOptedOut`: message stored failed with `Opted out (21610)`, contact marked opted out, note "Not sent: the customer has opted out of texts", owner told "The customer has opted out of texts."
- `SendOwnerAlert` stores "Your phone opted out of texts (STOP). Text START to the business number to get alerts again." as the failure; the Business Folder page already shows the newest failed alert.

## RED evidence and TDD gates

Task 1 is `type="tracer"`; its RED and GREEN were committed as test then feat. Task 2 is `tdd="true"`.

- **RED 1 (`02294c5`):** 7 behavior failures plus 32 errors ("Class App\Channels\TextKeywords not found", undefined `FakeTextChannel::optOut()`), each on the planned behavior. Pest's JSON line is not a format the classifier reads, so this RED rests on the semantic assessment only: the target tests ran and failed for the intended reason.
- **RED 2 (`c638484`):** 4 failures, each the planned behavior missing (no `OptedOutMessage` beside the safety alert, contact not marked opted out on 21610, no owner notification, raw Twilio sentence stored on the owner alert). The agent-race, holding, handoff and request-for-a-person cases already passed through the 02-24 chokepoint and Task 1, as the plan expected. The stale-record target was re-run with `--log-junit` and `gsd_run check tdd-red-evidence` returned `RED_EVIDENCE_OK` (reason `target_test_failed`); semantic assessment: it failed on `isOptedOut()` being false because `OutboundSender` did not yet catch `RecipientOptedOut`.
- **GREEN:** `2177f91` (Task 1), `bebb701` (Task 2). No refactor commit was needed.
- Tracer gate: the Task 1 automated command was re-run after the commit (179 tests, passed) and expansion went ahead.

## Task Commits

1. **Task 1 RED:** `02294c5` (test)
2. **Task 1 GREEN:** `2177f91` (feat)
3. **Task 2 RED:** `c638484` (test)
4. **Task 2 GREEN:** `bebb701` (feat)

**Plan metadata:** recorded in the docs(02-25) commit that follows this file.

## Files Created/Modified

- `app/Channels/TextKeywords.php` - word lists, `twilioOptOutType()`, `actionFor()`
- `app/Channels/RecipientOptedOut.php` - typed refusal for an opted-out number (21610)
- `app/Channels/FakeTextChannel.php` - OptOutType from the body, opted-out list, `optOut()`, `optIn()`, `refused()`, refusal on send
- `app/Actions/RecordInbound.php` - keyword branch for stop, start and help
- `app/Actions/RunFrontDesk.php` - opted-out alert; stale-record failure alert wording
- `app/Actions/OutboundSender.php` - `CARRIER_OPTED_OUT`, `recordCarrierOptOut()`
- `app/Actions/SendOwnerAlert.php` - `OWNER_OPTED_OUT` sentence
- `app/Livewire/DevChat.php` - optOutType from `TextKeywords`
- `tests/Unit/Channels/TextKeywordsTest.php`, `tests/Feature/Inbound/OptOutTest.php`, `tests/Feature/Alerts/OwnerAlertTest.php`, `tests/Feature/DevChatTest.php` - new and extended tests
- `docs/sprints/sprint-1.md` - walk-through log line (`match`, catch order, exception chaining)

## Decisions Made

- The opted-out check in `RunFrontDesk` sits after `answerDanger()`, not "first of all" as the plan words it. Putting it first would skip the life-safety second chance, and the plan's own Task 2 behavior row needs the safety alert to read "Safety message could not be sent".
- That safety alert is not suppressed by the opted-out alert. They are different reasons, so the owner of an opted-out customer who texts "I smell gas" gets the urgent safety alert and the "call them" alert. This is how the owner learns why the safety line did not go out. The paused-message dedupe was deliberately not copied.
- `START` from a contact who is not opted out is marked handled with no activity entry, so the timeline does not show an opt-in that changed nothing.
- `RunFrontDesk` recognises a stale-record refusal by `getPrevious() instanceof RecipientOptedOut`, because the owner-facing sentence is exactly "The customer has opted out of texts." and cannot also carry the `NOT_SENT` prefix.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Stale-record refusal would not have alerted the owner in RunFrontDesk**
- **Found during:** Task 2
- **Issue:** The plan says `OutboundSender` throws "The customer has opted out of texts." and "RunFrontDesk already alerts on send failures". It only alerts when the message starts with `NOT_SENT`; any other `DomainException` becomes a `send_refused` draft and no `front_desk_failed` alert.
- **Fix:** `OutboundSender` rethrows with the `RecipientOptedOut` as `previous`; `RunFrontDesk` checks that and raises `front_desk_failed` with "The customer opted out of texts, so call them".
- **Files modified:** `app/Actions/OutboundSender.php`, `app/Actions/RunFrontDesk.php`
- **Verification:** `OptOutTest` "corrects a stale local record when the carrier refuses an agent reply" passes
- **Committed in:** `bebb701`

**2. [Rule 2 - Missing Critical] Order of the opted-out check in RunFrontDesk, and the safety alert not suppressed**
- **Found during:** Task 1 and Task 2 (see Decisions Made)
- **Issue:** Following "first of all" literally would skip the life-safety second chance and leave the owner of an opted-out customer without the reason the safety line did not go out.
- **Fix:** Check runs after `answerDanger()`; no dedupe against an earlier alert for the same text.
- **Files modified:** `app/Actions/RunFrontDesk.php`
- **Verification:** `OptOutTest` "sends no safety line to an opted-out customer but still alerts the owner and says why" passes
- **Committed in:** `2177f91`, `bebb701`

---

**Total deviations:** 2 auto-fixed (1 bug, 1 missing critical)
**Impact on plan:** Both needed for the plan's own behavior rows to hold. No scope creep.

## Issues Encountered

None. The full suite passed at the end (865 tests). `database/database.sqlite` was not touched and no migration was run; there is no schema change in this plan.

## User Setup Required

None - no external service configuration required. (Plan 02-26 carries the Twilio Messaging Service and the optional removal of YES from Twilio's opt-in keywords.)

## Known Stubs

None.

## Threat Flags

None. T-02-49 (YES swallowed) is mitigated by `actionFor()` and its tests; T-02-62 (carrier list ahead of ours, owner phone opted out) is mitigated by `recordCarrierOptOut()` and the `OWNER_OPTED_OUT` sentence.

## Next Phase Readiness

- Plan 02-26 can map Twilio's HTTP 400 / code 21610 to `RecipientOptedOut` and everything above it already reacts.
- Possible polish for later: the safety alert for an opted-out customer reads "Safety message could not be sent" with the separate "call them" alert beside it; a single dedicated sentence on the safety alert would need a change to `SendLifeSafetyLine`, which this plan did not list.

## Self-Check: PASSED

- Created files present: `app/Channels/TextKeywords.php`, `app/Channels/RecipientOptedOut.php`, `tests/Unit/Channels/TextKeywordsTest.php`
- Commits present on the branch: `02294c5`, `2177f91`, `c638484`, `bebb701`
- Acceptance greps pass for both tasks; `php artisan test --compact` passes (865 tests).

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
