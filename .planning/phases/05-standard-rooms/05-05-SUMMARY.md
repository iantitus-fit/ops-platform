---
phase: 05-standard-rooms
plan: 05
subsystem: rooms
tags: [rooms, marketing, never-left-out, icm-sync, filament, axe, pest, tdd]

requires:
  - phase: 05-standard-rooms
    provides: 05-02 Room::neverLeftOutBecause and the one-line refusal; 05-03 the company knowledge provider and boot-test shape; 05-04 hidden-character refusals
  - phase: 02-front-desk
    provides: App\SendRule\OfferSentences::liveToday(), the front desk's own rule for which offers are live
provides:
  - the marketing room, registered in bootstrap/providers.php after company knowledge (key marketing, label marketing, handles lead_sources and offer)
  - a contract-only example folder rooms/marketing/CONTEXT.md owning business/lead-sources.md and business/offers/*.md in place
  - a dashboard section showing "Lead sources: <n>" and "Offers live today: <names>" or "none", decided only by OfferSentences
  - company knowledge and marketing marked as never left out, with the one-line refusal proven through icm:sync on the real example folder
  - exampleFolderFileCount() returning 16
affects: [05-06, 05-07, 05-08, 05-09, marketing, knowledge]

plan_head_before: 16f0384fd9ef94fc2a9909811b4ce664e4b9991d
plan_head_after: baca14b83a97d61616a390c099854e4ec36eae46

actuals:
  tokens: 6300
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "A section that shows a fact another part of the app already decides asks that code (OfferSentences::liveToday()) and carries no date logic of its own, so the dashboard and the front desk cannot disagree"
    - "The never-left-out mark is one trailing named argument on the room's Room, so turning D-02 on for a room is one line in its provider"
    - "A real-room refusal test edits the shipped contract with breakFile() rather than writing a second contract"

key-files:
  created:
    - app/Rooms/Marketing/MarketingServiceProvider.php
    - app/Rooms/Marketing/MarketingSection.php
    - examples/example-hvac/rooms/marketing/CONTEXT.md
    - tests/Feature/Rooms/MarketingBootTest.php
    - tests/Feature/Rooms/NeverLeftOutTest.php
  modified:
    - bootstrap/providers.php
    - tests/Pest.php
    - app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php
    - .planning/WINDOWS.md

key-decisions:
  - "The marketing section builds its offers line from ActiveConfig::offer($slug)['name'] for each slug OfferSentences::liveToday() returns; it has no start_date, end_date or isLive reference (grep count 0)"
  - "The marketing contract reads business/profile.md because the profile's time zone decides the day an offer is live"
  - "The never-left-out reasons stay the plan's placeholder wording until the 2026-10-17 wording pass"

patterns-established:
  - "Pest test-file helpers keep file-unique names (marketingAt, neverLeftOutRefusal) because Pest functions and constants are global"

requirements-completed: [ROOM-09, ROOM-08]

coverage:
  - id: D1
    description: "Marketing is registered and its contract-only folder turns it on from icm:sync to /admin, with the section showing Lead sources: 4 and Offers live today: First visit promotion"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MarketingBootTest.php#boots the marketing room with data set with its example folder"
        status: pass
    human_judgment: false
  - id: D2
    description: "The room boots empty (the one offer removed, lead sources kept) as Lead sources: 4 and Offers live today: none, and passes axe at WCAG 2.2 Level AA in both rows on SQLite with no driver guard"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MarketingBootTest.php#boots the marketing room with data set empty"
        status: pass
      - kind: automated_ui
        ref: "tests/Feature/Rooms/MarketingBootTest.php (RoomSectionAxe::assertClean in both rows)"
        status: pass
    human_judgment: false
  - id: D3
    description: "The offers line names exactly the offers the front desk would send at four moments in America/Chicago: before the start date, inside the window, at 11:30 pm on the end date, and after the end date"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MarketingBootTest.php#names the offers the front desk would send at each moment"
        status: pass
    human_judgment: false
  - id: D4
    description: "A marketing contract claiming business/faq.md is refused naming the company knowledge room, and the previous version stays live"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/MarketingBootTest.php#refuses a marketing contract that claims the FAQ and names the company knowledge room"
        status: pass
    human_judgment: false
  - id: D5
    description: "Leaving out company knowledge, marketing or both gives exactly the named one-line refusals through icm:sync, no line tells the owner to remove a file, a second sync gives the same lines, and a stray file with no contract gives only the missing-contract line"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/NeverLeftOutTest.php (5 cases)"
        status: pass
      - kind: unit
        ref: "tests/Unit/Icm/NeverLeftOutTest.php (7 cases)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Lead sources, offers and prices behave exactly as before: the offer, lead-source and price suites pass and are unchanged apart from the one D-24 count line in OfferValidationTest changed in 05-03"
    requirement: ROOM-09
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/OfferValidationTest.php, FrontDesk/OfferSentenceTest.php, Inbound/LeadFormTest.php, FrontDesk/PriceSentenceTest.php, KeyedPriceSentenceTest.php, PriceLeadTest.php, Icm/PriceSentenceValidationTest.php, Icm/ActiveConfigTest.php (176 tests)"
        status: pass
    human_judgment: false
  - id: D7
    description: "The marketing section's wording and a keyboard-only and screen reader walk read plainly and work"
    requirement: ROOM-09
    verification: []
    human_judgment: true
    rationale: "Automated axe covers part of WCAG; the keyboard and VoiceOver walk is a hand check recorded for Phase 5 UAT (WINDOWS entry 21), and the wording is a placeholder until the wording pass"

duration: 6min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 05: Marketing room and never-left-out Summary

**Marketing is the second real room: a contract-only folder owning the lead sources and offers where they already are, a section whose "Offers live today" line asks the front desk's own `OfferSentences::liveToday()`, and company knowledge plus marketing marked so leaving either out is refused in one named line.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-25T23:37:24Z
- **Completed:** 2026-10-25T23:43:34Z
- **Tasks:** 2 (Task 1 a tracer with tdd)
- **Files modified:** 9 (5 created, 4 modified)

## Accomplishments

- `MarketingServiceProvider` returns `Room(key: 'marketing', label: 'marketing', handles: lead_sources, offer, sections: [MarketingSection])`. No slot, tables, timeline entries or morph map (D-05); the web lead form is not a slot.
- `MarketingSection` shows `Lead sources: <n>` and `Offers live today: <names>` / `none`. The names come from `ActiveConfig::offer($slug)['name']` for each slug `OfferSentences::liveToday()` returns, so the section has no date logic of its own (D-17, T-05-09). The four-moment dataset covers 2026-09-15, 2026-11-02, 2026-12-31 23:30 and 2027-01-05 in America/Chicago.
- `examples/example-hvac/rooms/marketing/CONTEXT.md` owns `[business/lead-sources.md, business/offers/*.md]` (equal to SPEC's marketing rows, checked by `SpecTables::patternsOwnedBy`), reads the profile, writes nothing to records, and says `It has no outside system.` Nothing under `examples/example-hvac/business` moved.
- One line in `bootstrap/providers.php` turns the room on; `exampleFolderFileCount()` is 16.
- `neverLeftOutBecause` added to both real rooms: deleting `rooms/company_knowledge/`, `rooms/marketing/` or both gives exactly the named lines from the plan, company knowledge first, with the previous version staying live (T-05-10). A stray file with no contract gives only the missing-contract line.

## Task Commits

1. **Task 1 RED: marketing boot, agreement and refusal tests** - `2113734` (test)
2. **Task 1 GREEN: marketing joins the example business** - `0c08e50` (feat)
3. **Task 2 RED: never-left-out through icm:sync** - `2840f92` (test)
4. **Task 2 GREEN: mark company knowledge and marketing as never left out** - `baca14b` (feat)

**Plan metadata:** committed with this SUMMARY (docs).

## TDD Gate Compliance

- **RED (Task 1):** `2113734`. All 7 targeted cases failed: the boot rows on `hasRoom('marketing')` ("Failed asserting that false is true", line 48), the four-moment rows on the missing `Offers live today` line in the dashboard HTML, and the refusal case because `rooms/marketing/CONTEXT.md` did not exist yet. Semantic assessment: each failed on the planned behavior (the room not being on), with no syntax, load or fixture fault. `gsd_run check tdd-red-evidence` was not run because Pest's compact JSON is not one of its supported report formats (same as 05-02 and 05-03); RED judged by hand from the runner output.
- **GREEN (Task 1):** `0c08e50`; MarketingBootTest, CompanyKnowledgeBootTest and SpecTablesTest pass (38 tests). Tracer gate: `<verify>` was automated only, so under end-of-phase mode it was re-run end to end, passed, and expansion continued.
- **RED (Task 2):** `2840f92`. All 5 cases failed on the planned assertions: the sync gave one "belongs to the ... room, which this business doesn't use; add rooms/... or remove the file" line per file instead of the single named line. Same hand assessment.
- **GREEN (Task 2):** `baca14b`; the two NeverLeftOutTest files pass (12 tests).
- **REFACTOR:** none needed.

## Files Created/Modified

See key-files. `.planning/WINDOWS.md` gained entry 21.

## Decisions Made

- The section's lines and the never-left-out reasons are the plan's placeholder wording until the 2026-10-17 wording pass.
- The refusal test edits the shipped marketing contract with `breakFile()` instead of writing a second contract, so it also pins the exact `owns` line.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Final full suite: 2058 passed, 16333 assertions (2053 after Task 1, plus the five NeverLeftOutTest cases).

## Verification

- Task 1 verify (38 tests) and Task 2 verify (12 tests, then the 8 offer, lead-source and price files, 176 tests) pass; `php artisan test --compact` passes (2058).
- Acceptance greps all pass: provider line and `return 16;`; `liveToday` present and 0 matches for `start_date|end_date|isLive` in the section; the exact `owns` line and `It has no outside system.`; the required strings in MarketingBootTest; `neverLeftOutBecause` in both providers; `git diff main` quiet for the seven untouched suites; `OfferValidationTest.php` is `1 1` in numstat (only the D-24 line); no diff under `examples/example-hvac/business`.
- Postgres: no local Postgres. Neither boot test has a driver guard, so CI's pgsql leg runs them when Ian pushes the branch.

## Hand checks for UAT

- **Keyboard-only and VoiceOver walk of the marketing section on /admin, and a read of the offers line against the front desk on the day an offer ends** (D-18). Not run by the executor. Recorded as an `unrun-verify` entry (id 21) in `.planning/WINDOWS.md`. Nothing was paused for it.
- Edge probe flag from the plan (ROOM-09 unclassified): the end-date boundary is pinned (11:30 pm on the end date, business time zone); any further edge is for Ian at UAT.

## Known Stubs

None.

## Threat Flags

None. The section renders only inside the authenticated panel, shows a count and offer names through escaped output, and asks OfferSentences for "live today" (T-05-09). The never-left-out refusal keeps the previous version live (T-05-10). No agent action is added, so no chokepoint is named.

## Walk-through log

No new Laravel concept appeared in this plan (a second room is the same one-line provider entry 05-03 explained), so no line was added to the sprint file.

## Next Phase Readiness

Ready for 05-06. Both rooms that can never be left out are marked. `exampleFolderFileCount()` is 16; a later room that adds example files changes only that number.

## Self-Check: PASSED

- Created files exist: provider, section, contract, MarketingBootTest, NeverLeftOutTest.
- Commits `2113734`, `0c08e50`, `2840f92`, `baca14b` are ancestors of HEAD.
- Full suite passes (2058).

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*
