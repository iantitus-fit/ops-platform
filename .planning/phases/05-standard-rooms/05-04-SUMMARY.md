---
phase: 05-standard-rooms
plan: 04
subsystem: icm-sync
tags: [icm, loader, validation, unicode, hidden-characters, g-04-1, pest, tdd]

requires:
  - phase: 05-standard-rooms
    provides: plans 05-02 and 05-03 changed RoomChecks and RoomContractTest, which this plan edits again
  - phase: 04-room-standard
    provides: the room-folder lines in RoomChecks and the G-04-1 gap this plan closes
provides:
  - App\Icm\HiddenCharacters (PATTERN, found(), marked()), the one definition of the refused characters
  - the Loader refuses any file whose path holds a Cc, Cf, Zl or Zp character, before the README skip, without reading the file
  - problem code hidden_name and its plain-words sentence in Validator::problemMessage()
  - ValidationReport::lines() marks every finished line, so the stored rejected version, changelog.md and the Business Folder page show [line break] or [hidden character]
  - a refused name no longer feeds the room-folder lines in RoomChecks
affects: [05-05, 05-06, knowledge, safety-and-compliance]

plan_head_before: 9608a3394445d2d75a637fdd4a67f75d16bf8339
plan_head_after: 68e0bdcbdf65ca4c1f87643fbfb160e6ef436a78

actuals:
  tokens: 5900
  tasks: 2
  commits: 4

tech-stack:
  added: []
  patterns:
    - "One class owns a character set and exposes both the check (found) and the print-time mark (marked), so the two cannot disagree"
    - "Marking runs in ValidationReport::lines(), the one place every stored and printed refusal line passes through, so each consumer is covered without touching it"
    - "A file refused at load time carries a problem code on an Unrecognized IcmFile and later checks that build on file paths skip it by that code"

key-files:
  created:
    - app/Icm/HiddenCharacters.php
    - tests/Unit/Icm/HiddenCharactersTest.php
    - tests/Feature/Icm/HiddenCharacterTest.php
  modified:
    - app/Icm/Loader.php
    - app/Icm/Validator.php
    - app/Icm/ValidationReport.php
    - app/Icm/RoomChecks.php
    - tests/Feature/Rooms/RoomContractTest.php
    - docs/SPEC.md
    - docs/sprints/sprint-1.1.md

key-decisions:
  - "The Loader hands a hidden-named file to the Validator as an Unrecognized file with raw text '' and problem hidden_name, so the file is never read and the fingerprint of a rejected version does not depend on its contents"
  - "RoomChecks drops files with problem hidden_name when it builds the room-folder list, so a refused name earns only the name refusal (Pitfall 3); every other room line is unchanged"
  - "marked() matches CR LF as one unit before single line-break characters, so a Windows line break shows one [line break]"

patterns-established:
  - "Placeholder wording for the hidden_name sentence and the two marks lives in one place each (Validator::problemMessage(), HiddenCharacters::marked()) until the wording pass"

requirements-completed: [ROOM-08]

coverage:
  - id: D1
    description: "A file or folder name holding a Cc, Cf, Zl or Zp character is refused in one plain line, before any other check, README files included; the previous version stays live"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/HiddenCharacterTest.php (tracer, 13-row dataset, folder case, README case)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Every refusal line replaces such characters with [line break] or [hidden character], including paths and quoted owner values (a contract's owns entry, a connector's slot); no stored line holds a raw line break"
    requirement: ROOM-08
    verification:
      - kind: unit
        ref: "tests/Unit/Icm/HiddenCharactersTest.php (marked() cases, ValidationReport::lines() on malformed UTF-8)"
        status: pass
      - kind: integration
        ref: "tests/Feature/Icm/HiddenCharacterTest.php#marks a hidden character in a value the owner wrote in a contract and #marks a hidden character in a connector slot"
        status: pass
    human_judgment: false
  - id: D3
    description: "Accented (NFC and NFD), Cyrillic, CJK and emoji-with-variation-selector names pass; a ZWJ or ZWNJ name is refused with the reason"
    requirement: ROOM-08
    verification:
      - kind: integration
        ref: "tests/Feature/Icm/HiddenCharacterTest.php#lets names pass that are only not English or not ASCII, #lets café pass when the disk returns it decomposed (NFD), #refuses an emoji sequence joined with a zero-width joiner"
        status: pass
    human_judgment: false
  - id: D4
    description: "A refused name no longer also earns the unknown-room line; the Phase 4 G-04-1 test now asserts the one name refusal with the mark and no raw line break"
    verification:
      - kind: integration
        ref: "tests/Feature/Rooms/RoomContractTest.php#refuses a line break in a room folder name once, with the mark, and stores no raw line break"
        status: pass
    human_judgment: false
  - id: D5
    description: "The SPEC paragraph and the walk-through line read plainly to the owner, and the refusal sentence and marks are placeholder wording"
    verification: []
    human_judgment: true
    rationale: "Whether the added prose and the placeholder wording read plainly is a judgment; the wording pass on 2026-10-17 decisions covers the sentences"

duration: 11min
completed: 2026-10-25
status: complete
---

# Phase 5 Plan 04: Hidden characters in names Summary

**The sync refuses any file or folder name holding a line break, tab, direction mark or zero-width/joiner character (Unicode Cc, Cf, Zl, Zp) in one plain line, and every refusal line shows [line break] or [hidden character] in its place, closing Phase 4 gap G-04-1.**

## Performance

- **Duration:** about 11 min (start time was not captured at launch; estimated from the first commit)
- **Completed:** 2026-10-25T23:36:00Z
- **Tasks:** 2 (a tracer and a TDD task, 4 commits)
- **Files modified:** 10 (3 created, 7 modified)

## Accomplishments

- `HiddenCharacters::found()` treats anything other than a 0 from `preg_match` as a hit, so text that is not valid UTF-8 is refused; `marked()` scrubs broken bytes first, replaces CR LF, LF, CR, NEL, U+2028 and U+2029 with `[line break]` and every other match with `[hidden character]`.
- `Loader::load()` checks the relative path first, ahead of the README skip, and adds an Unrecognized file with problem `hidden_name` without reading it. A README.md in a hidden-named folder is refused too.
- `ValidationReport::lines()` runs each finished line through `marked()`, so quoted owner values the Loader never sees (a contract's `owns` entry, a connector's slot) are marked too. The stored rejected version, `changelog.md` and the Business Folder page all print `lines()`.
- `RoomChecks::errors()` leaves hidden-named files out of the room-folder list, so `rooms/pre[line break]# tend/CONTEXT.md` earns only the name refusal and not also "this engine has no ... room" (Pitfall 3).
- The Phase 4 G-04-1 test is updated in place to assert the one name refusal with the mark and that no stored line holds a raw line break.
- SPEC "The business folder" says what is refused and what is shown instead; one walk-through line explains Unicode categories in a PHP regex and the `preg_match` false-versus-0 trap.

## TDD

- **RED (Task 1):** 25 unit cases errored with class `App\Icm\HiddenCharacters` not found, and two cases failed on assertions: the feature tracer (icm:sync output did not contain the hidden-name refusal line) and `ValidationReport::lines()` on malformed UTF-8. The tracer's JUnit report passed `gsd_run check tdd-red-evidence` with `RED_EVIDENCE_OK` (reason target_test_failed). Semantic assessment: the target ran and failed on the planned assertion because the Loader did not yet refuse a name with a line break. Commit `f92a51b`.
- **GREEN (Task 1):** `HiddenCharacters`, the Loader refusal, the `hidden_name` sentence and the marked lines; 60 tests passed across the unit, tracer, ValidatorTest and LoaderTest files. Commit `50c7018`.
- **RED (Task 2):** the folder case and the updated G-04-1 case failed on the planned assertion with the extra line `rooms/pre[line break]# tend/: this engine has no pre # tend room; the rooms are: company_knowledge, pretend` (Pitfall 3); the other new cases already passed on Task 1's code, as the plan expects. The folder case passed `RED_EVIDENCE_OK`. A test-file slip (a single-quoted `\u{...}` in the ZWJ case) was fixed before the RED commit. Commit `03344fa`.
- **GREEN (Task 2):** `RoomChecks` drops `hidden_name` files from the folder list; 72 tests in the two files pass. Commit `68e0bdc`.
- No REFACTOR commit.

## Task Commits

1. **Task 1 RED:** `f92a51b` (test)
2. **Task 1 GREEN (tracer):** `50c7018` (feat)
3. **Task 2 RED:** `03344fa` (test)
4. **Task 2 GREEN:** `68e0bdc` (feat)

**Plan metadata:** recorded in the docs commit that follows this file.

## Tracer gate

The tracer's verify command (`php artisan test --compact` on the unit, feature, ValidatorTest and LoaderTest files) was re-run end to end after the commit and passed (60 tests); the tracer carries no human check, so expansion went ahead without a checkpoint.

## Deviations from Plan

None - plan executed exactly as written. The one in-flight correction was a test-authoring slip fixed before its commit (see TDD above).

## Issues Encountered

- `php artisan test --compact` takes about 2 minutes on the full suite and ran past the shell timeout once; it was run again in the background and passed (2046 tests). A single `warnings: 1` appears in the full-suite and the Rooms runs; it was not touched by this plan.

## Known Stubs

None.

## Hand checks for UAT

None. The plan has only automated checks, so nothing was added to `.planning/WINDOWS.md`.

## For the verifier

G-04-1 in `.planning/phases/04-room-standard/04-UAT.md` is closed by this plan. Proof: `tests/Feature/Rooms/RoomContractTest.php` ("refuses a line break in a room folder name once, with the mark, and stores no raw line break") and the tracer in `tests/Feature/Icm/HiddenCharacterTest.php`, which also checks `changelog.md`. Hidden characters inside file values (a service name, say) remain out of scope (CONTEXT Deferred Ideas).

## Threat Flags

None. No new endpoint, auth path or file-access pattern was added; the Loader gained a stricter refusal on the paths it already reads. No agent action was added, so no chokepoint is named.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ready for 05-05 (marketing room, `bootstrap/providers.php`, `tests/Pest.php`); this plan touched none of those files.
- The refusal sentence and the two marks are placeholder wording for the wording pass.

---
*Phase: 05-standard-rooms*
*Completed: 2026-10-25*

## Self-Check: PASSED
