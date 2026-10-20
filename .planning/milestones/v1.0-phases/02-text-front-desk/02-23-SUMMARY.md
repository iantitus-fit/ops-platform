---
phase: 02-text-front-desk
plan: 23
subsystem: dashboard
tags: [livewire, filament, table-widget, alpine, phone-view, pest, tdd]

requires:
  - phase: 02-text-front-desk
    provides: ConversationThread with the Needs you block (02-22) and paused banner (02-20), OwnerAlert failure columns (02-11), live draft rows and gate reasons
provides:
  - Conversation view that puts the Needs you block first, then the paused banner, the thread and the reply box, with the Customer section after the thread and collapsed
  - Thread in a 60vh scroll box that opens at the newest message, with Customer / Front desk / You labels and three different bubble colours
  - ConversationThread::draftNotAlerted() and Message::alertFailure() / Message::ownerAlerts() (the D-19 "Not alerted" flag)
  - Widgets DraftsWaiting, PausedConversationsWaiting, NewLeads, TodaysConversations in place of the stock account and Filament info widgets
affects: [02-24 opt-out block, phase verification of GATE-04, D-30 real-phone UAT]

actuals:
  tokens: 9600
  tasks: 2
  commits: 4

plan_head_before: e3afd46d783cdfa47875e3db4aff9c7aa125e187
plan_head_after: 3f30cb1071fe9cc56c5f1fb9ccd3bef5eedaf0d0

tech-stack:
  added: []
  patterns:
    - "Table widgets with $isLazy = false, $columnSpan = 'full' and an explicit $sort, found by discoverWidgets"
    - "Correlated Eloquent subquery as a where() column (latest non-draft message author) instead of raw SQL"
    - "Business-day window: Carbon::now(business zone)->startOfDay()/endOfDay() converted to the app zone before binding"
    - "Author-keyed bubble styling with data-author and translucent rgba backgrounds that work in light and dark"

key-files:
  created:
    - app/Filament/Widgets/DraftsWaiting.php
    - app/Filament/Widgets/PausedConversationsWaiting.php
    - app/Filament/Widgets/NewLeads.php
    - app/Filament/Widgets/TodaysConversations.php
    - tests/Feature/Filament/ConversationViewTest.php
    - tests/Feature/Filament/DashboardWidgetsTest.php
  modified:
    - app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
    - app/Livewire/ConversationThread.php
    - app/Models/Message.php
    - app/Providers/Filament/AdminPanelProvider.php
    - resources/views/livewire/conversation-thread.blade.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The not-alerted rule (alerts exist and every one failed) lives once on Message::alertFailure(); the thread flag and the DraftsWaiting row both read it, so the two screens cannot disagree."
  - "The 'Add your phone on your profile' advice is appended only for the 'No phone on file' failure; a provider error reads 'Not alerted: <reason>.' because the profile hint would mislead."
  - "Widgets set $isLazy = false so the four tables are in the first page response (a phone or desk sees them without a second round trip, and the page test can see the headings)."
  - "Paused-and-waiting is decided by the latest non-draft, non-withdrawn message author via a correlated subquery, ordered by conversations.last_message_at with the NULL guard used in ContactsTable."
  - "Draft box words are filled in by the browser (wire:model), so the ordering test uses the draft textarea as the marker rather than the draft text."

patterns-established:
  - "Home screen widgets live in app/Filament/Widgets and each links its rows to the conversation or contact view"

requirements-completed: [GATE-04]

coverage:
  - id: D1
    description: "D-29: at phone width the held reason, the draft box and Send come before the thread; the Customer section follows and is collapsed"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#puts the held reason, the draft and Send ahead of the thread, with the customer details after them (D-29)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#collapses the customer details section so the draft comes first on a phone (D-29)"
        status: pass
    human_judgment: false
  - id: D2
    description: "D-29: thread opens at the latest message in a bounded box; customer, front desk and owner messages are labelled and coloured apart; reply and draft boxes have inner padding"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#opens the thread at the latest message inside a bounded box (D-29)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#labels and styles customer, front desk and owner messages differently (D-29)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#gives the reply box and the draft box inner padding (D-29)"
        status: pass
    human_judgment: true
    rationale: "Whether the view reads well and the scroll lands at the newest message on a real phone is a judgment call; element order and attributes are tested, appearance is not."
  - id: D3
    description: "D-19: a held draft whose alert could not be sent is flagged in the conversation view and on the home screen"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationViewTest.php#flags a held draft whose alert could not be sent (D-19)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Filament/DashboardWidgetsTest.php#flags a waiting draft whose alert could not be sent and links each row to its conversation"
        status: pass
    human_judgment: false
  - id: D4
    description: "D-32: the desktop home screen shows drafts waiting, paused conversations with a waiting customer, new leads and today's conversations, in place of the stock widgets"
    requirement: GATE-04
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/DashboardWidgetsTest.php (six tests, including shows the four home screen widgets in place of the stock ones)"
        status: pass
    human_judgment: false
  - id: D5
    description: "D-30: the alert link opened on a real phone over Wi-Fi lands on the draft; also covers 02-12 profile phone, remember me and the no-phone warning"
    requirement: GATE-04
    verification: []
    human_judgment: true
    rationale: "Needs a real phone on the same Wi-Fi as this Mac; the executor cannot run it. It is the human-check in Task 2 for the verifier's UAT."

duration: 25min
completed: 2026-10-11
status: complete
---

# Phase 2 Plan 23: Phone-friendly conversation view and home screen Summary

**The alert link now opens on what needs the owner (held reason, editable draft, Send), the thread opens at the newest message with who-said-what colours and a "Not alerted" flag, and the desktop home screen lists drafts waiting, paused customers waiting, new leads and today's conversations.**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-12T03:40:00Z
- **Tasks:** 2 (tracer + tdd auto)
- **Files modified:** 12 (6 created, 6 modified)

## Accomplishments

- Conversation view reordered: Needs you block, paused banner, thread, reply box; the Customer section is now after the thread, collapsible and collapsed (D-29).
- Thread sits in a `max-height: 60vh` box that `x-init` scrolls to the bottom; customer, front desk and owner bubbles are labelled Customer / Front desk / You, aligned and tinted differently; both textareas have inner padding (D-29, Phase 1 UAT fixes).
- `Not alerted: No phone on file. Add your phone on your profile.` shows in the Needs you block when every alert for the live draft failed, and `Not alerted: <reason>` shows on the draft's row on the home screen (D-19).
- Four table widgets replace the stock ones: DraftsWaiting (oldest first), PausedConversationsWaiting (customer spoke last, oldest first), NewLeads (lead status, last 7 days, cost per lead in dollars), TodaysConversations (business-zone day, newest first); rows link to the conversation or contact (D-32).
- Tracer feedback gate: interactive run, end-of-phase mode, automated-only verify. Re-ran `tests/Feature/Filament` end to end (61 passed) before starting Task 2, so expansion continued.

## Task Commits

1. **Task 1: phone-friendly conversation view (tracer)**
   - RED `4603cf1` (test) - seven tests; five failed on the planned assertions, the ordering test already held because the thread view order was already held-reason, draft, Send, thread (the Customer-after-Send case is covered by the collapse test, which failed), and the no-flag test errored on the undefined `draftNotAlerted` property
   - GREEN `9a097cf` (feat)
2. **Task 2: home screen widgets (tdd)**
   - RED `204df2d` (test) - six tests, all failing on planned assertions
   - GREEN `3f30cb1` (feat)

**Plan metadata:** the docs commit following this summary.

## TDD Gate Compliance

- **RED (Task 2):** `vendor/bin/pest tests/Feature/Filament/DashboardWidgetsTest.php --log-junit=...` exited 1 with 6 tests, 0 passed, 6 failed. `gsd-tools check tdd-red-evidence` returned `RED_EVIDENCE_OK` (`target_test_failed`) on the JUnit report. Semantic assessment: the generated widget scaffold's placeholder `Model::query()` crashed the first run (Cannot instantiate abstract class Model, an INVALID_RED fixture fault), so the four scaffolds were pointed at their real models with an always-false filter (`whereRaw('1 = 0')`) and the run repeated. Then every test failed on its planned assertion (`assertCanSeeTableRecords`, missing headings and text, missing headings on /admin). The scaffolds were left uncommitted so the RED commit holds only the test file.
- **RED (Task 1):** run with Pest's own JSON summary, not the classifier; failures were the planned assertions (collapse order, scroll attribute, author styles, padding, not-alerted text) and the undefined `draftNotAlerted` property. One test (ordering) already passed; noted above.
- **GREEN:** both tasks' targeted tests, `tests/Feature/Filament` (67) and the full suite (805 tests) pass. No REFACTOR commits.

## Files Created/Modified

- `app/Filament/Widgets/DraftsWaiting.php`, `PausedConversationsWaiting.php`, `NewLeads.php`, `TodaysConversations.php` - the four home screen tables
- `app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php` - thread first, Customer section collapsible and collapsed
- `app/Livewire/ConversationThread.php` - `draftNotAlerted()` computed and cache clearing
- `app/Models/Message.php` - `ownerAlerts()` relation and `alertFailure()`
- `resources/views/livewire/conversation-thread.blade.php` - new order, scroll box, bubbles, padding, flag
- `app/Providers/Filament/AdminPanelProvider.php` - stock widgets removed
- `tests/Feature/Filament/ConversationViewTest.php`, `DashboardWidgetsTest.php` - new tests
- `docs/sprints/sprint-1.md` - two walk-through lines (Alpine `x-init`, Filament table widgets)

## Decisions Made

See `key-decisions` above. In short: one shared not-alerted rule on `Message`, the profile hint only for the no-phone failure, non-lazy widgets, subquery-based "customer spoke last", and the draft textarea as the ordering marker in the test.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical] Shared not-alerted rule on Message instead of a thread-only query**
- **Found during:** Task 2 (the widget needs the same D-19 rule as the thread)
- **Issue:** The plan lists `ConversationThread::draftNotAlerted()` only, but the home screen row needs the identical rule; two copies could drift and show different answers for one draft.
- **Fix:** Added `Message::ownerAlerts()` and `Message::alertFailure()`; the thread and the widget both call it. `app/Models/Message.php` is not in the plan's file list.
- **Verification:** ConversationViewTest and DashboardWidgetsTest both pass on it.
- **Committed in:** `3f30cb1`

**2. [Rule 1 - Bug] Dashboard test could not see the widgets because they loaded lazily**
- **Found during:** Task 2 (GREEN)
- **Issue:** Filament widgets are lazy by default, so the first `/admin` response had no headings.
- **Fix:** `$isLazy = false` on the four widgets.
- **Committed in:** `3f30cb1`

**3. [Rule 1 - Bug] Padding test counted the bubble padding too**
- **Found during:** Task 1 (GREEN)
- **Issue:** The test counted every `padding: 0.5rem 0.75rem`, and the new bubbles use the same value.
- **Fix:** The test counts only textarea tags with that padding.
- **Committed in:** `9a097cf`

**4. Plan wording refined (not a rule fix):** the "Add your phone on your profile" advice is appended only when the failure is "No phone on file". A provider error would otherwise tell the owner to fix something that is not wrong.

---

**Total deviations:** 3 auto-fixed (1 missing critical, 2 bugs), 1 wording refinement
**Impact on plan:** No scope creep; the extra model method keeps one rule in one place.

## Issues Encountered

- The generated widget scaffolds crashed the first RED run (see TDD Gate Compliance); handled by pointing them at real models before recording RED.
- The ordering assertion in Task 1 uses the draft textarea as a marker because the draft words are filled in by the browser and are not in the server HTML.

## Authentication Gates

None.

## Known Stubs

None. The `whereRaw('1 = 0')` RED scaffolds were replaced by the real queries before the GREEN commit.

## Threat Flags

None. All new output is inside the signed-in panel; widget columns and the thread's Blade echoes escape text (T-02-61), and the "Not alerted" text is plain state rendered through Filament's escaping badge.

## User Setup Required

None - no external service configuration required.

## Open Item for the Verifier (UAT)

**D-30 real-phone check (human-check in Task 2, not run by the executor).** Set `APP_URL` to this Mac's Wi-Fi address (for example `http://192.168.1.20:8000`), run `php artisan serve --host=0.0.0.0` with `php artisan queue:listen` and `php artisan schedule:work`, sign in at `/admin` with Remember me ticked, open the Business Folder page (warns that no one has a phone number), set your phone on your profile and see the warning go (plan 02-12), text "How much for a new unit?" from `/dev/chat`, and open the alert link from the fake channel log on a real phone on the same Wi-Fi. Expect: sign in once, the held reason, the draft and Send visible without scrolling, the thread scrolled to the latest message, customer details collapsed; tap Send and see the reply in `/dev/chat`. Recorded in `.planning/WINDOWS.md` as an unrun verify.

## Next Phase Readiness

Plan 02-24 (opt-out block) can build on the thread and home screen as they are. The remaining risk for this plan is the D-30 phone check above.

## Self-Check: PASSED

- Created files exist: four widgets, `ConversationViewTest.php`, `DashboardWidgetsTest.php`.
- Commits `4603cf1`, `9a097cf`, `204df2d`, `3f30cb1` are on the branch; `git rev-list --count e3afd46..HEAD` was 4.
- Acceptance criteria re-run: `->collapsed()` present; `x-init`, `scrollHeight`, `Front desk`, `You` in the thread view; `assertSeeInOrder` and `Not alerted:` in ConversationViewTest; no `FilamentInfoWidget` in the panel provider; four widget files each containing `TableWidget`; `Drafts waiting` and `inOrder` in DashboardWidgetsTest.
- `php artisan test --compact tests/Feature/Filament` 67 passed; `php artisan test --compact` 805 passed.

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-11*
