---
schema_version: 1
open_count: 11
waived_count: 0
fixed_count: 2
total_count: 13
last_updated: 2026-10-15T02:25:15.746Z
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
| 11 | 02.1 | unrun-verify | tests/Feature/Filament/ConversationViewTest.php |  | Plan 02.1-10 Task 1 human-check (owner phone walk: open a Needs you card from an alert after the customer texted twice; the wrote-again line shows above Send; on a draft answered by a price sentence, Send shows 'Already answered') not run by the executor; left for end-of-phase UAT | open |  | 2026-10-14T20:59:32.083Z |  |
| 12 | 02.1 | unrun-verify | tests/Feature/FrontDesk/EasyAnswerWhileWaitingTest.php |  | Plan 02.1-16 human-check (live model with a provider key, after icm:sync: from /dev/chat ask 'How much is the diagnostic?', then 'How much for a new system?' and answer the size, then while a draft waits ask an unrelated question and then check in; read the stored verdicts and AgentReply fields: price_services, asks_discount, touches_waiting and waiting_note set sensibly) not run by the executor; left for end-of-phase UAT | open |  | 2026-10-14T22:09:18.451Z |  |
| 13 | 02.1 | unrun-verify | resources/views/livewire/conversation-thread.blade.php |  | Ian's UX walk on his phone at UAT (02.1 D-48): walk the Needs you card and the Business Folder Offers section, then decide UI-REVIEW fix 2's styling (card hierarchy, colour, icon) and fix 3 (Offers warning colour, file name, date format), both deferred until this walk | open |  | 2026-10-15T02:25:15.746Z |  |

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
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-14T20:59:32.083Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 12,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "tests/Feature/FrontDesk/EasyAnswerWhileWaitingTest.php",
    "line": null,
    "description": "Plan 02.1-16 human-check (live model with a provider key, after icm:sync: from /dev/chat ask 'How much is the diagnostic?', then 'How much for a new system?' and answer the size, then while a draft waits ask an unrelated question and then check in; read the stored verdicts and AgentReply fields: price_services, asks_discount, touches_waiting and waiting_note set sensibly) not run by the executor; left for end-of-phase UAT",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-14T22:09:18.451Z",
    "resolved_at": null,
    "milestone": null
  },
  {
    "id": 13,
    "kind": "unrun-verify",
    "phase": "02.1",
    "file": "resources/views/livewire/conversation-thread.blade.php",
    "line": null,
    "description": "Ian's UX walk on his phone at UAT (02.1 D-48): walk the Needs you card and the Business Folder Offers section, then decide UI-REVIEW fix 2's styling (card hierarchy, colour, icon) and fix 3 (Offers warning colour, file name, date format), both deferred until this walk",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-15T02:25:15.746Z",
    "resolved_at": null,
    "milestone": null
  }
]
````
