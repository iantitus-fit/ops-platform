---
phase: 08-schedule-and-booking
plan: 09
subsystem: front-desk
tags: [booking-link, scheduling, livewire, send-rule, tdd, a11y]

requires:
  - phase: 08-schedule-and-booking
    provides: MintBookingLink, the booking page and route, ReplyParts.bookingLine and OutboundSender's booking_link arm, BookingSettings
provides:
  - ServiceToBook, the one D-01 and D-02 check, with whyNoLink() and its four plain reasons
  - WithdrawUnsentLink, a link that never reached the customer is revoked and the links it superseded are put back
  - The booking link on the front desk's own scheduling send, and on an owner-approved held scheduling draft (D-45)
  - meta.service_slug on every stored scheduling draft
  - The booking link box on the approve step, a native checkbox proven by axe and keyboard
  - The example front desk's D-05 line
affects: [08-10, 08-11, 08-12, 08-13, 08-14, 08-15, 08-16, phase-8-uat, front-desk, customer-communication]

actuals:
  tokens: 23500
  tasks: 4
  commits: 7

tech-stack:
  added: []
  patterns:
    - "One class holds a decision two paths share (ServiceToBook), so the agent's send and the owner's approval cannot drift"
    - "Measure the length with a 64-character placeholder token before minting, so a refusal mints and supersedes nothing"
    - "Mint, send, attach on success; on any failure revoke and put back what the mint superseded"
    - "A locked Livewire property filled by the server at every render names what the owner saw; the browser's flag only asks"

key-files:
  created:
    - app/Rooms/ScheduleAndBooking/ServiceToBook.php
    - app/Rooms/ScheduleAndBooking/WithdrawUnsentLink.php
    - tests/Feature/Booking/BookingLinkTest.php
    - tests/Feature/Booking/ApprovedDraftBookingLinkTest.php
    - tests/Feature/Filament/DraftBookingLinkKeyboardTest.php
  modified:
    - app/Actions/RunFrontDesk.php
    - app/Actions/SendOwnerReply.php
    - app/Actions/ResolveDraft.php
    - app/Actions/MintBookingLink.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - examples/example-hvac/agents/front-desk.md
    - docs/BLUEPRINT.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "ServiceToBook::handle() and whyNoLink() share one private check(), so the agent path and the approval path cannot drift"
  - "The write that puts back superseded links lives in MintBookingLink::restoreSuperseded(), keeping app/Rooms/ScheduleAndBooking free of Eloquent writes the appointments scan watches"
  - "The probe sweeps found no existing test reaching the link on either path, so no existing test was re-pointed and none changed"

patterns-established:
  - "Pattern: a plan that needs a global helper in two test files in one process prefixes each file's helpers (bookingLink*, approvedDraftLink*, draftLinkKeyboard*)"

requirements-completed: [BOOK-02]

coverage:
  - id: D1
    description: "A reply labeled scheduling goes out with the owner's link line and a fresh link for the service it is about; the model never writes the URL; the link records the carrying text"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingLinkTest.php#sends the scheduling reply with the owner's link line (D-01)"
        status: pass
    human_judgment: false
  - id: D2
    description: "No link for another label, the room off, no booking file, a service not book_online or missing, a visit booked ahead; D-02 resolution; revoke on a refused or failed send; supersede; the 1,600 limit; never in a held draft; service recorded on a scheduling draft only"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingLinkTest.php (21 cases beyond the tracer's two)"
        status: pass
    human_judgment: false
  - id: D3
    description: "An approved held scheduling draft gets the link at send time after the words, with the box off sending as today, each condition checked at approval with its reason, no link when the line was not drawn, none on another label, the box starting by the reading and the owner's check deciding, refusal over 1,600, revoke on failure, supersede, the booked alert on the draft row's key"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/ApprovedDraftBookingLinkTest.php (28 cases)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The box is one native labelled checkbox: axe finds nothing in it, one Tab from the draft reaches it, its name is the line, Space unticks it"
    requirement: BOOK-02
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Filament/DraftBookingLinkKeyboardTest.php"
        status: pass
    human_judgment: true
    rationale: "The keyboard-only and screen reader walk of customer and owner pages is a UAT step (CLAUDE.md accessibility rule); axe and a real-browser key test cover only part of WCAG"
  - id: D5
    description: "The example front desk's owner-written line offers a visit after intake; no front-desk code or existing assertion changed"
    requirement: BOOK-02
    verification:
      - kind: integration
        ref: "tests/Feature/Booking/BookingLinkTest.php#tells the front desk, in the owner's own words, to offer a visit once intake is done (D-05)"
        status: pass
    human_judgment: true
    rationale: "Whether the live model actually offers the visit after the last intake answer, labeled scheduling with the link, is a Dev chat check at Phase 8 UAT"

duration: 33min
completed: 2026-11-06
status: complete
plan_head_before: 5979a24f0e5fb73654a33f4df23867b5458ade85
plan_head_after: 8843db0d57f52f61d7ae9307e8d357d492e5e758
---

# Phase 8 Plan 09: The Booking Link in the Conversation Summary

**A scheduling reply, and an owner-approved scheduling draft, now carry the owner's link line with a freshly minted, revocable booking link, decided in one class (ServiceToBook) under every D-01 and D-02 condition, with a labelled checkbox on the approve step that checks the conditions again at Send and says why when a link was left off.**

## Performance

- **Duration:** 33 min
- **Started:** 2026-11-06T14:40:56Z
- **Completed:** 2026-11-06T15:14:00Z
- **Tasks:** 4
- **Files modified:** 14 (5 created, 9 modified)

## Accomplishments

- Agent path (D-01, D-02, D-12): `RunFrontDesk::sendPassingReply()` asks `ServiceToBook`, measures the length with a 64-character placeholder, mints, adds the owner's link line as a combined text (`meta.parts` is `model_words`, `booking_link`), and attaches the sent message to the link. A refused or failed send revokes the link and puts back the earlier unbooked links (`WithdrawUnsentLink`). A link over 1,600 characters is dropped and mints nothing.
- Held drafts (D-45): `storeDraft()` records `meta.service_slug` on a scheduling draft only, from every caller (a fresh draft, a replacing draft, a draft carried over from a waiting one). No link is ever in a draft body.
- Approval path (D-45): `SendOwnerReply::sendDraft()` adds the link after the words being sent (the agent's or the owner's edit) when the box asked, the draft is scheduling and `ServiceToBook::whyNoLink()` finds every condition holding. A condition that failed after the line was drawn sends the draft as it is, records `meta.booking_link_left_off`, and the notice is a warning "Draft sent without the booking link: {reason}". Over 1,600 with the link refuses Send beside the draft box and mints nothing.
- The box: `ConversationThread` gets `addBookingLink` and a locked `bookingLinkService` filled by the server at every render; the line follows the conditions while one draft stays on screen; a replacing draft resets the box (checked on a clean reading, unchecked on a complaint, a failed or a missing reading). One native checkbox in `[data-booking-link-choice]`, 1.5rem square, labelled "A booking link for {service} will be added".
- D-05: one owner-written line in the example `agents/front-desk.md`; `FrontDeskPrompt` and front-desk code untouched.
- Docs: BLUEPRINT section 1 Built bullet; walk-through line for `route()` in `docs/sprints/sprint-1.1.md`.

## TDD Gate Compliance

All three behavior tasks followed RED then GREEN, each RED with `RED_EVIDENCE_OK` from `gsd-tools check tdd-red-evidence` on a JUnit report, then semantic inspection of the planned assertion.

- **Task 1 RED** (`de715a6`): BookingLinkTest, 2 cases. Both failed on `Expecting null not to be null` (no token in the sent text, no `BookingLink` row), the planned assertion. First draft used `BookingLink::sole()`, which threw `ModelNotFoundException` (an error, not an assertion); rewritten to assert the count first so the failure is the planned one.
- **Task 2 RED** (`deadd33`) against Task 1's uncommitted code: 20 of 23 passed already (the agent-path conditions Task 1 built), 3 failed on the planned assertion `null is identical to 'ac-repair'` (the draft's `meta.service_slug`): the held scheduling draft, the replacing draft and the carried-over draft. GREEN: `da91ad1`, which carries Task 1's code too, as the plan's commit shape says.
- **Task 3 RED** (`9a99e3e`): 29 cases, 4 passing (cases that hold with no code: refusal before any mint, the price draft called directly), 25 failing, the target on `assertSee` of the box label; the 2 browser tests failed on "Expected to see text [A booking link for AC repair will be added]". GREEN: `d33a19c`, 30 of 30.
- **Task 4 RED** (`0b9f075`): the D-05 case failed on the agent body lacking the line. GREEN: `8843db0`.

No REFACTOR commit.

## Task Commits

1. **Task 1: tracer, a scheduling reply carries the link** - RED `de715a6` (test); its GREEN code was left uncommitted per the plan and committed with Task 2's feat
2. **Task 2: the link only under D-01 and D-02, the sweep** - RED `deadd33` (test), GREEN `da91ad1` (feat, with Task 1's code)
3. **Task 3: the link on an approved draft** - RED `9a99e3e` (test), GREEN `d33a19c` (feat)
4. **Task 4: D-05 line and docs** - RED `0b9f075` (test), GREEN `8843db0` (feat)

**Plan metadata:** the docs commit that carries this summary, STATE, ROADMAP, REQUIREMENTS and WINDOWS.

Tracer gate: Task 1's verify (`php artisan test --compact tests/Feature/Booking/BookingLinkTest.php`, automated only) was re-run end to end after the GREEN code and passed (2 of 2), so the plan expanded.

## Files Created/Modified

- `app/Rooms/ScheduleAndBooking/ServiceToBook.php` - the one D-01 and D-02 check; `handle()`, `whyNoLink()` and the four reasons
- `app/Rooms/ScheduleAndBooking/WithdrawUnsentLink.php` - revoke an unsent link and restore what its mint superseded; `idsAMintWouldSupersede()`
- `app/Actions/RunFrontDesk.php` - the link step in `sendPassingReply()` and `meta.service_slug` through `storeDraft()`
- `app/Actions/SendOwnerReply.php` - the link at approval, `TOO_LONG_WITH_LINK`, `SENT_WITHOUT_LINK`, `bookingLinkServiceFor()`, `bookingLinkStartsOn()`
- `app/Actions/ResolveDraft.php` - `approve()` passes `$addBookingLink` (default false)
- `app/Actions/MintBookingLink.php` - `restoreSuperseded()`
- `app/Livewire/ConversationThread.php`, `resources/views/livewire/conversation-thread.blade.php` - the box
- `examples/example-hvac/agents/front-desk.md` - the D-05 line
- `tests/Feature/Booking/BookingLinkTest.php` (24 cases with datasets), `tests/Feature/Booking/ApprovedDraftBookingLinkTest.php` (28 cases with datasets), `tests/Feature/Filament/DraftBookingLinkKeyboardTest.php` (2)
- `docs/BLUEPRINT.md`, `docs/sprints/sprint-1.1.md`

## storeDraft() callers (D-45)

`grep -n 'storeDraft(' app/Actions/RunFrontDesk.php` lists them; each passes the slug it has:

- `RunFrontDesk.php:435` and `:440` (the run's own reply, sent-partial and held): `$run->reply->serviceSlug`
- `:814` (the waiting-draft path): the new `$draftServiceSlug`, the waiting draft's own slug when its body and label are kept, else the run's (or the cover-both draft's) slug
- `:966` and `:985` (the whole-thread second reply): `$asked['second']->reply->serviceSlug`
- `:1383` (`sendCodeAnswer()`, a refused code answer stored as a draft): `carriedServiceSlug($waiting, $run->reply->serviceSlug)`, the waiting draft's label and slug carried over

## The Sweep (Ian's note)

- **Agent path:** a probe line where the link is minted wrote the calling test's file:line for the whole suite (2,741 tests). Only `tests/Feature/Booking/BookingLinkTest.php` lines appeared. **No existing front-desk test was reached**: none of DisclosureTest, EasyAnswerWhileWaitingTest, GateTest, GateVerdictTest, HeldCodeAnswerTest, ProcessInboundTest or WaitingCustomerRepliesTest needed the booking file removed (their scripted scheduling replies name no service, and no intake answers point to one). Nothing was re-pointed, so there is no file:line list; no existing test file changed and no assertion line was removed or changed (`git diff -U0 5979a24 -- tests` shows only added files). A find outside the seven candidates, the designed stop, did not happen.
- **Approval path:** a probe at the entry of the link step in `sendDraft()` over the whole suite (2,771 tests) named only `ApprovedDraftBookingLinkTest.php` lines. **It named no pre-existing test.** The approval call sites the objective lists were checked and are unchanged: `tests/Feature/Filament/DraftReviewTest.php`, `tests/Feature/FrontDesk/AnsweredDraftTest.php`, `tests/Feature/FrontDesk/SentTimeTest.php`, `tests/Feature/DevChatTest.php`, `tests/Feature/Inbound/OptOutTest.php` (drafts labeled price, or refused before any send) and `tests/Feature/Filament/ConversationViewTest.php` (factory drafts with no label); `git diff --quiet` against the plan's first commit's parent on those six files exits 0. None was hollowed: each still approves a non-scheduling draft.
- **D-05:** after the line, `tests/Feature/Booking`, `FrontDesk`, `Icm` and `DevChatTest.php` passed 1,263 of 1,263; the whole suite passed 2,772 of 2,772. `app/Ai/FrontDeskPrompt.php` and `FrontDeskPromptTest.php` are unchanged.
- Both probes and their scratch files are removed (`storage/framework/d01-probe.txt`, `d45-probe.txt` gone; `grep -c` of both names in `app/` prints 0). While a probe was in place `ArchitectureTest` flagged its `file_put_contents` in `App\Actions`, as it should; that was the probe, gone with it.

## Decisions Made

- `ServiceToBook::handle()` and `whyNoLink()` share one private `check()`, so the agent path and the approval path cannot drift.
- The write that restores superseded links is `MintBookingLink::restoreSuperseded()`, called by `WithdrawUnsentLink` (see Deviations).
- A reply with no model words never gets a link on the agent path (a text of owner sentences only is a fixed reply that carries no reading for the booking_link part).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] The appointments-write scan flags `WithdrawUnsentLink`**
- **Found during:** Task 2 (whole-suite probe run)
- **Issue:** `tests/Unit/AppointmentWritesTest.php` flags any Eloquent write (`->update(`, `->save(` and so on) in a file under `app/Rooms/ScheduleAndBooking/`. `WithdrawUnsentLink`, which the plan puts there, wrote `booking_links.superseded_at` and failed the test (it writes booking links, not appointments, but the scan is path-based). Editing the scan would change an existing test, against the sweep's "no existing test changes".
- **Fix:** added `MintBookingLink::restoreSuperseded(array $ids)` in `app/Actions` (which already owns the supersede), and `WithdrawUnsentLink` calls it. `WithdrawUnsentLink::handle(BookingLink, array)` keeps its planned signature.
- **Files modified:** `app/Actions/MintBookingLink.php` (not in the plan's `files_modified`), `app/Rooms/ScheduleAndBooking/WithdrawUnsentLink.php`
- **Verification:** `tests/Unit` and `tests/Feature/Booking` pass (614 of 614); whole suite green
- **Committed in:** `da91ad1`

**2. [Plan defect, no code change] The plan's BASE formula is polluted by earlier planning commits**
- **Found during:** Task 2 acceptance criteria
- **Issue:** `BASE="$(git log --reverse --format=%H --grep='^[a-z]*(08-09): ' | head -1)^"` matches three earlier `docs(08-09)` planning commits (`c777ffd`, `080ddec`, `264c77d`), so BASE resolved to `4474b07`, before plans 08-02 to 08-08, and its diff listed those plans' test edits (`ProcessInboundTest`, `FrontDeskInvariantTest` and others). The criteria would have failed for reasons that are not this plan's.
- **Fix:** used the parent of this plan's own first commit, `de715a6^` = `5979a24`, the dispatch base. With it: no assertion line removed or changed, no existing test file modified, and the six draft-review files unchanged.

---

**Total deviations:** 1 auto-fixed (Rule 3), 1 plan defect noted
**Impact on plan:** none on scope. The first keeps an existing test untouched; the second only corrects which commit the acceptance diffs start from.

## Test-Authoring Notes

- Livewire's fake webhook default `MessageSid` is `SM` plus 32 `c`, so a second text with that sid is silently ignored as a duplicate; the "newer draft replaced" tests use their own sids. (Found when a replacement test passed vacuously.)
- A stale `draftBody` validation error stays in the component after a refused Send until the next draft is resolved; harmless in the UI (the block hides once the draft is sent), so the test asserts the text count and not `assertHasNoErrors()` after the retry.
- A refused agent send becomes a draft and the customer gets the holding reply, so "no text" in the refused-send test means no text carrying the reply's words.

## Issues Encountered

None that remain.

## Auth Gates

None.

## Known Stubs

None.

## Threat Flags

None. The only new surface is the owner-side checkbox on an existing screen, already covered by T-08-69 (locked `bookingLinkService`, server re-check). One cost note, not a threat: the thread polls every 5 seconds and now runs `ServiceToBook` (one appointments `exists()` and one intake query) on each poll while a scheduling draft is live.

## Hand Checks for Phase 8 UAT (never paused, recorded in `.planning/WINDOWS.md`)

1. **CI pgsql leg** (WINDOWS id 57): after Ian pushes the phase branch, green for `BookingLinkTest`, `ApprovedDraftBookingLinkTest`, `DraftBookingLinkKeyboardTest` and the front-desk tests.
2. **Dev chat, D-05** (WINDOWS id 58): after the last intake answer the front desk's offer comes back labeled scheduling and carries the link.
3. **Held scheduling draft, D-45** (WINDOWS id 59): the approve step reads "A booking link for {service} will be added" with the box checked; Send texts the link after the words; with the box off it texts none; on a complaint-read scheduling draft the box starts unchecked.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The link is in the conversation on both paths; 08-10 onward (browser tests of the booking page, reminders, the owner's schedule screens) can mint and read links through `ServiceToBook` and `MintBookingLink`.
- Nothing blocks the next plan. The plan estimate was 155k tokens at low confidence; the realized diff measures about 23.5k tokens at chars/4 (tests are 80 percent of it).

## Self-Check: PASSED

- Created files exist: `ServiceToBook.php`, `WithdrawUnsentLink.php`, `BookingLinkTest.php`, `ApprovedDraftBookingLinkTest.php`, `DraftBookingLinkKeyboardTest.php` all found.
- Commits exist on the branch: `de715a6`, `deadd33`, `da91ad1`, `9a99e3e`, `d33a19c`, `0b9f075`, `8843db0` (7, measured from `5979a24`).
- Whole suite: 2,772 passed of 2,772 on SQLite; Pint clean.

---
*Phase: 08-schedule-and-booking*
*Completed: 2026-11-06*
