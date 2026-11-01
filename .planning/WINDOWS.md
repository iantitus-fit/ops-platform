---
schema_version: 1
open_count: 16
waived_count: 1
fixed_count: 28
total_count: 45
last_updated: 2026-11-01T18:26:57.546Z
---

# Broken Windows Ledger

> Cross-phase defect register. With `workflow.windows_enforce` enabled, `/gsd-ship` blocks while `open_count > 0`.
> Waive with `gsd-tools windows waive <id> "<reason>"` (reason required).
> Mark fixed with `gsd-tools windows fixed <id>`.

| id | phase | kind | file | line | description | status | reason | recorded_at | resolved_at |
|----|-------|------|------|------|-------------|--------|--------|-------------|-------------|
| 1 | 01 | stub | app/Jobs/ProcessInbound.php | 25 | ProcessInbound::handle() is empty by design in Phase 1; Sprint 1 front-desk agent runs here (AGNT-02) | open |  | 2026-10-11T13:23:26.587Z |  |
| 2 | 01 | stub | app/Channels/TwilioTextChannel.php |  | TwilioTextChannel::send() and parseInbound() throw by design until Phase 2 (TXT-01) | open |  | 2026-10-11T13:23:26.689Z |  |
| 3 | 01 | unrun-verify | tests/Feature/Filament/ContactResourceTest.php |  | Plan 01-06 Task 3 human-check (sign in, browse contacts, reply, close and reopen in a browser) not run; left for end-of-phase UAT | fixed |  | 2026-10-11T13:57:20.122Z | 2026-10-11T18:27:32.167Z |
| 4 | 01 | unrun-verify | tests/Feature/Quality/SeederTest.php |  | Plan 01-07 Task 3 human-check (browser walk of /dev/chat, Business Folder Sync Now, cards) not run by the executor; left for end-of-phase UAT. Dana shares the /dev/chat default number, so step 4 shows four contacts, not five | fixed |  | 2026-10-11T15:08:41.510Z | 2026-10-11T18:27:32.267Z |
| 5 | 02 | unrun-verify | docs/sprints/sprint-1.md |  | Plan 02-23 D-30: real-phone check of the alert link over Wi-Fi (also covers 02-12 profile phone, remember me, no-phone warning) not run by the executor | open |  | 2026-10-12T03:40:04.898Z |  |
| 6 | 02 | unrun-verify | tests/Feature/FrontDesk/GateTest.php |  | Plan 02-35 Task 1 human-check (UAT Test 2 re-run with live keys: 'My AC is blowing warm air' sends its intake question on its own; gate:export spot-check of promise and complaint scores on other ordinary texts) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-13T14:26:36.938Z |  |
| 7 | 02 | unrun-verify | tests/Feature/FrontDesk/SentTimeTest.php |  | Plan 02-36 Task 2 human-check (UAT Test 1 order: send a held draft from the conversation screen and see the holding reply listed first, each with its own time) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-13T14:42:56.426Z |  |
| 8 | 02 | unrun-verify | tests/Feature/DevChatTest.php |  | Plan 02-38 Task 1 human-check (/dev/chat after a held draft shows only the customer's text and the holding reply, then the sent reply after Send) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-13T14:50:49.105Z |  |
| 9 | 02 | unrun-verify | tests/Feature/FrontDesk/FrontDeskPromptTest.php |  | Plan 02-39 Task 3 human-check (live run after icm:sync: Dana's held draft does not call the visit tomorrow or booked; 'My furnace won't turn on.' from a new number gets a furnace-repair intake question) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-13T14:58:42.997Z |  |
| 10 | 02 | unrun-verify | tests/Feature/Filament/ConversationViewTest.php |  | Plan 02-40 Task 1 human-check (UAT Test 1 layout at 390x664 or on a phone: held reason, Customer wrote line, draft, Send and Discard visible without scrolling, with a short thread, a ~14-message thread and a long customer text) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-13T15:03:01.233Z |  |
| 11 | 02.1 | unrun-verify | tests/Feature/Filament/ConversationViewTest.php |  | Plan 02.1-10 Task 1 human-check (owner phone walk: open a Needs you card from an alert after the customer texted twice; the wrote-again line shows above Send; on a draft answered by a price sentence, Send shows 'Already answered') not run by the executor; left for end-of-phase UAT | fixed |  | 2026-10-14T20:59:32.083Z | 2026-10-18T15:30:23.672Z |
| 12 | 02.1 | unrun-verify | tests/Feature/FrontDesk/EasyAnswerWhileWaitingTest.php |  | Plan 02.1-16 human-check (live model with a provider key, after icm:sync: from /dev/chat ask 'How much is the diagnostic?', then 'How much for a new system?' and answer the size, then while a draft waits ask an unrelated question and then check in; read the stored verdicts and AgentReply fields: price_services, asks_discount, touches_waiting and waiting_note set sensibly) not run by the executor; left for end-of-phase UAT | fixed |  | 2026-10-14T22:09:18.451Z | 2026-10-18T15:30:23.770Z |
| 13 | 02.1 | unrun-verify | resources/views/livewire/conversation-thread.blade.php |  | Ian's UX walk on his phone at UAT (02.1 D-48): walk the Needs you card and the Business Folder Offers section, then decide UI-REVIEW fix 2's styling (card hierarchy, colour, icon) and fix 3 (Offers warning colour, file name, date format), both deferred until this walk. Also: Contacts and Conversations tables at 375 wide, pick the columns to hide; the thread's scroll box inside the page scroll; the hardcoded red (#dc2626) in dark mode | waived | UAT test 1 (Ian, 2026-10-17): the phone walk was done and the look fails on a phone; recorded as a design issue for a separate phone design pass (.planning/todos/pending/2026-10-17-phone-design-pass.md), not a 02.1 gap | 2026-10-15T02:25:15.746Z | 2026-10-18T15:30:24.235Z |
| 14 | 02.1 | unrun-verify | tests/Feature/FrontDesk/CustomerReadingTest.php |  | Plan 02.1-17 human-check (live JEV_API_KEY: text from /dev/chat 'My head is pounding and the kids feel dizzy since the furnace came on.' and confirm gate.customer_reading shows Jev answered a call with an empty draft_reply and read life_safety above 0.075) not run by the executor; left for end-of-phase UAT | fixed |  | 2026-10-16T20:57:36.914Z | 2026-10-18T15:30:23.863Z |
| 15 | 02.1 | unrun-verify | tests/Feature/FrontDesk/PossibleDangerTest.php |  | Plan 02.1-21 human-check (live provider key: text from /dev/chat 'My head is pounding and the kids feel dizzy since the furnace came on.' and confirm the stored AgentReply sets possible_danger true) not run by the executor; left for end-of-phase UAT | fixed |  | 2026-10-16T21:46:11.228Z | 2026-10-18T15:30:23.964Z |
| 16 | 02.1 | unrun-verify | tests/Feature/SendRule/SafetyCheckOutageTest.php |  | Plan 02.1-23 human-check (live JEV_API_KEY with php artisan schedule:work running: confirm the health ping succeeds against OpenRouter and no outage row opens; set a wrong key, wait for the next ping, and confirm one safety_check_down text reaches the owner's phone; restore the key and confirm the back-up text) not run by the executor; left for end-of-phase UAT | fixed |  | 2026-10-16T22:06:46.795Z | 2026-10-18T15:30:24.053Z |
| 17 | 02.1 | unrun-verify | resources/views/livewire/conversation-thread.blade.php |  | Plan 02.1-25 human-check (on a phone, a Needs you card after the customer wrote again shows the quoted newest text and 'Send anyway'; tapping Send twice sends once; Discard asks first and Cancel keeps the draft) not run by the executor; left for end-of-phase UAT with entry 13's walk | fixed |  | 2026-10-16T22:29:40.293Z | 2026-10-18T15:30:24.142Z |
| 18 | 04 | stub | resources/views/filament/rooms/section.blade.php | 3 | Room section view body is empty on purpose; plan 04-06 fills it with the room's line (D-21) | fixed |  | 2026-10-22T21:54:44.369Z | 2026-10-23T01:36:56.101Z |
| 19 | 04 | unrun-verify | tests/Feature/Rooms/RoomAccessibilityTest.php |  | CI run on the pushed phase branch: tests workflow green on both sqlite and pgsql legs, including PretendRoomBootTest and RoomAccessibilityTest (plan 04-09 human-check, for end-of-phase UAT) | fixed |  | 2026-10-23T01:44:47.162Z | 2026-10-26T22:56:08.734Z |
| 20 | 05 | unrun-verify | app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php |  | Plan 05-03 hand check (D-18, carried from Phase 4): keyboard-only and VoiceOver walk of the company knowledge section on /admin, the first real room section; not run by the executor, left for Phase 5 UAT | open |  | 2026-10-25T23:24:32.493Z |  |
| 21 | 05 | unrun-verify | app/Rooms/Marketing/MarketingSection.php |  | Plan 05-05 hand check (D-18): keyboard-only and VoiceOver walk of the marketing section on /admin, and read the offers line against the front desk on the day an offer ends; not run by the executor, left for Phase 5 UAT | open |  | 2026-10-25T23:41:24.145Z |  |
| 22 | 05 | unrun-verify | app/Rooms/ContentGeneration/ContentGenerationSection.php |  | Plan 05-06 hand check (D-18): keyboard-only and VoiceOver walk of the content generation section on /admin, empty and with the brief in place; not run by the executor, left for Phase 5 UAT | open |  | 2026-10-25T23:55:41.982Z |  |
| 23 | 05 | unrun-verify | app/Rooms/Money/MoneySection.php |  | Plan 05-07 hand check (D-18): keyboard-only and VoiceOver walk of the money section on /admin, empty and with the notes in place; not run by the executor, left for Phase 5 UAT | open |  | 2026-10-26T00:08:17.569Z |  |
| 24 | 05 | unrun-verify | app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php |  | Plan 05-08 hand check (D-18): keyboard-only and VoiceOver walk of the Google Business Profile section on /admin, empty and with the categories file in place; not run by the executor, left for Phase 5 UAT | open |  | 2026-10-26T00:21:53.598Z |  |
| 25 | quick-261026-jig | unrun-verify | .github/workflows/tests.yml |  | CI human check after push: both legs pass on Ubuntu 24.04; first run cache miss then re-run hit (install-deps only, no Chromium download); no step exceeds its timeout | fixed |  | 2026-10-26T19:14:54.022Z | 2026-10-26T22:56:08.825Z |
| 26 | quick-261026-lrs | unrun-verify | .github/workflows/tests.yml |  | After Ian pushes gsd/quick-ci-runner: both CI legs pass; the browser step shows no package-manager output and finishes in seconds on a cache hit; no 'Host system is missing dependencies' message; if libraries are named, list them for Ian and do not add apt back. Replaces check (4) of row 25 (quick-261026-jig). | fixed |  | 2026-10-26T20:50:09.012Z | 2026-10-26T22:56:08.916Z |
| 27 | 06 | unrun-verify | tests/Feature/Rooms/HandBuiltRegistrySyncTest.php |  | Postgres: after Ian pushes the phase branch, the CI pgsql leg runs HandBuiltRegistrySyncTest and the rest of the suite green (06-01 hand check) | fixed |  | 2026-10-27T21:48:43.201Z | 2026-10-28T12:17:21.837Z |
| 28 | 06 | unrun-verify | .github/workflows/tests.yml |  | Postgres: after Ian pushes the phase branch, the CI pgsql leg runs FrontDeskBootTest and RoomClaimedWidgetsTest and the rest of the suite green (06-02 hand check) | fixed |  | 2026-10-27T22:28:22.535Z | 2026-10-28T12:17:21.932Z |
| 29 | 06 | unrun-verify | tests/Feature/Rooms/TeamBootTest.php |  | 06-03 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg is green with both room wraps registered and no ALRT change yet (local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact) | fixed |  | 2026-10-28T00:36:39.046Z | 2026-10-28T12:17:22.024Z |
| 30 | 06 | unrun-verify | tests/Feature/Alerts/AlertSentOnceTest.php |  | 06-04 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/Alerts/AlertSentOnceTest.php green (the conditional update's affected-row count and the frozen-clock 119/120 second comparison on Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/Alerts) | fixed |  | 2026-10-28T13:44:35.628Z | 2026-10-28T18:14:47.414Z |
| 31 | 06 | unrun-verify | tests/Feature/Alerts/AlertDedupeKeyTest.php |  | 06-05 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/Alerts/AlertDedupeKeyTest.php green (the refused duplicate inside the test's transaction is survived through createOrFirst's savepoint, and many NULL keys sit under the unique index; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/Alerts) | fixed |  | 2026-10-28T15:46:45.038Z | 2026-10-28T18:14:47.506Z |
| 32 | 06 | unrun-verify | tests/Feature/FrontDesk/ClaimTokenTest.php |  | 06-06 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/FrontDesk/ClaimTokenTest.php and FrontDeskFailureTest.php green (the claim_token column, its (conversation_id, claim_token) index and the token-matched failed() lookup on Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/FrontDesk/ClaimTokenTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php) | fixed |  | 2026-10-28T16:14:33.573Z | 2026-10-28T18:14:47.601Z |
| 33 | 06 | unrun-verify | tests/Feature/FrontDesk/FailureReportOnceTest.php |  | 06-07 hand check, left unrun for UAT and the phase gate before /gsd-verify-work: after the phase branch is pushed, the CI tests workflow is green on both legs for the whole phase, tests/Feature/FrontDesk/FailureReportOnceTest.php included (the conditional update on messages.failure_reported_at is atomic on SQLite and Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact) | fixed |  | 2026-10-28T16:36:32.260Z | 2026-10-28T18:14:47.697Z |
| 34 | 07 | unrun-verify | .github/workflows |  | 07-01: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectWordTest and ProjectWordSpecTest included (criterion 1 on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T12:37:24.851Z | 2026-11-01T17:50:58.522Z |
| 35 | 07 | unrun-verify | .github/workflows |  | 07-02: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectRecordTest included, in particular 'refuses a second project opened by a new lead for one contact' (the partial unique index projects_one_lead_project_per_contact on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T15:45:59.200Z | 2026-11-01T17:51:08.314Z |
| 36 | 07 | unrun-verify | .github/workflows |  | 07-03: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectCardTest and CardInjectionTest included (the card's project section and the quoted project name on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T17:30:20.550Z | 2026-11-01T17:51:08.413Z |
| 37 | 07 | unrun-verify | .github/workflows |  | 07-04: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectActionsTest and ProjectWritesTest included (close, reopen and rename under a row lock, and the write-confinement scan, on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T18:55:35.664Z | 2026-11-01T17:51:08.505Z |
| 38 | 11 | unrun-verify | app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php |  | 07-05: UAT keyboard-only and VoiceOver walk of a contact screen with two projects: the list is reachable, New, Close, Reopen and Rename work without a mouse, the New and Close confirmations and the rename form take focus and return it, and each button has an accessible name (CLAUDE.md WCAG 2.2 AA; New confirmation added by review WR-03) | open | Moved to Phase 11 (Ian, 2026-11-01): the live VoiceOver listen joins the Phase 11 owner-screen pass (A11Y-03), as Phase 5 did; the keyboard, focus and button-name checks are proven by the browser tests in 08eeec0 and a9497bc, green in CI | 2026-10-30T20:18:50.026Z |  |
| 39 | 07 | unrun-verify | .github/workflows |  | 07-05: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectsRelationManagerTest included (the contact screen's project list and its New, Close, Reopen and Rename actions on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T20:18:50.137Z | 2026-11-01T17:51:08.604Z |
| 40 | 07 | unrun-verify | .github/workflows |  | 07-06: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with LeadsBootTest, FrontDeskBootTest and RoomWidgetOwnershipTest included (ROOM-05 on Postgres for the leads room) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T22:36:27.019Z | 2026-11-01T17:51:08.708Z |
| 41 | 07 | unrun-verify | resources/views/filament/rooms/leads-section.blade.php |  | 07-07: UAT keyboard-only and VoiceOver walk of /admin with three open projects: each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date (D-15, CLAUDE.md WCAG 2.2 AA) | fixed | Evidence: Phase 7 UAT test 3, passed 2026-10-31 (07-UAT.md) | 2026-10-30T23:42:17.829Z | 2026-11-01T18:26:57.546Z |
| 42 | 07 | unrun-verify | .github/workflows |  | 07-07: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with LeadsBootTest (axe included) and LeadsSectionTest included (the leads section on Postgres) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-30T23:42:17.942Z | 2026-11-01T17:51:08.804Z |
| 43 | 07 | unrun-verify | .github/workflows |  | 07-08: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular OpenOnNewLeadTest's 'loses a race without an error and leaves one project' (the savepoint and the partial index on Postgres) and the three changed test lines (criterion 4 on both databases) | fixed | Evidence: CI run 41226007469 (Ian, 2026-11-01) | 2026-10-31T15:09:06.795Z | 2026-11-01T17:51:08.895Z |
| 44 | 11 | unrun-verify | app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php |  | 07-09: UAT retest of WINDOWS entry 38 with VoiceOver in Safari and by keyboard, contact with two projects: Tab from the top reaches the list; Close opens a dialog announced as Close Job 1?, and confirming it leaves focus on the row's button, read as Reopen Job 1, button, and the Closed notification is announced; Reopen opens a dialog announced as Reopen Job 1?, and confirming it leaves focus on Close Job 1; every row button is announced with its project (Close Johnny, Rename Job 2); New and Rename still take and return focus; the row buttons look and space as before (gaps G-07-2.1 to G-07-2.3) | open | Moved to Phase 11 (Ian, 2026-11-01): the live VoiceOver listen joins the Phase 11 owner-screen pass (A11Y-03), as Phase 5 did; the keyboard, focus and button-name checks are proven by the browser tests in 08eeec0 and a9497bc, green in CI | 2026-11-01T15:36:26.960Z |  |
| 45 | 07 | unrun-verify | .github/workflows |  | 07-09: after Ian pushes the phase branch, the CI tests workflow is green on both legs with ProjectsRelationManagerTest and ProjectsRelationManagerKeyboardTest included (the focus proof in Chromium on SQLite and Postgres) | open |  | 2026-11-01T15:36:34.622Z |  |

````json
[
  {
    "id": 1,
    "kind": "stub",
    "phase": "01",
    "file": "app/Jobs/ProcessInbound.php",
    "line": 25,
    "description": "ProcessInbound::handle() is empty by design in Phase 1; Sprint 1 front-desk agent runs here (AGNT-02)",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-11T13:23:26.587Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 2,
    "kind": "stub",
    "phase": "01",
    "file": "app/Channels/TwilioTextChannel.php",
    "line": null,
    "description": "TwilioTextChannel::send() and parseInbound() throw by design until Phase 2 (TXT-01)",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-11T13:23:26.689Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 3,
    "kind": "unrun-verify",
    "phase": "01",
    "file": "tests/Feature/Filament/ContactResourceTest.php",
    "line": null,
    "description": "Plan 01-06 Task 3 human-check (sign in, browse contacts, reply, close and reopen in a browser) not run; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-11T13:57:20.122Z",
    "resolved_at": "2026-10-11T18:27:32.167Z",
    "milestone": null
  },
  {
    "id": 4,
    "kind": "unrun-verify",
    "phase": "01",
    "file": "tests/Feature/Quality/SeederTest.php",
    "line": null,
    "description": "Plan 01-07 Task 3 human-check (browser walk of /dev/chat, Business Folder Sync Now, cards) not run by the executor; left for end-of-phase UAT. Dana shares the /dev/chat default number, so step 4 shows four contacts, not five",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-11T15:08:41.510Z",
    "resolved_at": "2026-10-11T18:27:32.267Z",
    "milestone": null
  },
  {
    "id": 5,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "docs/sprints/sprint-1.md",
    "line": null,
    "description": "Plan 02-23 D-30: real-phone check of the alert link over Wi-Fi (also covers 02-12 profile phone, remember me, no-phone warning) not run by the executor",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-12T03:40:04.898Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 6,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "tests/Feature/FrontDesk/GateTest.php",
    "line": null,
    "description": "Plan 02-35 Task 1 human-check (UAT Test 2 re-run with live keys: 'My AC is blowing warm air' sends its intake question on its own; gate:export spot-check of promise and complaint scores on other ordinary texts) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-13T14:26:36.938Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 7,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "tests/Feature/FrontDesk/SentTimeTest.php",
    "line": null,
    "description": "Plan 02-36 Task 2 human-check (UAT Test 1 order: send a held draft from the conversation screen and see the holding reply listed first, each with its own time) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-13T14:42:56.426Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 8,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "tests/Feature/DevChatTest.php",
    "line": null,
    "description": "Plan 02-38 Task 1 human-check (/dev/chat after a held draft shows only the customer's text and the holding reply, then the sent reply after Send) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-13T14:50:49.105Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 9,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "tests/Feature/FrontDesk/FrontDeskPromptTest.php",
    "line": null,
    "description": "Plan 02-39 Task 3 human-check (live run after icm:sync: Dana's held draft does not call the visit tomorrow or booked; 'My furnace won't turn on.' from a new number gets a furnace-repair intake question) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-13T14:58:42.997Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 10,
    "kind": "unrun-verify",
    "phase": "02",
    "file": "tests/Feature/Filament/ConversationViewTest.php",
    "line": null,
    "description": "Plan 02-40 Task 1 human-check (UAT Test 1 layout at 390x664 or on a phone: held reason, Customer wrote line, draft, Send and Discard visible without scrolling, with a short thread, a ~14-message thread and a long customer text) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-13T15:03:01.233Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 11,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/Filament/ConversationViewTest.php",
    "line": null,
    "description": "Plan 02.1-10 Task 1 human-check (owner phone walk: open a Needs you card from an alert after the customer texted twice; the wrote-again line shows above Send; on a draft answered by a price sentence, Send shows 'Already answered') not run by the executor; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-14T20:59:32.083Z",
    "resolved_at": "2026-10-18T15:30:23.672Z",
    "milestone": null
  },
  {
    "id": 12,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/FrontDesk/EasyAnswerWhileWaitingTest.php",
    "line": null,
    "description": "Plan 02.1-16 human-check (live model with a provider key, after icm:sync: from /dev/chat ask 'How much is the diagnostic?', then 'How much for a new system?' and answer the size, then while a draft waits ask an unrelated question and then check in; read the stored verdicts and AgentReply fields: price_services, asks_discount, touches_waiting and waiting_note set sensibly) not run by the executor; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-14T22:09:18.451Z",
    "resolved_at": "2026-10-18T15:30:23.770Z",
    "milestone": null
  },
  {
    "id": 13,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "resources/views/livewire/conversation-thread.blade.php",
    "line": null,
    "description": "Ian's UX walk on his phone at UAT (02.1 D-48): walk the Needs you card and the Business Folder Offers section, then decide UI-REVIEW fix 2's styling (card hierarchy, colour, icon) and fix 3 (Offers warning colour, file name, date format), both deferred until this walk. Also: Contacts and Conversations tables at 375 wide, pick the columns to hide; the thread's scroll box inside the page scroll; the hardcoded red (#dc2626) in dark mode",
    "status": "waived",
    "reason": "UAT test 1 (Ian, 2026-10-17): the phone walk was done and the look fails on a phone; recorded as a design issue for a separate phone design pass (.planning/todos/pending/2026-10-17-phone-design-pass.md), not a 02.1 gap",
    "recorded_at": "2026-10-15T02:25:15.746Z",
    "resolved_at": "2026-10-18T15:30:24.235Z",
    "milestone": null
  },
  {
    "id": 14,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/FrontDesk/CustomerReadingTest.php",
    "line": null,
    "description": "Plan 02.1-17 human-check (live JEV_API_KEY: text from /dev/chat 'My head is pounding and the kids feel dizzy since the furnace came on.' and confirm gate.customer_reading shows Jev answered a call with an empty draft_reply and read life_safety above 0.075) not run by the executor; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-16T20:57:36.914Z",
    "resolved_at": "2026-10-18T15:30:23.863Z",
    "milestone": null
  },
  {
    "id": 15,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/FrontDesk/PossibleDangerTest.php",
    "line": null,
    "description": "Plan 02.1-21 human-check (live provider key: text from /dev/chat 'My head is pounding and the kids feel dizzy since the furnace came on.' and confirm the stored AgentReply sets possible_danger true) not run by the executor; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-16T21:46:11.228Z",
    "resolved_at": "2026-10-18T15:30:23.964Z",
    "milestone": null
  },
  {
    "id": 16,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/SendRule/SafetyCheckOutageTest.php",
    "line": null,
    "description": "Plan 02.1-23 human-check (live JEV_API_KEY with php artisan schedule:work running: confirm the health ping succeeds against OpenRouter and no outage row opens; set a wrong key, wait for the next ping, and confirm one safety_check_down text reaches the owner's phone; restore the key and confirm the back-up text) not run by the executor; left for end-of-phase UAT",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-16T22:06:46.795Z",
    "resolved_at": "2026-10-18T15:30:24.053Z",
    "milestone": null
  },
  {
    "id": 17,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "resources/views/livewire/conversation-thread.blade.php",
    "line": null,
    "description": "Plan 02.1-25 human-check (on a phone, a Needs you card after the customer wrote again shows the quoted newest text and 'Send anyway'; tapping Send twice sends once; Discard asks first and Cancel keeps the draft) not run by the executor; left for end-of-phase UAT with entry 13's walk",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-16T22:29:40.293Z",
    "resolved_at": "2026-10-18T15:30:24.142Z",
    "milestone": null
  },
  {
    "id": 18,
    "kind": "stub",
    "phase": "04",
    "file": "resources/views/filament/rooms/section.blade.php",
    "line": 3,
    "description": "Room section view body is empty on purpose; plan 04-06 fills it with the room's line (D-21)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-22T21:54:44.369Z",
    "resolved_at": "2026-10-23T01:36:56.101Z",
    "milestone": "v2.0"
  },
  {
    "id": 19,
    "kind": "unrun-verify",
    "phase": "04",
    "file": "tests/Feature/Rooms/RoomAccessibilityTest.php",
    "line": null,
    "description": "CI run on the pushed phase branch: tests workflow green on both sqlite and pgsql legs, including PretendRoomBootTest and RoomAccessibilityTest (plan 04-09 human-check, for end-of-phase UAT)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-23T01:44:47.162Z",
    "resolved_at": "2026-10-26T22:56:08.734Z",
    "milestone": "v2.0"
  },
  {
    "id": 20,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php",
    "line": null,
    "description": "Plan 05-03 hand check (D-18, carried from Phase 4): keyboard-only and VoiceOver walk of the company knowledge section on /admin, the first real room section; not run by the executor, left for Phase 5 UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-25T23:24:32.493Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 21,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "app/Rooms/Marketing/MarketingSection.php",
    "line": null,
    "description": "Plan 05-05 hand check (D-18): keyboard-only and VoiceOver walk of the marketing section on /admin, and read the offers line against the front desk on the day an offer ends; not run by the executor, left for Phase 5 UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-25T23:41:24.145Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 22,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "app/Rooms/ContentGeneration/ContentGenerationSection.php",
    "line": null,
    "description": "Plan 05-06 hand check (D-18): keyboard-only and VoiceOver walk of the content generation section on /admin, empty and with the brief in place; not run by the executor, left for Phase 5 UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-25T23:55:41.982Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 23,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "app/Rooms/Money/MoneySection.php",
    "line": null,
    "description": "Plan 05-07 hand check (D-18): keyboard-only and VoiceOver walk of the money section on /admin, empty and with the notes in place; not run by the executor, left for Phase 5 UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-26T00:08:17.569Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 24,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php",
    "line": null,
    "description": "Plan 05-08 hand check (D-18): keyboard-only and VoiceOver walk of the Google Business Profile section on /admin, empty and with the categories file in place; not run by the executor, left for Phase 5 UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-26T00:21:53.598Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 25,
    "kind": "unrun-verify",
    "phase": "quick-261026-jig",
    "file": ".github/workflows/tests.yml",
    "line": null,
    "description": "CI human check after push: both legs pass on Ubuntu 24.04; first run cache miss then re-run hit (install-deps only, no Chromium download); no step exceeds its timeout",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-26T19:14:54.022Z",
    "resolved_at": "2026-10-26T22:56:08.825Z",
    "milestone": "v2.0"
  },
  {
    "id": 26,
    "kind": "unrun-verify",
    "phase": "quick-261026-lrs",
    "file": ".github/workflows/tests.yml",
    "line": null,
    "description": "After Ian pushes gsd/quick-ci-runner: both CI legs pass; the browser step shows no package-manager output and finishes in seconds on a cache hit; no 'Host system is missing dependencies' message; if libraries are named, list them for Ian and do not add apt back. Replaces check (4) of row 25 (quick-261026-jig).",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-26T20:50:09.012Z",
    "resolved_at": "2026-10-26T22:56:08.916Z",
    "milestone": "v2.0"
  },
  {
    "id": 27,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/Rooms/HandBuiltRegistrySyncTest.php",
    "line": null,
    "description": "Postgres: after Ian pushes the phase branch, the CI pgsql leg runs HandBuiltRegistrySyncTest and the rest of the suite green (06-01 hand check)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-27T21:48:43.201Z",
    "resolved_at": "2026-10-28T12:17:21.837Z",
    "milestone": "v2.0"
  },
  {
    "id": 28,
    "kind": "unrun-verify",
    "phase": "06",
    "file": ".github/workflows/tests.yml",
    "line": null,
    "description": "Postgres: after Ian pushes the phase branch, the CI pgsql leg runs FrontDeskBootTest and RoomClaimedWidgetsTest and the rest of the suite green (06-02 hand check)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-27T22:28:22.535Z",
    "resolved_at": "2026-10-28T12:17:21.932Z",
    "milestone": "v2.0"
  },
  {
    "id": 29,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/Rooms/TeamBootTest.php",
    "line": null,
    "description": "06-03 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg is green with both room wraps registered and no ALRT change yet (local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-28T00:36:39.046Z",
    "resolved_at": "2026-10-28T12:17:22.024Z",
    "milestone": "v2.0"
  },
  {
    "id": 30,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/Alerts/AlertSentOnceTest.php",
    "line": null,
    "description": "06-04 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/Alerts/AlertSentOnceTest.php green (the conditional update's affected-row count and the frozen-clock 119/120 second comparison on Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/Alerts)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-28T13:44:35.628Z",
    "resolved_at": "2026-10-28T18:14:47.414Z",
    "milestone": "v2.0"
  },
  {
    "id": 31,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/Alerts/AlertDedupeKeyTest.php",
    "line": null,
    "description": "06-05 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/Alerts/AlertDedupeKeyTest.php green (the refused duplicate inside the test's transaction is survived through createOrFirst's savepoint, and many NULL keys sit under the unique index; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/Alerts)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-28T15:46:45.038Z",
    "resolved_at": "2026-10-28T18:14:47.506Z",
    "milestone": "v2.0"
  },
  {
    "id": 32,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/FrontDesk/ClaimTokenTest.php",
    "line": null,
    "description": "06-06 hand check, left unrun for UAT: after the phase branch is pushed, the CI pgsql leg runs tests/Feature/FrontDesk/ClaimTokenTest.php and FrontDeskFailureTest.php green (the claim_token column, its (conversation_id, claim_token) index and the token-matched failed() lookup on Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact tests/Feature/FrontDesk/ClaimTokenTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-28T16:14:33.573Z",
    "resolved_at": "2026-10-28T18:14:47.601Z",
    "milestone": "v2.0"
  },
  {
    "id": 33,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "tests/Feature/FrontDesk/FailureReportOnceTest.php",
    "line": null,
    "description": "06-07 hand check, left unrun for UAT and the phase gate before /gsd-verify-work: after the phase branch is pushed, the CI tests workflow is green on both legs for the whole phase, tests/Feature/FrontDesk/FailureReportOnceTest.php included (the conditional update on messages.failure_reported_at is atomic on SQLite and Postgres 17; local option: docker postgres:17 on port 55432, DB_CONNECTION=pgsql, php artisan test --compact)",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-10-28T16:36:32.260Z",
    "resolved_at": "2026-10-28T18:14:47.697Z",
    "milestone": "v2.0"
  },
  {
    "id": 34,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-01: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectWordTest and ProjectWordSpecTest included (criterion 1 on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T12:37:24.851Z",
    "resolved_at": "2026-11-01T17:50:58.522Z",
    "milestone": "v2.0"
  },
  {
    "id": 35,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-02: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectRecordTest included, in particular 'refuses a second project opened by a new lead for one contact' (the partial unique index projects_one_lead_project_per_contact on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T15:45:59.200Z",
    "resolved_at": "2026-11-01T17:51:08.314Z",
    "milestone": "v2.0"
  },
  {
    "id": 36,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-03: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectCardTest and CardInjectionTest included (the card's project section and the quoted project name on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T17:30:20.550Z",
    "resolved_at": "2026-11-01T17:51:08.413Z",
    "milestone": "v2.0"
  },
  {
    "id": 37,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-04: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectActionsTest and ProjectWritesTest included (close, reopen and rename under a row lock, and the write-confinement scan, on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T18:55:35.664Z",
    "resolved_at": "2026-11-01T17:51:08.505Z",
    "milestone": "v2.0"
  },
  {
    "id": 38,
    "kind": "unrun-verify",
    "phase": "11",
    "file": "app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php",
    "line": null,
    "description": "07-05: UAT keyboard-only and VoiceOver walk of a contact screen with two projects: the list is reachable, New, Close, Reopen and Rename work without a mouse, the New and Close confirmations and the rename form take focus and return it, and each button has an accessible name (CLAUDE.md WCAG 2.2 AA; New confirmation added by review WR-03)",
    "status": "open",
    "reason": "Moved to Phase 11 (Ian, 2026-11-01): the live VoiceOver listen joins the Phase 11 owner-screen pass (A11Y-03), as Phase 5 did; the keyboard, focus and button-name checks are proven by the browser tests in 08eeec0 and a9497bc, green in CI",
    "recorded_at": "2026-10-30T20:18:50.026Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 39,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-05: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with ProjectsRelationManagerTest included (the contact screen's project list and its New, Close, Reopen and Rename actions on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T20:18:50.137Z",
    "resolved_at": "2026-11-01T17:51:08.604Z",
    "milestone": "v2.0"
  },
  {
    "id": 40,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-06: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with LeadsBootTest, FrontDeskBootTest and RoomWidgetOwnershipTest included (ROOM-05 on Postgres for the leads room)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T22:36:27.019Z",
    "resolved_at": "2026-11-01T17:51:08.708Z",
    "milestone": "v2.0"
  },
  {
    "id": 41,
    "kind": "unrun-verify",
    "phase": "07",
    "file": "resources/views/filament/rooms/leads-section.blade.php",
    "line": null,
    "description": "07-07: UAT keyboard-only and VoiceOver walk of /admin with three open projects: each row's contact link is reached by Tab, announced with the contact's name, opens the contact screen, and the opened date is read as a date (D-15, CLAUDE.md WCAG 2.2 AA)",
    "status": "fixed",
    "reason": "Evidence: Phase 7 UAT test 3, passed 2026-10-31 (07-UAT.md)",
    "recorded_at": "2026-10-30T23:42:17.829Z",
    "resolved_at": "2026-11-01T18:26:57.546Z",
    "milestone": "v2.0"
  },
  {
    "id": 42,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-07: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green with LeadsBootTest (axe included) and LeadsSectionTest included (the leads section on Postgres)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-30T23:42:17.942Z",
    "resolved_at": "2026-11-01T17:51:08.804Z",
    "milestone": "v2.0"
  },
  {
    "id": 43,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-08: after Ian pushes the phase branch, the CI tests workflow's pgsql leg is green for the whole suite, in particular OpenOnNewLeadTest's 'loses a race without an error and leaves one project' (the savepoint and the partial index on Postgres) and the three changed test lines (criterion 4 on both databases)",
    "status": "fixed",
    "reason": "Evidence: CI run 41226007469 (Ian, 2026-11-01)",
    "recorded_at": "2026-10-31T15:09:06.795Z",
    "resolved_at": "2026-11-01T17:51:08.895Z",
    "milestone": "v2.0"
  },
  {
    "id": 44,
    "kind": "unrun-verify",
    "phase": "11",
    "file": "app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php",
    "line": null,
    "description": "07-09: UAT retest of WINDOWS entry 38 with VoiceOver in Safari and by keyboard, contact with two projects: Tab from the top reaches the list; Close opens a dialog announced as Close Job 1?, and confirming it leaves focus on the row's button, read as Reopen Job 1, button, and the Closed notification is announced; Reopen opens a dialog announced as Reopen Job 1?, and confirming it leaves focus on Close Job 1; every row button is announced with its project (Close Johnny, Rename Job 2); New and Rename still take and return focus; the row buttons look and space as before (gaps G-07-2.1 to G-07-2.3)",
    "status": "open",
    "reason": "Moved to Phase 11 (Ian, 2026-11-01): the live VoiceOver listen joins the Phase 11 owner-screen pass (A11Y-03), as Phase 5 did; the keyboard, focus and button-name checks are proven by the browser tests in 08eeec0 and a9497bc, green in CI",
    "recorded_at": "2026-11-01T15:36:26.960Z",
    "resolved_at": null,
    "milestone": "v2.0"
  },
  {
    "id": 45,
    "kind": "unrun-verify",
    "phase": "07",
    "file": ".github/workflows",
    "line": null,
    "description": "07-09: after Ian pushes the phase branch, the CI tests workflow is green on both legs with ProjectsRelationManagerTest and ProjectsRelationManagerKeyboardTest included (the focus proof in Chromium on SQLite and Postgres)",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-11-01T15:36:34.622Z",
    "resolved_at": null,
    "milestone": "v2.0"
  }
]
````
## Phase 02.1 UAT outcomes (entries 11 to 17)

- 11: UAT test 1 (Ian, 2026-10-17): behavior pass on conversations 23 and 24; "Already answered" skipped as a timing trick, covered by AnsweredDraftTest and DraftReviewTest; the look goes to the phone design pass (see entry 13).
- 12: UAT test 2 (Ian, 2026-10-17): issue G-02.1-2 from the 44-call probe (fallback model named system-replacement for a mini split and deferred 9a to the owner). Fixed by one price_services rule in quick task 261018-dxy; its 8-call live recheck on 2026-10-18 passed: price_services [] on all 8 calls, and all four 9a replies asked AC or furnace.
- 13: waived. UAT test 1: the phone walk was done and the look fails on a phone; recorded as a design issue for a separate phone design pass (.planning/todos/pending/2026-10-17-phone-design-pass.md), not a 02.1 gap.
- 14: UAT test 3 (Ian, 2026-10-17): pass by Ian's ruling. Jev read 0.05 on text A, under the 0.075 bar, and the reply model's possible_danger sent the line. Jev tuning for CO symptoms is held in docs/phase-2.2-owner-input.md section 7, due before the first client goes live. The pausing reading is now stored on the safety line (G-02.1-3, quick task 261018-dxy).
- 15: UAT test 3 (Ian, 2026-10-17): pass; possible_danger true on all 8 reply-model runs.
- 16: UAT test 4 (Ian, 2026-10-18): pass. Phone delivery was not tested (Twilio trial, A2P), deferred to Twilio go-live.
- 17: UAT test 1 (Ian, 2026-10-17): behavior pass; the look goes to the phone design pass (see entry 13).
