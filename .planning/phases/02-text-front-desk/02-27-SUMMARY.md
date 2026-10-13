---
phase: 02-text-front-desk
plan: 27
subsystem: api
tags: [twilio, messages-api, status-callback, opt-out, laravel-http-client, pest]

requires:
  - phase: 02-text-front-desk
    provides: "TwilioSignature, ValidateTwilioSignature (inbound|status), EnsureTextDriverIsTwilio, TwilioTextChannel::parseInbound, services.twilio config (plan 02-26); RecipientOptedOut and OutboundSender's reaction to it (plans 02-25, 02-14)"
provides:
  - "TwilioTextChannel::send(): POST to the Messages API through the Messaging Service with basic auth, returns the message sid"
  - "HTTP 400 with Twilio code 21610 raises RecipientOptedOut; every other failure raises a RuntimeException carrying only the HTTP status and Twilio code"
  - "POST /webhooks/text/twilio/status (route webhooks.text.twilio.status): signed, forward-only delivery status, 21610 marks the contact opted out"
  - "Go-live steps for Twilio in docs/sprints/sprint-1.md and docs/SETUP.md"
affects: [02-28, 02-29, go-live after A2P approval, phase 2 verification]

actuals:
  tokens: 7900
  tasks: 2
  commits: 4
plan_head_before: 602d6042d9b3a04cc0a3aea3981f381098b31ec2
plan_head_after: a9dff4b3c7259dedb4cd28d4ed42158ed0b806bc

tech-stack:
  added: []
  patterns:
    - "Laravel HTTP client chain (withBasicAuth, asForm, acceptJson, timeout) with the failure read defensively from the JSON body"
    - "Forward-only status ranking in a match, applied inside a transaction with lockForUpdate"
    - "Status callback looks up only outbound rows by exact provider and provider_message_id"

key-files:
  created:
    - app/Http/Controllers/TwilioStatusCallbackController.php
    - tests/Feature/Channels/TwilioSendTest.php
    - tests/Feature/Channels/TwilioStatusCallbackTest.php
  modified:
    - app/Channels/TwilioTextChannel.php
    - routes/web.php
    - tests/Feature/Inbound/TextChannelTest.php
    - docs/sprints/sprint-1.md
    - docs/SETUP.md

key-decisions:
  - "Twilio send errors carry the HTTP status and Twilio code only (or 'no answer from Twilio' / 'no message sid'): never the token, the account sid, or Twilio's own wording"
  - "A 2xx answer with no sid is a failed send, so OutboundSender can never record a message as sent without a provider id"
  - "A failed/undelivered callback with ErrorCode 21610 marks the contact opted out even when the message was already failed, because the carrier's statement about the customer is true whatever order callbacks arrive in"
  - "The callback only touches direction=out rows with a status that travels through Twilio (queued, sent, delivered, failed); a customer's inbound text sharing a sid, or a draft, is never changed"

patterns-established:
  - "Status words map to MessageStatus in a match with a null default, so a status Twilio adds later is ignored rather than an error"
  - "Twilio error 21610 is handled in both places it can appear: send refusal (RecipientOptedOut) and delivery callback (opt-out note)"

requirements-completed: [TXT-01]

coverage:
  - id: D1
    description: "TwilioTextChannel::send posts To, MessagingServiceSid, Body and StatusCallback over basic auth to the Messages API and the returned sid is stored as provider_message_id"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#posts the text to the Messages API through the Messaging Service and stores the returned sid"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#leaves StatusCallback out when no status callback URL is configured"
        status: pass
    human_judgment: false
  - id: D2
    description: "A send refused with HTTP 400 and code 21610 throws RecipientOptedOut, the owner reply is refused and the contact is marked opted out; other 400s are ordinary failures"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#refuses the owner reply and marks the customer opted out when Twilio answers 21610"
        status: pass
      - kind: unit
        ref: "tests/Feature/Channels/TwilioSendTest.php#treats a 400 with any other code as an ordinary failure, not an opt-out"
        status: pass
    human_judgment: false
  - id: D3
    description: "A failed Twilio send is stored with an error naming the status and code and never the auth token; missing configuration fails with 'Twilio is not configured' and sends nothing"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#stores a 500 as failed with an error that names the status and never contains the auth token"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#stores a connection failure as failed with an error that never contains the auth token"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioSendTest.php#fails with Twilio is not configured and sends nothing when a setting is missing"
        status: pass
    human_judgment: false
  - id: D4
    description: "The signed status callback moves a message forward only (queued < sent < delivered or failed), records ErrorCode in meta, ignores unknown sids and statuses, matches ids as exact strings, and answers 403 unsigned"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioStatusCallbackTest.php (22 cases)"
        status: pass
    human_judgment: false
  - id: D5
    description: "An undelivered callback with ErrorCode 21610 marks the contact opted out with a note, keeping an existing opt-out date"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioStatusCallbackTest.php#marks the customer opted out when an undelivered callback carries error 21610"
        status: pass
    human_judgment: false
  - id: D6
    description: "Live texting on Ian's Twilio number after A2P approval, including the Console webhook and Advanced Opt-Out settings"
    verification: []
    human_judgment: true
    rationale: "Needs a real Twilio account, an approved A2P registration and a phone; it cannot be exercised by a test and does not gate this phase (DEC-05)"

duration: 13min
completed: 2026-10-11
status: complete
---

# Phase 2 Plan 27: Twilio Send and Delivery Status Summary

**Real Twilio driver: Messages API send through the Messaging Service with basic auth, 21610 opt-out refusal mapped to RecipientOptedOut, and a signed forward-only status callback that records delivery and carrier opt-outs**

## Performance

- **Duration:** 13 min
- **Started:** 2026-10-12T03:52:00Z
- **Completed:** 2026-10-12T04:05:14Z
- **Tasks:** 2
- **Files modified:** 8 (3 created, 5 modified)

## Accomplishments

- `TwilioTextChannel::send()` posts `To`, `MessagingServiceSid`, `Body` and (when configured) `StatusCallback` as a form to `https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json` over basic auth and returns the `sid`, which `OutboundSender` stores as `provider_message_id`. Sending through the Messaging Service is what makes Advanced Opt-Out apply (D-39).
- A 400 whose JSON `code` is 21610 throws `RecipientOptedOut`, so the existing `OutboundSender` path marks the contact opted out, stores the message failed and refuses the owner reply with "The customer has opted out of texts." Any other failure (non-2xx, no connection, 2xx with no sid, blank config) is a `RuntimeException` carrying the status and code only.
- `TwilioStatusCallbackController` at `POST /webhooks/text/twilio/status` (signature checked against `status_callback_url`) maps sent, delivered, failed and undelivered onto `MessageStatus`, only ever moves a message forward, stores `ErrorCode` in `meta.error_code`, and marks the contact opted out on 21610. Unknown sids, unknown statuses and a missing sid all answer 200 with empty TwiML.
- The Phase 1 stub test in `TextChannelTest` now asserts the real behaviour (unconfigured send throws "Twilio is not configured", nothing is sent); `TWILIO_NOT_BUILT_MESSAGE` and the channel's `NOT_BUILT` constant are gone.
- "Going live on Twilio" steps are in `docs/sprints/sprint-1.md`, a short section in `docs/SETUP.md`, and two walk-through lines.

## Task Commits

1. **Task 1 (tracer): Sending through Twilio, the 21610 refusal, forward-only delivery status**
   - RED `cd6fcf8` (test)
   - GREEN `779fb39` (feat)
2. **Task 2 (tdd): Stub tests describe the real driver; go-live steps**
   - `4b31e15` (test) rewrite of the stub test in TextChannelTest
   - `a9dff4b` (docs) sprint-1 notes, SETUP.md, walk-through log

**Plan metadata:** recorded in the docs(02-27) commit after this summary.

## TDD Gate Compliance

- **RED (Task 1):** `cd6fcf8`. Run before any code: 33 tests, 9 failures, 24 errors, 0 passed. The send tests failed because `send()` still threw the Phase 1 `LogicException` ("built in Phase 2") where the tests expect a `Http` post, `RecipientOptedOut` or "Twilio is not configured"; the status tests errored with `Route [webhooks.text.twilio.status] not defined`, the planned route not yet existing. `gsd check tdd-red-evidence` supports TAP, JUnit, swift-testing and unittest reports, not Pest's JSON line, so machine classification was not run. Semantic assessment: every red test failed on the planned missing behaviour (no syntax, fixture or import fault); the status tests' failure is a missing route, which is the feature itself.
- **GREEN (Task 1):** `779fb39`. 53/53 in `tests/Feature/Channels`.
- **Task 2 RED:** the rewritten stub test passed at once because Task 1 had already built the behaviour, as the plan anticipated ("it passes or fails according to Task 1"). It was committed as `test(02-27)`. Between `779fb39` and `4b31e15` the old stub test in `TextChannelTest` was red by design; the full suite was green at `a9dff4b` (919 tests, 3848 assertions).
- No REFACTOR commit was needed.

## Files Created/Modified

- `app/Channels/TwilioTextChannel.php` - real `send()`; stub constant and `LogicException` removed
- `app/Http/Controllers/TwilioStatusCallbackController.php` - forward-only status, error code, 21610 opt-out
- `routes/web.php` - `webhooks.text.twilio.status` with `EnsureTextDriverIsTwilio` and `ValidateTwilioSignature::class.':status'`
- `tests/Feature/Channels/TwilioSendTest.php` - 11 cases (request shape, 21610, 500, connection failure, no sid, missing config x3)
- `tests/Feature/Channels/TwilioStatusCallbackTest.php` - 22 cases, signed with a locally written recipe (not the app's own class)
- `tests/Feature/Inbound/TextChannelTest.php` - stub test rewritten in place
- `docs/sprints/sprint-1.md`, `docs/SETUP.md` - go-live notes and walk-through log

## Decisions Made

See `key-decisions` above. In short: errors never carry secrets or Twilio's wording; a sid-less 2xx is a failure; 21610 on a callback marks the contact opted out regardless of rank movement; callbacks touch only outbound rows in a Twilio-travelling status.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical] Callback only touches outbound rows in a queued/sent/delivered/failed state**
- **Found during:** Task 1 (designing the controller)
- **Issue:** `inbound` texts also store `provider='twilio'` and a `provider_message_id`, so a callback matching an inbound sid, or a draft/withdrawn row, could have been overwritten; the plan only said "find by provider and provider_message_id".
- **Fix:** query adds `direction = out` and the rank `match` returns null (untouchable) for draft, withdrawn and received. Test: "never touches a text the customer sent".
- **Files modified:** app/Http/Controllers/TwilioStatusCallbackController.php, tests/Feature/Channels/TwilioStatusCallbackTest.php
- **Committed in:** cd6fcf8, 779fb39

**2. [Rule 2 - Missing critical] A 2xx answer with no sid is a failed send**
- **Found during:** Task 1
- **Issue:** returning `json('sid')` unchecked could hand `OutboundSender` a null/empty id, recording a "sent" message with no way to track it.
- **Fix:** non-string or empty sid raises `Twilio send failed (HTTP <status>, no message sid)`.
- **Committed in:** cd6fcf8, 779fb39

### Plan-text notes (no code change)

- The plan's Task 2 asked for two walk-through lines (middleware with parameters, and signature checking with `hash_equals`). Plan 02-26 already logged both in full, so this plan added two different lines (the HTTP client chain, and forward-only status with the `:status` parameter) instead of repeating them. The acceptance check for `hash_equals` in sprint-1.md passes on the 02-26 entry.

---

**Total deviations:** 2 auto-fixed (both Rule 2)
**Impact on plan:** Both are correctness guards inside the files the plan named. No scope creep, no new packages.

## Issues Encountered

None. All 53 channel tests passed on the first GREEN run; full suite 919/919.

## Known Limitations (not stubs)

- A callback can in principle arrive between Twilio's 201 response and `OutboundSender` saving `provider_message_id`: it would find no row and answer 200 (as the plan specifies), and a `delivered` that landed in that window could then be overwritten by OutboundSender's own `Sent` write. In practice delivery takes seconds and the window is a few milliseconds, so it was left alone; a guard in `OutboundSender::transmit` would be the place if it ever shows up.
- Live behaviour against Twilio (real signature on Ian's URLs, real 21610, real callbacks) is untested by design; it is a UAT item after A2P approval (DEC-05, VALIDATION manual list).

## User Setup Required

No USER-SETUP.md (the plan has no `user_setup`). Go-live setup is for after A2P approval and does not gate this build (DEC-05). Steps are in `docs/sprints/sprint-1.md` under "Going live on Twilio" and summarised in `docs/SETUP.md` section 11:

- Create a Twilio Messaging Service, add the number, set the incoming webhook to `https://<host>/webhooks/text/twilio` and the status callback to `https://<host>/webhooks/text/twilio/status`, exactly as in `.env`.
- Turn on Advanced Opt-Out; consider removing YES from the opt-in keywords.
- `.env`: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_MESSAGING_SERVICE_SID`, `TWILIO_WEBHOOK_URL`, `TWILIO_STATUS_CALLBACK_URL`, then `TEXT_CHANNEL=twilio`.
- Test by texting from a phone that is not the owner's alert phone.

## Threat Flags

None. The one new public route (`/webhooks/text/twilio/status`) is T-02-63 in the plan's threat model and is behind the signature check; T-02-52 (no token in errors or logs) is covered by the send tests.

## Next Phase Readiness

- Plans 02-28 and 02-29 can rely on a complete Twilio driver (send, inbound, status) selected by `TEXT_CHANNEL` alone.
- Nothing blocks the build; live Twilio waits on A2P approval.

## Self-Check: PASSED

- Created files exist: TwilioStatusCallbackController.php, TwilioSendTest.php, TwilioStatusCallbackTest.php.
- Commits `cd6fcf8`, `779fb39`, `4b31e15`, `a9dff4b` are on `gsd/phase-02-text-front-desk` (`git rev-list --count 602d604..HEAD` = 4).
- Acceptance greps pass; `php artisan test --compact` 919 passed.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-11*
