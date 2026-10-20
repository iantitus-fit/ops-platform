---
phase: 01-foundation
plan: 04
subsystem: inbound-leads
tags: [laravel, form-request, rate-limiting, csrf, libphonenumber, contact-matching, attribution, pest]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: "Plan 01-01: ActiveConfig::leadSource() and NoActiveVersion; test harness with activateExampleFolder()"
  - phase: 01-foundation
    provides: "Plan 01-03: RecordInbound, InboundData, PhoneNumber::toE164, contacts/conversations/messages/activities tables, the forgery exemption for webhooks/*"
provides:
  - "POST /leads (leads.store): public web form endpoint, throttled by the named limiter leads (10 per minute per IP), CSRF-exempt, answers {\"received\": true}"
  - "InboundData::fromLeadForm(): source lowercased and trimmed or direct, meta.form = {name, phone, email, source, message} for the customer card in 01-05"
  - "RecordInbound contact matching: phone_e164 first, then lowercased trimmed email, fill-only-when-empty, never overwriting a stored phone or email (D-12, D-13)"
  - "First-touch attribution on the contact and per-touch source and cost on every activity (D-14); unknown sources and a missing live version noted, never dropped (D-15)"
  - "RecordInbound::RESERVED_SOURCES (text, direct) and the exact note summaries listed in the plan"
affects: [01-05 customer cards (renders meta.form and timeline notes), 01-06 dashboard (cost per lead from activities), 01-07 dev chat, phase-02 agent]

# Actuals (#2632)
actuals:
  tokens: 8776
  tasks: 3
  commits: 6

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Matching written out by hand (phone, then email, fill only empty) instead of an update-or-create helper"
    - "Conflicting form details become timeline notes whose subject is the inbound message and whose occurred_at equals the touch"
    - "Source lookup returns the source plus the notes it calls for; NoActiveVersion is caught so a missing live version never loses a lead"
    - "Public endpoint recipe: FormRequest with after() hook and JSON failedValidation(), named throttle, forgery exemption by path"

key-files:
  created:
    - app/Http/Controllers/LeadController.php
    - app/Http/Requests/StoreLeadRequest.php
    - tests/Feature/Inbound/LeadFormTest.php
    - tests/Feature/Inbound/ContactMatchingTest.php
  modified:
    - app/Actions/InboundData.php
    - app/Actions/RecordInbound.php
    - app/Providers/AppServiceProvider.php
    - routes/web.php
    - bootstrap/app.php
    - docs/sprints/sprint-0.md

key-decisions:
  - "A rejected lead answers a JSON 422 for any caller by overriding failedValidation() in StoreLeadRequest, the same way FakeTextWebhookRequest does; a website form does not send Accept: application/json and the default is a 302 redirect"
  - "Note activities are written after the message so each one carries the message as its subject and the touch's occurred_at; matching returns the notes instead of writing them"
  - "Source notes (unknown source, no live version) are added once per touch, ahead of any contact-matching notes"
  - "A known cost of 0 (the referral source) is stored as 0; null is reserved for unknown"

patterns-established:
  - "Inbound entry points build InboundData from validated() field by field; request input never reaches a model"
  - "Reserved source keys live as a constant on RecordInbound and are exempt from unknown-source notes"

requirements-completed: [LEAD-02, LEAD-03]

# Metrics
duration: 5min
completed: 2026-10-11
plan_head_before: 6569e109e40f101dab940e09aa2926fc52619066
plan_head_after: 7756dfb1419f19bf75badb0cd68468a164d56f48
commits: 6
status: complete

coverage:
  - id: D1
    description: "An Angi web form from someone who already texted lands on the same contact and open conversation as a web message, with a form_received activity carrying source angi and cost 4500 read through ActiveConfig"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/LeadFormTest.php#it lands an Angi form from someone who already texted on the same contact with Angi's cost"
        status: pass
    human_judgment: false
  - id: D2
    description: "A form from a brand-new person creates a contact with lead_source_key angi and acquisition_cost_cents 4500"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/LeadFormTest.php#it creates a contact with the source and cost for a form from a new person"
        status: pass
    human_judgment: false
  - id: D3
    description: "D-12 and D-13: contacts match by phone then lowercased trimmed email; a form with neither is a 422; a form never overwrites a stored phone or email, and conflicts and unreadable phones become timeline notes"
    requirement: LEAD-03
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/ContactMatchingTest.php (matches a form with only an email; rejects a form with no phone and no email; fills in the phone; keeps the stored phone and notes the different one; lands on the contact the phone matches; fills in an empty email; keeps a different stored email; records the lead on the email contact and notes a phone it could not read; rejects an unreadable phone)"
        status: pass
    human_judgment: false
  - id: D4
    description: "LEAD-03 boundary, empty, encoding and precision: US formats and an extension normalize to +13125550147, +44 keeps its country code, a text and a form from one phone share one contact and one conversation, phone_e164 is a string"
    requirement: LEAD-03
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/ContactMatchingTest.php (stores every common way of writing one US number; keeps the country code of an international number; puts a text and then a form / a form and then a text on one contact; matches by email when the phone is only spaces)"
        status: pass
    human_judgment: false
  - id: D5
    description: "D-14: the contact keeps its first-touch source and cost; every later touch records its own source and cost on its activity"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/LeadFormTest.php#it keeps the first touch on the contact and records each later touch's own source and cost"
        status: pass
    human_judgment: false
  - id: D6
    description: "D-15 and never lose a lead: unknown sources are stored raw with null cost and a note; no source is direct; texts are text; a missing live version records the lead with null cost and a note for non-reserved sources only"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/LeadFormTest.php (accepts a source that is not in lead-sources.md; records a form with no source as direct; records a text with the source text; still records a form when no business folder version is live; adds no cost note for the reserved sources)"
        status: pass
    human_judgment: false
  - id: D7
    description: "POST /leads is throttled (the 11th post in a minute is a 429), enforces field caps, and is exempt from the request forgery check; a real post is not rejected with 419"
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/LeadFormTest.php (answers the 11th lead post with 429; refuses fields longer than their caps; exempts the lead form and webhooks from the request forgery check)"
        status: pass
      - kind: other
        ref: "php artisan serve --no-reload plus curl against a throwaway SQLite file: POST /leads source=angi gave 200 {\"received\":true}, no 419; contact +12025550188 with angi and 4500, activity cost 4500; a post with no phone or email gave 422 JSON (run by the executor)"
        status: pass
    human_judgment: false
---

# Phase 1 Plan 04: Web Form Leads Summary

**POST /leads takes a website form, matches the person by phone then email without overwriting anything, and records the source and its cost per lead from lead-sources.md on the same timeline and open conversation as their texts, behind a 10-per-minute limiter and a path-scoped CSRF exemption.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-10-11T13:26:32Z
- **Completed:** 2026-10-11T13:31:39Z (task work; summary and state updates follow)
- **Tasks:** 3 (1 tracer, 2 TDD)
- **Files modified:** 10 (4 created, 6 modified)

## Accomplishments

- `POST /leads` (`leads.store`) validates name, phone, email, message and source with length caps, builds `InboundData::fromLeadForm()` from `validated()` only, and answers `{"received": true}`. The tracer proved the full path first: an Angi form from someone who already texted lands in the same conversation with a `form_received` activity at 4500 cents.
- Matching (D-12, D-13) is an explicit method in `RecordInbound`: phone first, then lowercased trimmed email, then create. An empty phone or email on the matched contact is filled; a different one is kept and a note activity (`form gave a different phone: +1...`, `form gave a different email: ...`) is added. An unreadable phone is treated as absent and noted (`form gave a phone we could not read: ...`).
- A form with no usable phone and no email is a 422 with the exact sentences from the plan, for JSON and plain form posts alike.
- Attribution (D-14, D-15): the contact keeps its first source and cost; every touch's activity carries its own. Unknown sources are stored raw with null cost and `unknown source: <key>`; no source is `direct`; with no live version the cost is null and a non-reserved source gets `Cost per lead unknown for <key>: no business folder version is live yet`. The lead is never dropped.
- Abuse controls: named limiter `leads` (10 per minute per IP, 429 on the 11th), field caps, and `leads` added next to `webhooks/*` in the forgery exemption. Checked against a real `php artisan serve`, because tests skip the forgery check.

## Task Commits

1. **Task 1: tracer, an Angi form from someone who already texted lands on the same contact with Angi's cost**
   - RED `535bdfd` (test), GREEN `c8a3681` (feat). Tracer gate: `<verify>` is automated-only and the mode is end-of-phase, so the verify command was re-run and passed before expansion.
2. **Task 2: match the right contact every time**
   - RED `9bbd43b` (test), GREEN `e1b53fe` (feat)
3. **Task 3: first-touch attribution, unknown sources, no live version, throttling and the CSRF exemption**
   - RED `44615ca` (test), GREEN `7756dfb` (feat)

**Plan metadata:** the docs commits that follow this file (summary, then state/roadmap/requirements).

No REFACTOR commits: nothing needed cleanup after GREEN.

## TDD notes

- RED evidence was validated with `gsd_run check tdd-red-evidence` (JUnit from `pest --log-junit`) and returned `RED_EVIDENCE_OK` for each task's target test: route not defined (Task 1), no `form gave a different phone` note (Task 2), no `unknown source: yelp` note (Task 3).
- Semantic assessment: each target executed and failed on the planned assertion (route missing, missing note, missing exemption entry), not on a syntax, import or fixture fault.
- At the Task 2 RED commit 15 of 24 tests failed and 9 passed (text/form ordering, the US phone formats, the +44 number, phone-wins). At the Task 3 RED commit 3 of 17 failed (unknown source, no live version, forgery exemption) and the rest passed (throttle, caps, first-touch, direct, text). The passing ones are characterization tests that pin behavior the earlier tasks already provided; the failing ones carry the RED.

## Files Created/Modified

- `app/Http/Controllers/LeadController.php` - `store()` records the lead through `RecordInbound` and answers JSON
- `app/Http/Requests/StoreLeadRequest.php` - field caps, the phone-or-email `after()` hook, JSON 422 `failedValidation()`
- `app/Actions/InboundData.php` - `fromLeadForm()`
- `app/Actions/RecordInbound.php` - ActiveConfig injection, `matchOrCreateContact()`, `lookUpLeadSource()`, `RESERVED_SOURCES`, note activities
- `app/Providers/AppServiceProvider.php` - the `leads` rate limiter
- `routes/web.php`, `bootstrap/app.php` - the route and the forgery exemption
- `tests/Feature/Inbound/LeadFormTest.php`, `tests/Feature/Inbound/ContactMatchingTest.php` - 17 and 24 tests
- `docs/sprints/sprint-0.md` - 4 Walk-through log lines (named rate limiters, `after()` hooks, JSON responses, CSRF exemptions)

## Decisions Made

- JSON 422 for rejected leads (see Deviation 1).
- Note activities are written after the message so each carries the message as subject and the touch's `occurred_at`; the matching method returns the notes rather than writing them.
- A known cost of 0 (referral) is stored as 0; null means unknown.
- Filling an empty email does not check whether another contact already owns that email. The plan's flagged assumptions accept this ("noted rather than enforced").

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] A rejected lead from a plain form post would redirect instead of answering 422**
- **Found during:** Task 2 (writing the RED tests; same cause as 01-03 Deviation 1)
- **Issue:** The plan says a lead with no usable phone or email is a 422. A FormRequest failure for a caller that does not send `Accept: application/json` (a website form, curl) is a 302 redirect.
- **Fix:** `StoreLeadRequest::failedValidation()` throws an `HttpResponseException` with a JSON 422 carrying `message` and `errors`, and a test posts without the JSON header.
- **Files modified:** `app/Http/Requests/StoreLeadRequest.php`, `tests/Feature/Inbound/ContactMatchingTest.php`
- **Verification:** `it answers a plain form post with a 422 too, not a redirect` fails at RED and passes at GREEN; also checked with a real curl (422, `application/json`).
- **Committed in:** `e1b53fe` (test in `9bbd43b`)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Necessary for the plan's own 422 truths to hold for real form posts. No scope creep.

## Issues Encountered

None. The manual curl check ran against a throwaway SQLite file and a temp copy of the example folder with `--no-reload` (so the environment overrides apply); the dev database checksum was unchanged and no tracked files were dirtied.

## Authentication Gates

None.

## Known Stubs

None introduced. The two stubs from 01-03 (`ProcessInbound::handle`, `TwilioTextChannel`) are unchanged.

## Threat Flags

None beyond the plan's threat model (T-01-16 to T-01-20 are mitigated and tested: limiter and caps, no overwrite of stored phone or email, `validated()` only, path-scoped exemption, first-touch fields set only at creation).

## User Setup Required

None - no external service configuration required.

## Verification

- `php artisan test --compact tests/Feature/Inbound`: 64 passed (17 LeadFormTest, 24 ContactMatchingTest, 15 FakeTextWebhookTest, 8 TextChannelTest).
- `php artisan test --compact tests/Feature/Icm tests/Feature/Quality tests/Unit/ArchitectureTest.php`: 83 passed, no regression.
- `php artisan test --compact` (whole suite): 149 passed, 627 assertions.
- `vendor/bin/pint --dirty --format agent`: passed.
- All task acceptance criteria re-run and passing, including `grep -c updateOrCreate app/Actions/RecordInbound.php` printing 0.
- Per CLAUDE.md, Ian should run the complete suite once: `php artisan test --compact`.

## Next Phase Readiness

- Plan 01-05 can render `meta.form` from form messages and show the `note` activities (their subject is the message) and the per-touch `lead_source_key` and `cost_cents` on the timeline.
- Plan 01-06 can total cost per lead from `activities.cost_cents`; null means unknown, 0 means a free source such as referral.
- Open point for Ian: FA-1 in the plan. The form contract is exactly the fields name, phone, email, message, source; confirm if a client's website posts different field names.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*

## Self-Check: PASSED

- All 4 created files and 6 modified files exist on disk.
- Commits found on the branch: `535bdfd`, `c8a3681`, `9bbd43b`, `e1b53fe`, `44615ca`, `7756dfb`.
- Measured commit count for the plan: 6 code commits (`6569e10..7756dfb`), plus the summary commit.
