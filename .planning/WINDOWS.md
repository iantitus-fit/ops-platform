---
schema_version: 1
open_count: 4
waived_count: 0
fixed_count: 2
total_count: 6
last_updated: 2026-10-13T14:26:36.938Z
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
  }
]
````
