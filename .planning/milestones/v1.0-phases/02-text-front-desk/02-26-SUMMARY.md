---
phase: 02-text-front-desk
plan: 26
subsystem: api
tags: [twilio, webhook, hmac-sha1, signature, laravel-middleware, pest]

requires:
  - phase: 02-text-front-desk
    provides: "TextChannel and InboundText with optOutType, RecordInbound (keywords, dispatch once), RecipientOptedOut, the fake webhook pattern (plans 02-08, 02-24, 02-25)"
provides:
  - "POST /webhooks/text/twilio (route webhooks.text.twilio): a Twilio-signed inbound text lands on the same timeline as the fake one; anything unsigned is a 403"
  - "App\\Channels\\TwilioSignature (compute, matches, urlVariants): Twilio's signature recipe with no SDK, hash_equals, default-port variants"
  - "ValidateTwilioSignature middleware (parameter inbound|status) and EnsureTextDriverIsTwilio"
  - "TwilioTextChannel::parseInbound() reading MessageSid/SmsSid, From, To, Body, NumMedia, OptOutType"
  - "services.twilio config and TWILIO_* placeholders in .env.example"
affects: [02-27 Twilio send and status callbacks, 02-28, 02-29, go-live after A2P approval]

actuals:
  tokens: 8800
  tasks: 1
  commits: 2
plan_head_before: 63a38451785bfb5279ee264262574587eafd29ce
plan_head_after: 1e5fa26f2bcd62735b5c377cf5b39469f134827f

tech-stack:
  added: []
  patterns:
    - "Route middleware with a parameter (ValidateTwilioSignature::class.':inbound') picks which configured URL a signature is checked against"
    - "Webhook signature is checked against a configured URL, never one rebuilt from the request"
    - "Per-path opt-out from trimStrings and convertEmptyStringsToNull so a signed form is verified exactly as sent"

key-files:
  created:
    - app/Channels/TwilioSignature.php
    - app/Http/Middleware/ValidateTwilioSignature.php
    - app/Http/Middleware/EnsureTextDriverIsTwilio.php
    - app/Http/Controllers/TwilioTextWebhookController.php
    - app/Http/Requests/TwilioTextWebhookRequest.php
    - tests/Feature/Channels/TwilioWebhookTest.php
  modified:
    - app/Channels/TwilioTextChannel.php
    - config/services.php
    - .env.example
    - routes/web.php
    - bootstrap/app.php
    - tests/Feature/Inbound/TextChannelTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "A Twilio opt-out is trusted only from OptOutType; the real channel does not simulate keywords the way the fake does, because RecordInbound already matches the words in code when the provider reports none"
  - "MessageSid is required only when SmsSid is absent, so the older field name still records"
  - "A signature mismatch logs the configured URL's host and nothing else; missing token or URL is treated as a mismatch (403), never as allowed"

patterns-established:
  - "Test helpers named per file (twilioInboundPayload, twilioSignatureHeaderFor, twilioPostInbound); the signature helper recomputes Twilio's recipe independently of TwilioSignature so a bug there cannot hide itself"

requirements-completed: [TXT-01]

coverage:
  - id: D1
    description: "TwilioSignature reproduces Twilio's published test vector (RSOYDt4T1cUTdK1PDd93/VVr8B8=) and tries the URL with and without the default port"
    requirement: TXT-01
    verification:
      - kind: unit
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#computes the signature Twilio publishes for its own test vector"
        status: pass
      - kind: unit
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#lists the URL as written and with and without the default port"
        status: pass
    human_judgment: false
  - id: D2
    description: "A correctly signed Twilio inbound is recorded with provider twilio and queued once; a retried MessageSid is recorded and queued once"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#records a correctly signed Twilio text and queues it once"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#records a retried MessageSid once and queues it once"
        status: pass
    human_judgment: false
  - id: D3
    description: "Unsigned, wrongly signed, tampered, or unconfigured requests get 403 and record nothing; the signature is checked against the configured URL; a mismatch logs the host only"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#refuses a text with no signature and records nothing"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#refuses a text with a wrong signature and records nothing"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#checks the signature against the configured URL, not the one the request arrived on"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#logs a signature mismatch with the host only, never the token, the signature or the body"
        status: pass
    human_judgment: false
  - id: D4
    description: "Boundary, empty and encoding cases: 1,600 character body in full, picture-only text with num_media 1, emoji body, body with leading and trailing whitespace"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#stores a 1,600 character body from Twilio in full"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#records a picture-only text with an empty body and the media count"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#records an emoji body unchanged when the signature was made over the UTF-8 text"
        status: pass
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#accepts a body with leading and trailing whitespace, because the signature covers the text as Twilio sent it"
        status: pass
    human_judgment: false
  - id: D5
    description: "A Twilio STOP (OptOutType) opts the contact out with no reply (D-39)"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#opts the contact out on a Twilio STOP and sends no reply (D-39)"
        status: pass
    human_judgment: false
  - id: D6
    description: "TEXT_CHANNEL alone switches which webhook answers: the Twilio route is 404 unless the driver is twilio and the fake route is 404 when it is"
    requirement: TXT-01
    verification:
      - kind: integration
        ref: "tests/Feature/Channels/TwilioWebhookTest.php#answers 404 on the Twilio route unless the Twilio driver is on, and on the fake route when it is"
        status: pass
    human_judgment: false
  - id: D7
    description: "Real delivery from Twilio to the live URL on Ian's registered number"
    verification: []
    human_judgment: true
    rationale: "Needs the Twilio account, an approved A2P registration and the public URL; per DEC-05 this does not gate the build and is exercised at go-live"

duration: 3min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 26: Twilio Signed Inbound Webhook Summary

**Twilio inbound webhook with a hand-written HMAC-SHA1 signature check (hash_equals, configured URL, default-port variants), a Twilio parseInbound, and TEXT_CHANNEL-only switching, with no SDK added**

## Performance

- **Duration:** 3 min
- **Started:** 2026-10-12T03:56:43Z
- **Completed:** 2026-10-12T03:59:17Z
- **Tasks:** 1 (tracer)
- **Files modified:** 13 (6 created, 7 modified)

## Accomplishments

- `POST /webhooks/text/twilio` records a correctly signed Twilio text through the same `RecordInbound` path as the fake channel; the answer is the empty TwiML `<Response></Response>` as `text/xml`.
- `TwilioSignature` reproduces Twilio's published vector (`RSOYDt4T1cUTdK1PDd93/VVr8B8=`). `ValidateTwilioSignature` answers 403 for a missing, wrong, tampered or unconfigured signature, checks the configured URL rather than the request's, also accepts the URL with or without `:443`/`:80`, and logs only the configured URL's host.
- `TwilioTextChannel::parseInbound()` reads `MessageSid` (falling back to `SmsSid`), `From`, `To`, `Body`, `NumMedia` and `OptOutType`; a Twilio STOP opts the contact out with no reply. `send()` is still the Phase 1 stub for plan 02-27.
- The Twilio and fake routes are mutually exclusive on `channels.text.driver`; switching `TEXT_CHANNEL` needs no code change.
- Full suite: 886 tests pass.

## Task Commits

1. **Task 1 (tracer): signed Twilio inbound reaches the timeline, unsigned is refused**
   - RED: `fe4cd35` (test) - failing Twilio webhook tests
   - GREEN: `1e5fa26` (feat) - signed Twilio inbound webhook

**Plan metadata:** see the docs(02-26) commits that follow this summary.

RED evidence (semantic assessment): the new tests failed because the symbols and route they exercise did not exist (`TwilioSignature` class not found, `webhooks.text.twilio` route not defined, `ValidateTwilioSignature` not found) and the rewritten `TextChannelTest` cases hit the Phase 1 `LogicException` in `parseInbound()`. That is the planned reason: the behavior was absent, not a syntax, fixture or unrelated fault. 21 of 28 tests failed, 7 (unchanged Phase 1 cases) passed. The plan's task carries no `tdd="true"` attribute, so the machine RED-evidence classifier was not run.

## Files Created/Modified

- `app/Channels/TwilioSignature.php` - Twilio's signature recipe: `compute`, `matches` (hash_equals over every URL variant), `urlVariants`.
- `app/Http/Middleware/ValidateTwilioSignature.php` - 403 for unsigned or wrongly signed requests; parameter `inbound` or `status` picks the configured URL.
- `app/Http/Middleware/EnsureTextDriverIsTwilio.php` - 404 unless the Twilio driver is on.
- `app/Http/Controllers/TwilioTextWebhookController.php` - records the inbound and answers empty TwiML.
- `app/Http/Requests/TwilioTextWebhookRequest.php` - validates only what Twilio always sends, JSON 422 on failure.
- `app/Channels/TwilioTextChannel.php` - `parseInbound()` filled; `send()` left as the stub.
- `config/services.php`, `.env.example` - `services.twilio.*` and five commented `TWILIO_*` placeholders.
- `routes/web.php` - route `webhooks.text.twilio` with `EnsureTextDriverIsTwilio` and `ValidateTwilioSignature:inbound`.
- `bootstrap/app.php` - Twilio webhook paths skip `trimStrings` and `convertEmptyStringsToNull` (see Deviations).
- `tests/Feature/Channels/TwilioWebhookTest.php` - 20 tests; `tests/Feature/Inbound/TextChannelTest.php` - the Phase 1 "refuses to read a Twilio webhook" test rewritten in place as two `parseInbound` tests; the send stub test and `TWILIO_NOT_BUILT_MESSAGE` stay for 02-27.
- `docs/sprints/sprint-1.md` - walk-through log line for signatures and middleware parameters.

## Decisions Made

- The real channel reads `OptOutType` only; it does not simulate keywords the way the fake does, because `RecordInbound` already falls back to matching the words in code when a provider reports none.
- `MessageSid` is required only when `SmsSid` is absent so the older Twilio field name still records.
- A missing or unset auth token or URL is a 403, never a pass.
- The signature test helper recomputes Twilio's recipe on its own instead of calling `TwilioSignature`, so a bug in the class cannot hide itself.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Laravel's trim and empty-to-null request middleware broke signatures for texts with surrounding whitespace**
- **Found during:** Task 1 (GREEN, edge probe after the planned tests passed)
- **Issue:** `TrimStrings` and `ConvertEmptyStringsToNull` are global middleware that rewrite the request's form values before route middleware runs. Twilio signs the form exactly as sent, so any text starting or ending with whitespace (for example `"Yes, please come today "`) got a 403 and was lost. The stored body would also have differed from the customer's own words.
- **Fix:** In `bootstrap/app.php`, `trimStrings(except: [...])` and `convertEmptyStringsToNull(except: [...])` skip paths matching `webhooks/text/twilio*`. Added a test with leading and trailing whitespace, which failed with 403 before the fix and passes after.
- **Files modified:** `bootstrap/app.php`, `tests/Feature/Channels/TwilioWebhookTest.php`
- **Verification:** `tests/Feature/Channels/TwilioWebhookTest.php` and the whole Inbound folder pass (162 tests); full suite 886 pass.
- **Committed in:** `1e5fa26`

---

**Total deviations:** 1 auto-fixed (1 bug). `bootstrap/app.php` was not in the plan's `files_modified`.
**Impact on plan:** The fix is required for the plan's own truth ("the signature is computed over the received values"); plan 02-27 reuses the `webhooks/text/twilio*` pattern, so the status callback is covered too. No scope creep.

## Issues Encountered

None. All 20 planned behaviors passed on the first implementation run.

## Known Stubs

- `app/Channels/TwilioTextChannel.php` `send()` still throws the Phase 1 "built in Phase 2" `LogicException`. Intentional per the plan's flagged assumption; plan 02-27 builds it, and the Phase 1 send stub test and `TWILIO_NOT_BUILT_MESSAGE` stay with it. It does not block this plan's goal (the receiving half).

## Threat Flags

None. The one new public surface (`/webhooks/text/twilio`) is T-02-50 in the plan's threat model; T-02-51 (replay) and T-02-53 (URL mismatch) are covered by the retried-MessageSid and default-port tests. No packages were installed (T-02-SC accepted).

## Authentication Gates

None.

## User Setup Required

Go-live setup for Twilio is not done and does not gate this build (DEC-05: tests use Twilio-shaped payloads, no real credentials exist in the repo; `.env.example` holds empty commented placeholders only).

- **Env vars (in `.env`):** `TWILIO_ACCOUNT_SID` and `TWILIO_AUTH_TOKEN` (Twilio Console, Account Info; the auth token also signs webhooks), `TWILIO_MESSAGING_SERVICE_SID` (Console, Messaging, Services), `TWILIO_WEBHOOK_URL` (the exact public URL typed into the service's incoming webhook, ending `/webhooks/text/twilio`), `TWILIO_STATUS_CALLBACK_URL` (exact public URL ending `/webhooks/text/twilio/status`, valid hostname, no underscores).
- **Twilio Console:** create a Messaging Service, add the number to its sender pool, set the incoming webhook to `TWILIO_WEBHOOK_URL`; turn on Advanced Opt-Out (Messaging, Services, Opt-Out Management) so `OptOutType` reaches the webhook (D-39), and consider removing YES from the opt-in keywords so a customer's yes is never read as an opt-in.
- **Switch on:** set `TEXT_CHANNEL=twilio` in `.env` once A2P is approved. Sending and the status callback arrive with plan 02-27.

## Next Phase Readiness

- Plan 02-27 can add `send()`, the status callback route (`ValidateTwilioSignature::class.':status'` is ready and covered by a probe-route test) and map HTTP 400 / code 21610 to `RecipientOptedOut`.
- No blockers.

## Self-Check: PASSED

- Created files exist: `app/Channels/TwilioSignature.php`, `app/Http/Middleware/ValidateTwilioSignature.php`, `app/Http/Middleware/EnsureTextDriverIsTwilio.php`, `app/Http/Controllers/TwilioTextWebhookController.php`, `app/Http/Requests/TwilioTextWebhookRequest.php`, `tests/Feature/Channels/TwilioWebhookTest.php` (all verified on disk).
- Commits `fe4cd35` (RED) and `1e5fa26` (GREEN) are on `gsd/phase-02-text-front-desk`; `git rev-list --count` from the plan base `63a3845` gives 2.
- Acceptance criteria: `hash_equals` and `sha1` in `TwilioSignature.php`; `webhooks.text.twilio` and `ValidateTwilioSignature::class.':inbound'` in `routes/web.php`; `'twilio' =>` and `TWILIO_AUTH_TOKEN` in `config/services.php`; the published vector in `TwilioWebhookTest`; `parseInbound` and `OptOutType` in `TextChannelTest`. All pass.
- Verification: the narrow command (TwilioWebhookTest, TextChannelTest, ArchitectureTest) passes 43 of 43; `php artisan test --compact` passes 886 of 886.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
