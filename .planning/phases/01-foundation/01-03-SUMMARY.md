---
phase: 01-foundation
plan: 03
subsystem: inbound-records
tags: [laravel, eloquent, libphonenumber, twilio-shaped-webhook, idempotency, pest]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: "Plan 01-01: ActiveConfig, icm_versions, test harness (temp copy of examples/example-hvac)"
provides:
  - "contacts, conversations, messages and activities tables with the one-open-conversation partial unique index and the (provider, provider_message_id) unique index"
  - "App\\Actions\\RecordInbound, the single entry point plans 01-04 (forms) and 01-07 (/dev/chat) reuse"
  - "TextChannel interface with FakeTextChannel and a TwilioTextChannel stub, driver chosen by config channels.text.driver"
  - "POST /webhooks/text/fake (Twilio-shaped, empty TwiML reply, 404 unless the fake driver is on, CSRF-exempt)"
  - "App\\Support\\PhoneNumber::toE164 (US default region, isValidNumber gate)"
  - "CustomerRecordChanged event and empty ProcessInbound job fired after commit for every new inbound"
affects: [01-04 web form, 01-05 customer cards, 01-06 dashboard, 01-07 dev chat, phase-02 agent and Twilio]

# Actuals (#2632)
actuals:
  tokens: 16561
  tasks: 3
  commits: 6

# Tech tracking
tech-stack:
  added: [giggsey/libphonenumber-for-php-lite 9.0.41]
  patterns:
    - "Channel lives on messages, not conversations; one open conversation per contact enforced by the database"
    - "Idempotent inbound: pre-insert lookup, composite unique index, whole-transaction rollback on UniqueConstraintViolationException"
    - "Nested DB::transaction as a savepoint around the conversation create"
    - "Dispatch ProcessInbound and CustomerRecordChanged only after the transaction commits"
    - "TextChannel bound to a config-reading closure; FakeTextChannel singleton keeps its sent() list"

key-files:
  created:
    - app/Actions/InboundData.php
    - app/Actions/RecordInbound.php
    - app/Channels/TextChannel.php
    - app/Channels/InboundText.php
    - app/Channels/FakeTextChannel.php
    - app/Channels/TwilioTextChannel.php
    - app/Events/CustomerRecordChanged.php
    - app/Jobs/ProcessInbound.php
    - app/Http/Controllers/FakeTextWebhookController.php
    - app/Http/Requests/FakeTextWebhookRequest.php
    - app/Http/Middleware/EnsureTextDriverIsFake.php
    - app/Support/PhoneNumber.php
    - app/Models/Contact.php
    - app/Models/Conversation.php
    - app/Models/Message.php
    - app/Models/Activity.php
    - app/Enums/ContactStatus.php
    - app/Enums/ConversationStatus.php
    - app/Enums/MessageChannel.php
    - app/Enums/MessageDirection.php
    - app/Enums/MessageAuthor.php
    - app/Enums/MessageStatus.php
    - app/Enums/ActivityType.php
    - config/channels.php
    - database/migrations/2026_10_11_131450_create_contacts_table.php
    - database/migrations/2026_10_11_131451_create_conversations_table.php
    - database/migrations/2026_10_11_131452_create_messages_table.php
    - database/migrations/2026_10_11_131453_create_activities_table.php
    - database/factories/ContactFactory.php
    - database/factories/ConversationFactory.php
    - database/factories/MessageFactory.php
    - database/factories/ActivityFactory.php
    - tests/Feature/Inbound/FakeTextWebhookTest.php
    - tests/Feature/Inbound/TextChannelTest.php
  modified:
    - composer.json
    - composer.lock
    - .env.example
    - routes/web.php
    - bootstrap/app.php
    - app/Providers/AppServiceProvider.php
    - docs/sprints/sprint-0.md

key-decisions:
  - "A rejected webhook (missing or unreadable From) answers a JSON 422 by overriding failedValidation() in the FormRequest; the default for a non-JSON caller is a 302 redirect, which is wrong for Twilio"
  - "TextChannel is bound with bind(), not singleton(), so each resolution reads channels.text.driver; FakeTextChannel stays a singleton so sent() persists"
  - "FakeTextChannel::send() was a throwing placeholder in the tracer commit and became real in Task 3, so Task 3's send tests failed for the right reason at RED"
  - "RecordInbound takes the contact id for CustomerRecordChanged from the conversation relation it already holds, avoiding an extra query"

patterns-established:
  - "Inbound entry point: controller builds InboundData field by field, RecordInbound writes, request input never reaches a model directly"
  - "Fixtures use real area codes with the reserved 555-01XX line (libphonenumber rejects 555 area codes)"
  - "Gate unauthenticated test endpoints with per-request middleware that reads config, evaluated before the FormRequest"

requirements-completed: [LEAD-01, LEAD-03]

# Metrics
duration: 10min
completed: 2026-10-11
plan_head_before: b16a60a9fd345d7d7f06c8432aa8128bd2172174
plan_head_after: 8edd6b4fea4e6412419e21a51fb9a468aa12369b
commits: 6
status: complete

coverage:
  - id: D1
    description: "A Twilio-shaped text from a new number creates a contact, an open conversation, an inbound message and a text_received timeline entry, replies with empty TwiML, and queues ProcessInbound"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it records a text from a new number as a contact, conversation, message and timeline entry"
        status: pass
    human_judgment: false
  - id: D2
    description: "A second text from the same number joins the same open conversation, and texts written in different phone formats resolve to one E.164 contact"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it puts a second text from the same number in the same open conversation"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it resolves different ways of writing one number to a single contact"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-16: one open conversation per contact across channels, enforced by the database; a text after close starts a new thread and keeps the closed one"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it refuses a second open conversation for one contact at the database"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it starts a new open conversation when the last one was closed and keeps the closed one"
        status: pass
    human_judgment: false
  - id: D4
    description: "D-18: a retried webhook records nothing new and dispatches neither ProcessInbound nor CustomerRecordChanged a second time; CustomerRecordChanged fires after commit for a new text"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it records a retried webhook once and queues nothing the second time"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it announces that the customer record changed once a new text is recorded"
        status: pass
    human_judgment: false
  - id: D5
    description: "LEAD-01 edge cases: 1,600 character body kept in full, picture-only text with empty body, emoji and accents unchanged, same-second arrival order, missing or unreadable From rejected with 422 and nothing recorded"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php (stores a 1,600 character body in full; records a picture-only text; stores emoji and accented text unchanged; keeps arrival order; rejects a text with no From; rejects a From that is not a readable phone number)"
        status: pass
    human_judgment: false
  - id: D6
    description: "LEAD-03 (text half): numbers stored in E.164 through libphonenumber with the US default region from config"
    requirement: LEAD-03
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it resolves different ways of writing one number to a single contact"
        status: pass
    human_judgment: false
  - id: D7
    description: "TextChannel resolves from config (fake, twilio, named error for an unknown driver); the Twilio stub points to TXT-01; the fake sender returns fake- ids, remembers sends and logs bodies only in local"
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/TextChannelTest.php (8 tests)"
        status: pass
    human_judgment: false
  - id: D8
    description: "The unauthenticated fake webhook returns 404 unless the fake driver is on, and a real (non-test) POST is not rejected with 419"
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it answers 404 and records nothing when the text driver is not fake"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/FakeTextWebhookTest.php#it exempts webhooks from the request forgery check so a real post is not rejected with 419"
        status: pass
      - kind: other
        ref: "php artisan serve --no-reload plus curl: fake driver 200 text/xml, retried SID 200 with one row and one queued job, twilio driver 404 (run by the executor against a throwaway SQLite file)"
        status: pass
    human_judgment: false
---

# Phase 1 Plan 03: Inbound Text and Records Summary

**A Twilio-shaped text through the fake channel becomes one contact, one open conversation, one message and one timeline entry, with E.164 phones via libphonenumber, idempotent retries, and a channel layer chosen from config.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-10-11T13:13:23Z
- **Completed:** 2026-10-11T13:23:00Z (task work; summary and state updates follow)
- **Tasks:** 3 (1 tracer, 2 TDD)
- **Files modified:** 41 (34 created, 7 modified)

## Accomplishments

- `POST /webhooks/text/fake` takes a Twilio-shaped form body, records contact, conversation, message and `text_received` activity in one transaction, and answers an empty `<Response></Response>` as `text/xml`.
- D-16 lives in the schema: `channel` is on `messages`, and the partial unique index `conversations_one_open_per_contact` makes the database refuse a second open conversation. A text after a close starts a new conversation and the closed one is kept.
- D-18: a repeated `MessageSid` returns 200, records nothing and dispatches neither `ProcessInbound` nor `CustomerRecordChanged`. A concurrent-retry unique violation rolls back and returns the winner's message.
- Phones are stored in E.164 through `giggsey/libphonenumber-for-php-lite` (US default region from `channels.phone_region`, `isValidNumber` gate). `From` values `+12025550123`, `(202) 555-0123` and `202.555.0123` land on one contact.
- `TextChannel` resolves from `channels.text.driver` (fake, twilio, or a named `InvalidArgumentException`). The fake sender returns `fake-` ids, remembers sends and logs bodies only in `local`. The Twilio stub points to TXT-01.
- The fake webhook is 404 unless the fake driver is on (middleware runs before the FormRequest), and `webhooks/*` is exempt from the request forgery check. Verified against a real `php artisan serve`, because tests skip that check.

## Task Commits

1. **Task 1: tracer, one text becomes a contact, conversation, message and timeline entry**
   - RED `7580a49` (test), GREEN `55de073` (feat)
2. **Task 2: threading, idempotency and the record-changed event**
   - RED `a3eaa7b` (test), GREEN `16f7c7b` (feat)
3. **Task 3: text channel layer, fake-only webhook, CSRF exemption, fake sender**
   - RED `48a3c79` (test), GREEN `8edd6b4` (feat)

**Plan metadata:** the docs commits that follow this file (summary, then state/roadmap/requirements).

No REFACTOR commits: nothing needed cleanup after GREEN.

## TDD notes

- RED evidence was validated with `gsd_run check tdd-red-evidence` (JUnit format from `pest --log-junit`) and returned `RED_EVIDENCE_OK` for the target test of each task: route not defined (Task 1), retried SID gives 500 on the unique index (Task 2), non-fake driver still gives 200 (Task 3).
- Semantic assessment: each target test executed and failed on the planned behavior, not on a syntax, import or fixture fault.
- At the Task 2 and Task 3 RED commits some of the new tests already passed (threading, closed conversation, the database invariant, boundary, empty, encoding, ordering, and fake-driver resolution), because Task 1's implementation already provides that behavior. They are characterization tests that pin the behavior down; the tests that carry the RED are the ones that failed (4 in Task 2, 9 in Task 3). The plan expected the closed-conversation test to fail at Task 2 RED; it did not, because `RecordInbound` only ever looks up the open conversation.

## Files Created/Modified

See `key-files` above. The migrations are `2026_10_11_131450` to `131453` (contacts, conversations, messages, activities). `docs/sprints/sprint-0.md` gained 10 Walk-through log lines (routes, controllers, form requests, middleware, relationships and enum casts, factory states, queued jobs, transactions and savepoints, events, interface binding, Composer packages).

## Decisions Made

- JSON 422 for rejected webhooks (see Deviation 1).
- `bind()` instead of `singleton()` for `TextChannel` so config is read per resolution; `FakeTextChannel` itself stays a singleton.
- Contact creation race (two concurrent first texts from one new number with different SIDs) is not wrapped in a savepoint: the second request would hit the `phone_e164` unique index and return 500, and the provider's retry then succeeds. The plan scoped the savepoint to the conversation only. Worth revisiting when Twilio goes live in Phase 2.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Rejected webhook redirected instead of answering 422**
- **Found during:** Task 2 (RED run of the missing-From and unreadable-From tests)
- **Issue:** The plan says a missing or unreadable `From` returns 422. A FormRequest failure for a caller that does not send `Accept: application/json` (Twilio, curl) is a 302 redirect, not a 422.
- **Fix:** `FakeTextWebhookRequest::failedValidation()` throws an `HttpResponseException` carrying a JSON 422 with `message` and `errors`.
- **Files modified:** `app/Http/Requests/FakeTextWebhookRequest.php`
- **Verification:** both rejection tests assert `assertUnprocessable()` and `assertJsonValidationErrors(['From'])` on a plain `post()`; also checked with a real curl (422, `application/json`).
- **Committed in:** `16f7c7b`

**2. [Rule 3 - Blocking] Throwing placeholder for FakeTextChannel::send() in the tracer**
- **Found during:** Task 1
- **Issue:** `TextChannel` requires `send()`, but the plan builds the real sender in Task 3. Leaving it out would not compile; implementing it in Task 1 would make Task 3's send tests pass at RED.
- **Fix:** Task 1 shipped `send()` throwing a `LogicException`; Task 3 replaced it.
- **Files modified:** `app/Channels/FakeTextChannel.php`
- **Committed in:** `55de073`, replaced in `8edd6b4`

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both necessary for the plan's own truths to hold. No scope creep.

## Issues Encountered

- First manual `curl` run hit the dev database and returned 500: `php artisan serve` drops env overrides when a `.env` file exists, so my throwaway SQLite file was ignored. Only SELECTs failed (no new tables in the dev database), so nothing was written there. Re-ran with `--no-reload`, which passes the environment through; all checks passed and the dev database was confirmed unchanged.
- The `tdd-red-evidence` classifier needs a report path inside the project, so the temporary record was written to `.planning/` and deleted after each check.

## Authentication Gates

None.

## Known Stubs

Both are intentional extension points from the plan, not gaps in this plan's goal:

| File | Stub | Resolved by |
|------|------|-------------|
| `app/Jobs/ProcessInbound.php` (handle) | Empty body; the front-desk agent runs here | Sprint 1, AGNT-02 (Phase 2) |
| `app/Channels/TwilioTextChannel.php` | `send()` and `parseInbound()` throw `LogicException` | Phase 2, TXT-01 |

Both are recorded in `.planning/WINDOWS.md` (created by this step; shipping is not blocked because `workflow.windows_enforce` is not set).

## Threat Flags

None beyond the plan's threat model (T-01-10 to T-01-15 are all mitigated and tested).

## User Setup Required

None - no external service configuration required. `TEXT_CHANNEL` and `PHONE_REGION` are documented (commented) in `.env.example` and default to `fake` and `US`.

## Verification

- `php artisan test --compact tests/Feature/Inbound`: 23 passed (15 in FakeTextWebhookTest, 8 in TextChannelTest), 88 assertions.
- `php artisan test --compact tests/Feature/Icm tests/Feature/Quality tests/Unit/ArchitectureTest.php`: 83 passed, no regression in earlier plans.
- `php artisan test --compact` (whole suite): 108 passed, 396 assertions.
- `vendor/bin/pint --dirty --format agent`: passed.
- All Task 1, 2 and 3 acceptance criteria re-run and passing.
- Per CLAUDE.md, Ian should run the complete suite once: `php artisan test --compact`.

## Next Phase Readiness

- Plan 01-04 can add `InboundData::fromLeadForm()` and reuse `RecordInbound`; it must extend `findOrCreateContact` for email matching (D-12/D-13) and per-touch source/cost (D-14/D-15), and add `leads` to the forgery exemption in `bootstrap/app.php`.
- Plan 01-05 adds the listener for `CustomerRecordChanged`; the event already fires after commit with the contact id.
- Plan 01-07 can call `RecordInbound` in-process with `InboundData::fromText()`.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*

## Self-Check: PASSED

- All 34 created files exist on disk; the 7 modified files exist.
- Commits found on the branch: `7580a49`, `55de073`, `a3eaa7b`, `16f7c7b`, `48a3c79`, `8edd6b4`, `90c9bb3`.
- Measured commit count for the plan: 6 code commits (`b16a60a..8edd6b4`), plus the summary commit.
- Evaluation scope for plan 01-03 resolved from the plan's own commits.
