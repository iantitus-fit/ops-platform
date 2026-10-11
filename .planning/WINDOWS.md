---
schema_version: 1
open_count: 3
waived_count: 0
fixed_count: 0
total_count: 3
last_updated: 2026-10-11T13:57:20.122Z
---

# Broken Windows Ledger

> Cross-phase defect register. With `workflow.windows_enforce` enabled, `/gsd-ship` blocks while `open_count > 0`.
> Waive with `gsd-tools windows waive <id> "<reason>"` (reason required).
> Mark fixed with `gsd-tools windows fixed <id>`.

| id | phase | kind | file | line | description | status | reason | recorded_at | resolved_at |
|----|-------|------|------|------|-------------|--------|--------|-------------|-------------|
| 1 | 01 | stub | app/Jobs/ProcessInbound.php | 25 | ProcessInbound::handle() is empty by design in Phase 1; Sprint 1 front-desk agent runs here (AGNT-02) | open |  | 2026-10-11T13:23:26.587Z |  |
| 2 | 01 | stub | app/Channels/TwilioTextChannel.php |  | TwilioTextChannel::send() and parseInbound() throw by design until Phase 2 (TXT-01) | open |  | 2026-10-11T13:23:26.689Z |  |
| 3 | 01 | unrun-verify | tests/Feature/Filament/ContactResourceTest.php |  | Plan 01-06 Task 3 human-check (sign in, browse contacts, reply, close and reopen in a browser) not run; left for end-of-phase UAT | open |  | 2026-10-11T13:57:20.122Z |  |

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
    "status": "open",
    "reason": "",
    "recorded_at": "2026-10-11T13:57:20.122Z",
    "resolved_at": null,
    "milestone": null
  }
]
````
