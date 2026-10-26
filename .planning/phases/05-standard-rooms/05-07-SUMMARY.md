---
phase: 05-standard-rooms
plan: 07
subsystem: rooms
tags: [rooms, money, money-notes, account-number-scan, luhn, libphonenumber, icm-sync, filament, axe, pest, tdd]

requires:
  - phase: 04-room-standard
    provides: RoomFileType, RoomRegistry, RoomChecks ownership and placement checks, RoomSectionAxe
  - phase: 05-standard-rooms
    provides: 05-06 singleFileLabel() and the D-27 single-file check; 05-03 and 05-05 real-room shape (provider, section, contract-only folder, boot test)
provides:
  - the money room (key money), registered after content generation, optional, with the room's own money_notes file type and no slot, table or record
  - AccountNumberScan, the maximal-span scan with the 9-digit rule, the phone skip and the card check digit
  - a money notes check that refuses card numbers and every 9-digit number however grouped (ZIP+4 60601-1234 excepted) in one fixed digit-free line
  - an example folder rooms/money/ holding the contract and one fictional notes file with no digits
  - a dashboard section showing "Payment notes: in place" or the platform's empty line naming rooms/money/notes.md
  - SPEC rows: ownership for rooms/money/notes.md and the money_notes file type
  - exampleFolderFileCount() returning 20
affects: [05-08, 05-09, finance, safety-and-compliance, knowledge]

plan_head_before: 7a588ee381aaef067a387eee118b32c4e90ff047
plan_head_after: c801df0445a65a7ead10ceeeed948f605227f87a

actuals:
  tokens: 14000
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A free-text check scans the whole raw file for maximal spans and judges each span whole, never in part"
    - "A refusal that could otherwise quote a secret is a fixed sentence with no digit, and a test asserts the line and changelog.md hold none of it"

key-files:
  created:
    - app/Rooms/Money/MoneyNotesType.php
    - app/Rooms/Money/MoneySection.php
    - app/Rooms/Money/MoneyServiceProvider.php
    - app/Rooms/Money/AccountNumberScan.php
    - examples/example-hvac/rooms/money/CONTEXT.md
    - examples/example-hvac/rooms/money/notes.md
    - tests/Feature/Rooms/MoneyBootTest.php
    - tests/Feature/Rooms/MoneyNotesCheckTest.php
  modified:
    - bootstrap/providers.php
    - tests/Pest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.1.md
    - .planning/WINDOWS.md

key-decisions:
  - "The money room has no slot, table, timeline entry, morph map or payment record (ROOM-14, D-05); a boot-test case asserts the room registers none and no migration mentions payment or money"
  - "The edited-contract second-file case uses rooms/money/second.md, not more.md, because the D-27 check keeps the first claimed file in path order and more.md sorts before notes.md"
  - "The scan runs on the raw file, so a number in front matter is caught like one in the body"

patterns-established:
  - "Pest file-unique helper and constant names (writeMoneyNotes, MONEY_NOTES_REFUSAL)"

requirements-completed: [ROOM-14]

coverage:
  - id: D1
    description: "The money room is registered with its money_notes type and boots from icm:sync to /admin with the example notes, section 'Payment notes: in place', a contract equal to SPEC's row, no slot, table or timeline entry, and exampleFolderFileCount() 20"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyBootTest.php#boots the money room with data set with its example folder"
        status: pass
    human_judgment: false
  - id: D2
    description: "The room boots empty (notes removed) with exactly 'Money: nothing set up yet. Add rooms/money/notes.md to start.' and passes axe at WCAG 2.2 Level AA in both rows, on SQLite with no driver guard"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyBootTest.php#boots the money room with data set empty"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Rooms/MoneyBootTest.php (RoomSectionAxe::assertClean in both rows)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A second money notes file is refused in one line naming both files under the shipped contract and under a contract edited to own two paths, keeping the previous version live; two owned paths with one notes file present activates"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyBootTest.php (3 D-27 cases)"
        status: pass
    human_judgment: false
  - id: D4
    description: "The room adds no table, timeline entry, morph map or payment record, and no migration mentions payment or money"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyBootTest.php#adds no payment table or record"
        status: pass
    human_judgment: false
  - id: D5
    description: "The notes check refuses 19 card and 9-digit rows (including 123-456-789, 113108819, 60601 1234 and +123456789) and lets 12 phone, ZIP+4, date, short and over-long rows through, in one fixed digit-free line with the previous version live"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyNotesCheckTest.php (refused dataset, passes dataset)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Adjacency, empty, ordering and scope: a comma keeps spans apart, an empty or digit-free body passes, several refused numbers give one line with no digit and changelog.md holds none of them, a number in front matter is caught, the same number in business/faq.md passes"
    requirement: ROOM-14
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MoneyNotesCheckTest.php (comma, empty body, several numbers and changelog, front matter, only the money notes file)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The money section's wording and a keyboard-only and screen reader walk read plainly and work"
    requirement: ROOM-14
    verification: []
    human_judgment: true
    rationale: "Automated axe covers part of WCAG; the keyboard and VoiceOver walk is a hand check recorded for Phase 5 UAT (WINDOWS entry 23), and the wording is a placeholder until the wording pass"

duration: 25min
completed: 2026-10-26
status: complete
---

# Phase 5 Plan 07: Money room Summary

**The money room lands at the full standard with one plain-words notes file and no slot, table or record, and, tests first, an AccountNumberScan check that refuses card numbers and every 9-digit number however grouped (routing, Social Security, employer ID) in one fixed line that never repeats a digit, while phones and a ZIP+4 written 60601-1234 pass.**

## Performance

- **Duration:** 25 min
- **Tasks:** 2 (Task 1 a tracer with tdd, Task 2 tdd)
- **Files:** 8 created, 5 changed

## Accomplishments

- `MoneyServiceProvider` returns `Room(key: 'money', label: 'money', fileTypes: [MoneyNotesType], sections: [MoneySection])`, listed after content generation in `bootstrap/providers.php`. No slot, tables, timeline entries or morph map (ROOM-14, D-05). The room stays optional.
- `MoneyNotesType`: type `money_notes`, front matter only its type, `singleFileLabel()` returns `'money notes file'`, and `checks()` runs the number scan on the whole raw file (front matter included).
- `AccountNumberScan::findsAccountNumber()`: finds maximal spans (optional `+`, digits, single-space or single-dash groups), judges each whole. Exactly nine digits with no `+` is refused however grouped unless it is `ddddd-dddd`; otherwise a span `PhoneNumber::toE164()` accepts is skipped (whole span, `+` included); then nine digits with a `+` is refused, and 13 to 19 digits passing a hand-written mod-10 check digit is refused. No package added.
- Refusal line: `rooms/money/notes.md: this file has what looks like a card, bank routing, Social Security or tax ID number; account, card and ID numbers never go in the folder`. Fixed sentence, no digit, previous version stays live.
- Example folder: `rooms/money/CONTEXT.md` (owns `[rooms/money/notes.md]`, reads `business/profile.md`, writes nothing, connector none, ends `It has no outside system.`) and `notes.md` (fictional, no digit). `exampleFolderFileCount()` is 20.
- SPEC: ownership row for `rooms/money/notes.md` (owner money) and the `money_notes` row in the file types table, including the one-notes-file rule.

## Task Commits

1. **Task 1 RED: the money room boots empty and with its notes** - `e33cc85` (test)
2. **Task 1 GREEN: the money room joins the example business** - `23a14a6` (feat)
3. **Task 2 RED: the money notes refuse account, card and ID numbers** - `5ede028` (test)
4. **Task 2 GREEN: keep account, card and ID numbers out of the money notes** - `c801df0` (feat)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **Task 1 RED:** `e33cc85`. Target tests failed on the planned behavior: the furnished row on `hasRoom('money')` (`Failed asserting that false is true.`), the empty row on the file count (`18 is identical to 17`, because the notes file did not exist yet), the shipped-contract second-file case with the type and unknown-room refusals instead of the single-file line, and the three remaining cases on a missing contract file or room. Semantic assessment: all fail because the room is not on yet, with no syntax, fixture or load fault. `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported formats (same as 05-02, 05-03 and 05-06); RED judged by hand from the runner output.
- **Task 1 GREEN:** `23a14a6`; MoneyBootTest and SpecTablesTest (34 tests) pass, then the full suite (2080). Tracer gate: `<verify>` was automated only, so under end-of-phase mode it was re-run end to end and expansion continued.
- **Task 2 RED:** `5ede028`. 22 of 37 target tests failed on the planned behavior (`Expected status code 1 but received 0`): all 19 refused rows, the comma row, the several-numbers-and-changelog row and the front-matter row. The 15 pass rows passed in RED by design (they pin behavior that must keep working).
- **Task 2 GREEN:** `c801df0`; MoneyNotesCheckTest and MoneyBootTest (43 tests) pass, then the full suite (2116).
- **REFACTOR:** none needed.

## Decisions Made

- The edited-contract second-file case writes `rooms/money/second.md`, not the plan's `rooms/money/more.md` (see Deviations).
- The scan reads `$file->raw`, as the plan says, so front matter is covered.

## Deviations from Plan

**1. [Rule 1 - Plan defect] Second-file path in the edited-contract cases**
- **Found during:** Task 1 GREEN
- **Issue:** The plan has the contract edited to own `[rooms/money/notes.md, rooms/money/more.md]` and expects the line `rooms/money/more.md: ... in rooms/money/notes.md`. The D-27 check 05-06 built keeps the first file a present contract claims in path order, and `more.md` sorts before `notes.md`, so it kept `more.md` and refused `notes.md`. The shipped-contract case, where the contract claims only `notes.md`, matches the plan.
- **Fix:** The two edited-contract cases (refused, and accepted with one file present) use `rooms/money/second.md`, which sorts after `notes.md`; a docblock in the test says why. The shipped-contract case keeps `more.md`, so the plan's exact-line acceptance grep still passes. The 05-06 check was not changed.
- **Files modified:** tests/Feature/Rooms/MoneyBootTest.php
- **Commit:** `23a14a6`

**Total deviations:** 1 auto-fixed (plan defect in a test fixture name). **Impact:** none on behavior or scope.

## Issues Encountered

None. Final full suite: 2116 passed, 16620 assertions.

## Verification

- Task 1 verify (34 tests), Task 2 verify (43 tests, then the full suite, 2116) pass.
- Acceptance greps pass: provider line, `return 20;`, `return 'money notes file';`, the exact `more.md` line in MoneyBootTest, zero digits in notes.md and CONTEXT.md, `It has no outside system.` and `business/faq.md` in the contract, SPEC rows (`rooms/money/notes.md` | money, `| money_notes |`, `refuses a second money notes file`), `PhoneNumber::toE164` in the scan, zero `use Faker`, `never go in the folder` in the type, every required row in MoneyNotesCheckTest, no composer change, no migration diff against main.
- Postgres: no local Postgres. The boot test has no driver guard, so CI's pgsql leg runs it when Ian pushes the branch.

## Hand checks for UAT

- **Keyboard-only and VoiceOver walk of the money section on /admin** (D-18), empty and with the notes in place. Not run by the executor. Recorded as an `unrun-verify` entry (id 23) in `.planning/WINDOWS.md`. Nothing was paused for it.
- Wording of the section lines and the refusal is a placeholder until the wording pass.

## Known Stubs

None.

## Threat Flags

None. T-05-13, T-05-14 and T-05-21 are mitigated and covered by tests; T-05-15 (account numbers have no shape) is accepted and warned about in the room's CONTEXT.md.

## Walk-through log

One line added to `docs/sprints/sprint-1.1.md` marked `(plan 05-07)`: finding numbers in free text with a regex of maximal spans, the card check digit, and a refusal that never quotes what it found.

## Next Phase Readiness

Ready for 05-08. Google Business Profile reuses `singleFileLabel()` for its own type. An `owns` pattern starting with `*` must be quoted in YAML (05-02 finding). When a test edits a contract to own a second path of a single-file type, name the second file so it sorts after the kept file (this plan's finding).

## Self-Check: PASSED

- Created files exist: provider, section, type, scan, contract, notes, both tests.
- Commits `e33cc85`, `23a14a6`, `5ede028`, `c801df0` are ancestors of HEAD.
- Full suite passes (2116).

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-26*
