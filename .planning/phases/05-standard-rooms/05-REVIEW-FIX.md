---
phase: 05-standard-rooms
fixed_at: 2026-10-25T00:00:00Z
review_path: .planning/phases/05-standard-rooms/05-REVIEW.md
iteration: 1
findings_in_scope: 9
fixed: 9
skipped: 1
status: partial
---

# Phase 5: Code Review Fix Report

**Fixed at:** 2026-10-25
**Source review:** .planning/phases/05-standard-rooms/05-REVIEW.md
**Iteration:** 1

**Summary:**
- In scope: WR-01, WR-02, WR-03, IN-01, IN-02, IN-03 (second bullet only), IN-04, IN-05, and D-29 (9 items)
- Fixed: 9 (each with a failing test committed first, then the fix)
- Skipped: 1 (IN-03 first bullet, by instruction)
- Final full suite: `php artisan test --compact` gave 2188 passed, 0 failed (16910 assertions), 1 Pest warning that is not a failure. Ran after every fix was in the working tree.

**Where verification ran:** the main checkout at /Users/iantitus/Projects/ops-platform on branch gsd/phase-05-standard-rooms (`workflow.use_worktrees` is false, so no worktree was created). Every number here is reproducible from that tree. The suite runs `vendor/bin/pint --dirty --format agent` before each fix commit; it passed every time.

Nothing was pushed, merged or rewritten. `.planning/state.json` and `.planning/milestone.lock` were not touched or staged.

Commit pairs (RED = failing test, GREEN = fix):

| Item | RED | GREEN |
|---|---|---|
| WR-01 | a79bfe7 | 0ea7952 |
| D-29 | 7b21185 | 8d61261 |
| WR-02 | b9c9320 | 8aa7232 |
| WR-03 | b2b2d12 | 5bd8bb4 |
| IN-01 | 887d345 | c218979 |
| IN-02 | f1f252b | 2417732 |
| IN-03 (second bullet) | 2826dd8 | b4a6178 |
| IN-04 | 1167c57 | 031c9c3 |
| IN-05 | 1710866 | 4fdafa4 |

## Fixed Issues

### WR-01: The card/ID scan can be bypassed by ordinary separators and by a preceding number

**Status:** fixed: requires human verification (the rule is a logic change to a safety scan)
**Files modified:** `app/Rooms/Money/AccountNumberScan.php`, `tests/Feature/Rooms/MoneyNotesCheckTest.php`, `docs/SPEC.md`
**Test file:** `tests/Feature/Rooms/MoneyNotesCheckTest.php`. New tests: "refuses a card or a 9-digit number whose groups are joined by a dot, tab, Unicode space, Unicode dash or one line break (D-28)", "refuses a card or a 9-digit number that follows another number in the same span (D-28)", "still splits spans at two separators in a row, and at a comma (D-28)", "lets the example money notes through the widened check".
**RED commit:** a79bfe7. **Output before the fix:** 12 failed, 45 passed of 57. Every failure read `Expected status code 1 but received 0. Failed asserting that 0 matches expected 1.` The 12: card with dots, card wrapped across one line break, across a Windows line break, with non-breaking spaces, with en dashes, with tabs, `123.45.6789`, 9 digits with a non-breaking space, `Paid 2026-10-25 4111 1111 1111 1111`, the same with a line break between groups, `2026-10-25 2026-10-26 123 45 6789`, and `Paid 2026-10-25 123-45-6789 4111 1111 1111 1111`. The guards (two spaces split, comma split, ZIP+4 alone, the example notes) passed before and after.
**GREEN commit:** 0ea7952. **After:** the file passes (58 of 58). `tests/Feature/Rooms`, `tests/Unit` and `tests/Feature/Icm` pass.
**Applied fix:**
- The span pattern is now `/\+?[0-9]+(?:(?:[\t.\p{Zs}\p{Pd}]|\R)[0-9]+)*/u`. I wrote the digits as `[0-9]` because PHP's `/u` makes `\d` match Unicode digits, which the rest of the scan (a byte-wise Luhn) cannot read.
- Each span is judged whole first with D-22 and D-26 unchanged, then, if it is over 19 digits, `hasAccountNumberRun()` checks every run of whole digit groups. A run with 9 digits, or 13 to 19 passing the check digit, is a hit unless the run's own text (with the leading plus when the run starts at the span's first group) is a whole phone per `PhoneNumber::toE164`. A run stops growing past 19 digits. No ZIP+4 exception for runs.
- Added `mb_scrub()` before matching so invalid UTF-8 in the notes cannot make the `/u` pattern fail and let the file through.
- Docblocks cite D-28 and keep the "cannot see a bank account number" sentence. The SPEC `money_notes` row describes the widened check and cites D-28; `SpecTablesTest` still passes. Linear cost was checked with a 200,000-group span: 0.01 s.

**Existing test updated in place (recorded decision made its expectation wrong):** in "lets through what is not a card or a 9-digit number", the case `two phones side by side` was `312-555-0147 773-555-0100`. That text is one 20-digit span, and its first four groups (`3125550147773`, 13 digits) pass the check digit and are not a phone, so D-28 refuses it. Under D-22 and D-28 that is the accepted false refusal. I changed the passing example to `312-555-0100 773-555-0147` (no run hits) and added the original text to the refused list with a name saying why. The assertion itself was not loosened. A comment in the test says so.

**Things Ian should know:**
- **Gap not closed:** D-28 checks group runs only for spans over 19 digits. `2026-10-25 123 45 6789` is 17 digits, so it is still not refused. The instruction listed it as a case to catch; D-28 as written does not, and I did not widen a recorded decision. I used `2026-10-25 2026-10-26 123 45 6789` (25 digits) for the test. If a single date before a 9-digit number should be refused, D-28 needs to say runs are checked for any span of more than one group, not just over 19 digits. Decision for Ian.
- **False-refusal rate:** with the run rule, a date or a phone next to a few more digits has about a 1 in 10 chance per eligible run of passing the check digit by luck. Two example texts hit this (the existing two-phones case above, and a date followed by `312-555-0147`). This is the accepted cost in D-22 and D-28.
- **Pre-existing, not fixed (out of scope):** the span match fails open on a very large input. With about 200,000 digit groups joined by single spaces, `preg_match_all` returns false ("Recursion limit exhausted") and the scan finds nothing. This was true of the old pattern too. A one-line fix is to treat a false return as a refusal. It needs its own test and I left it for you to schedule.

### D-29: Each single-file type declares its default path

**Status:** fixed: requires human verification (logic change to which file the sync keeps)
**Files modified:** `app/Rooms/RoomFileType.php`, `app/Rooms/ContentGeneration/ContentBriefType.php`, `app/Rooms/Money/MoneyNotesType.php`, `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php`, `app/Icm/RoomChecks.php`, `tests/Rooms/PretendRoom/PretendNoteType.php`, `tests/Unit/Rooms/RoomRegistryTest.php`, `tests/Unit/Icm/SingleFileTypeTest.php`, `tests/Feature/Rooms/MoneyBootTest.php`, `tests/Feature/Rooms/SpecTablesTest.php`, `tests/Rooms/SpecTables.php`, `docs/SPEC.md`
**Tests:**
- `tests/Unit/Icm/SingleFileTypeTest.php`: "keeps the default path and refuses the file that sorts before it, naming the default (D-29)", "keeps the default path even when the contract claims only the other file (D-29)", and the guard "keeps the first claimed, then the first in path order, when the default path is not there (D-29)".
- `tests/Feature/Rooms/MoneyBootTest.php`: "keeps the default notes file when an added file sorts before it" (contract owns `more.md` and `notes.md`; the line must be on `more.md` and name `notes.md`).
- `tests/Feature/Rooms/SpecTablesTest.php`: "declares for each single-file type the default path that SPEC gives it" and "reads the File column of the file types table, and only the rows under its heading". The SPEC reader is a new `SpecTables::fileTypeFiles()` in `tests/Rooms/SpecTables.php`.
**RED commit:** 7b21185. **Output before the fix:** 3 failed, 1 errored, 42 passed of 46.
- Unit: `Failed asserting that two arrays are identical` (line was on `rooms/solo/main.md`, not `added.md`) and `Failed asserting that an array contains 'rooms/solo/added.md'`.
- Money: `Failed asserting that two arrays are identical`, expected a line on `rooms/money/more.md`, got one on `rooms/money/notes.md`.
- SPEC tie: `Call to undefined method App\Rooms\ContentGeneration\ContentBriefType::singleFileDefaultPath()`.
**GREEN commit:** 8d61261 (`feat(05): D-29 ...` as instructed). **After:** 961 of 961 in `tests/Feature/Rooms`, `tests/Unit`, `tests/Feature/Icm` (then 967 to 972 as later fixes landed). The SPEC tie test needed `ksort` on my side, because the registry order differs from the SPEC row order; that was in my new test, not an existing one.
**Applied fix:**
- New interface method `RoomFileType::singleFileDefaultPath(): ?string`, documented next to `singleFileLabel()`, citing D-29 and the `Validator::SINGLETONS` parallel. Implemented in `ContentBriefType` (`rooms/content_generation/brief.md`), `MoneyNotesType` (`rooms/money/notes.md`), `GbpCategoriesType` (`rooms/google_business_profile/categories.md`), and `null` in `PretendNoteType` and the anonymous type in `RoomRegistryTest`. grep found no other implementer.
- `RoomChecks::singleFileErrors()` now keeps the default-path file when it is among the files of that type in the room's folder; otherwise the old rule (first validly claimed, else first by path). Docblock updated. The sync still refuses the whole version.
- The unit test helper type in `SingleFileTypeTest.php` gained an optional default-path argument, and the `solo` room's type now defaults to `rooms/solo/main.md`. The existing assertions are unchanged and still pass.
- SPEC rows for `content_brief`, `money_notes` and `gbp_categories` now say the file at the File column's path is the one kept, and cite D-29.
- The earlier `second.md` tests (05-07) still pass; I left them alone.
- Side effect to know: if the contract owns only the added file and the default file also exists, the default is kept, so the default file also gets the existing "no room lists this file under owns" line. The version is refused either way.

### WR-02: `GoogleBusinessProfileSection` can take down the dashboard on a bad driver setting

**Status:** fixed: requires human verification
**Files modified:** `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php`, `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php`, `tests/Feature/Rooms/GoogleBusinessProfileBootTest.php`
**Test file:** `tests/Feature/Rooms/GoogleBusinessProfileBootTest.php`: "draws the dashboard and says not connected when the driver setting is bad" (cases: `live`, an array, `5`, `true`) and "refuses a driver setting that is not a string with the same kind of error, not a conversion error".
**RED commit:** b9c9320. **Output before the fix:** 5 failed, 13 passed of 18. Four read `Expected response status code [200] but received 500`; one read `Failed asserting that 'Array to string conversion' contains "array"`.
**GREEN commit:** 8aa7232. **After:** `GoogleBusinessProfileBootTest.php` 18 of 18.
**Applied fix:** `connectedToGoogle()` reads the configured driver name and never builds the driver. It answers "connected" only for a string in `LIVE_DRIVERS`, which is empty this phase (only the fake exists), so a bad value, including a non-string, reads as not connected. The provider still throws for a real caller of the slot, and its message now uses `get_debug_type()` for a non-string value (`array`, `int`, `bool`). Class docblock updated.
**Judged differently:** the review's suggested `driver !== 'fake'` would read an unknown name such as `live` as connected. Your instruction was that a bad value must not read as connected, so I used a live-driver list instead. When the real connector lands, add its driver name to `LIVE_DRIVERS`.

### WR-03: `RoomChecks::bothClaimError` puts the owner's raw `owns` entry in the message

**Status:** fixed
**Files modified:** `app/Icm/RoomChecks.php`, `tests/Feature/Rooms/RoomOwnershipTest.php`
**Test file:** `tests/Feature/Rooms/RoomOwnershipTest.php`: "shortens a very long owns entry two contracts both write, as every owner value is".
**RED commit:** b2b2d12. **Output before the fix:** `Failed asserting that two arrays are identical` (the line held the full 128-character entry instead of the 77 characters plus `...`).
**GREEN commit:** 5bd8bb4. **After:** `RoomOwnershipTest.php` 21 of 21.
**Applied fix:** the entry branch now passes `PlainWords::oneLine($entry)`; the loaded-file branch keeps the loaded path as is, as the review allowed. Docblock explains why.

### IN-01: `Loader::ROOM_CONTRACT` ends in `$`

**Status:** fixed
**Files modified:** `app/Icm/Loader.php`, `app/Rooms/RoomRegistry.php`, `tests/Unit/Icm/RoomChecksTest.php`, `tests/Unit/Rooms/RoomRegistryTest.php`
**Tests:** `tests/Unit/Icm/RoomChecksTest.php`: "does not take a path with a trailing line break for a room contract". `tests/Unit/Rooms/RoomRegistryTest.php`: "refuses a key or a file type that ends in a line break".
**Seam:** `ROOM_CONTRACT` is a public constant and `RoomChecks::contractErrors()` takes an `IcmFile` a test builds by hand, so no private member was exposed.
**RED commit:** 887d345. **Output before the fix:** `Failed asserting that 1 is identical to 0.` (the pattern matched `rooms/foo/CONTEXT.md` plus a newline) and `Exception [LogicException] not thrown.` (the registry accepted a key ending in a newline).
**GREEN commit:** c218979. **After:** both files pass.
**Applied fix:** `ROOM_CONTRACT` now ends in `\z`. The one sibling in the same pattern in the Phase 5 reviewed code is `RoomRegistry::NAME_PATTERN` (room keys, file type words, slots), which also ended in `$`; it now ends in `\z` too.
**Not changed:** two other `$` anchors in `app/Icm/Validator.php` (`isTime()` at line 359 and the `records` check at line 740) have the same trailing-newline behavior. They are Phase 1 code outside this review's rooms scope and neither is a safety check, so I left them. Say the word if you want them done.

### IN-02: `CompanyKnowledgeSection` hardcodes the FAQ path

**Status:** fixed
**Files modified:** `app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php`, `tests/Feature/Rooms/CompanyKnowledgeBootTest.php`
**Test file:** `tests/Feature/Rooms/CompanyKnowledgeBootTest.php`: "says the FAQ is not added when the file at its path is not a knowledge file" (a service file written to `business/faq.md`).
**RED commit:** f1f252b. **Output before the fix:** the dashboard held no `FAQ: not added yet` (it said `FAQ: in place`), so the assertion on that text failed.
**GREEN commit:** 2417732. **After:** `CompanyKnowledgeBootTest.php` passes, including the existing empty and furnished boots.
**Applied fix:** the line is decided by `filesOfType('knowledge')` containing `business/faq.md`. The path stays fixed, as the contract owns it; a missing FAQ still reads "not added yet", and a file of another type there no longer counts. I did not make the section follow the FAQ to another path, because the room has no way to know which knowledge file is "the FAQ". Say so if you want any knowledge file to count.

### IN-03 (second bullet): `preg_replace` null reported as an empty entry

**Status:** fixed. The first bullet (hidden characters inside category names) is **skipped: waits for the deferred hidden-characters-in-values decision**, as instructed.
**Files modified:** `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php`, `tests/Feature/Rooms/GbpCategoriesTest.php`
**Test file:** `tests/Feature/Rooms/GbpCategoriesTest.php`: "gives an entry that is not valid text its own line, not the empty-entry line". It calls `GbpCategoriesType::checks()` with an `IcmFile` built by hand (invalid UTF-8 byte in item 2).
**RED commit:** 2826dd8. **Output before the fix:** `Expecting 'additional_categories item 2 ...ove it' not to contain 'is empty'.`
**GREEN commit:** b4a6178. **After:** `GbpCategoriesTest.php` passes.
**Applied fix:** `normalized()` returns null when the text cannot be read, and `checks()` gives that entry the line `additional_categories item N has characters that are not valid text; retype that category`. A null primary category is skipped in the primary comparison as before.

### IN-04: `MarketingSection` indexes `offer()` without a null guard

**Status:** fixed
**Files modified:** `app/Rooms/Marketing/MarketingSection.php`, `tests/Feature/Rooms/MarketingBootTest.php`
**Test file:** `tests/Feature/Rooms/MarketingBootTest.php`: "names a live offer by its slug when the config has no offer under that slug". The seam is an `OfferSentences` subclass bound in the container whose `liveToday()` returns a slug the config does not have.
**RED commit:** 1167c57. **Output before the fix:** `Expected response status code [200] but received 500 ... ErrorException: Trying to access array offset on null in app/Rooms/Marketing/MarketingSection.php:29`.
**GREEN commit:** 031c9c3. **After:** `MarketingBootTest.php` passes.
**Applied fix:** `(string) ($config->offer($live['slug'])['name'] ?? $live['slug'])`.

### IN-05: Example folder router does not mention the new folders

**Status:** fixed
**Files modified:** `examples/example-hvac/CLAUDE.md`, `tests/Feature/Rooms/ExampleRouterTest.php` (new file)
**Test file:** `tests/Feature/Rooms/ExampleRouterTest.php`: "names every top-level folder of the example business in its router" and "points to the rooms and connectors folders in plain words".
**RED commit:** 1710866. **Output before the fix:** the unnamed folders were `agents`, `automations`, `connectors`, `rooms`, `tests`; the second test failed because the router held neither `rooms/` nor `connectors/`.
**GREEN commit:** 4fdafa4. **After:** both pass; the full suite passes, so the sync still loads the example (`CLAUDE.md` is a router the Loader keeps as raw text, and `exampleFolderFileCount()` is unchanged).
**Applied fix:** five pointer lines added to the router, in the fictional example's own plain words: `rooms/`, `connectors/`, `agents/`, `automations/` and `tests/`. **Judged differently:** you asked for the rooms and connectors lines, but the test you specified ("names every top-level folder") also needs `agents/`, `automations/` and `tests/`, which the router also left out. I added all five rather than weaken the test.

## Skipped Issues

### IN-03 (first bullet): hidden characters inside category names

**File:** `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php:126-129`
**Reason:** skipped by instruction. It waits for the deferred hidden-characters-in-values decision (SPEC says hidden characters inside values are not checked yet). The second bullet of IN-03 was fixed above.
**Original issue:** `normalized()` does not strip hidden characters, so `"Furnace repair"` and `"Furnace\u{200B} repair"` count as different categories.

## Verification summary

- Final full suite, main checkout, after all fixes: `php artisan test --compact` gave 2188 passed, 0 failed, 16910 assertions, 1 warning. I did not investigate the one Pest warning; it shows on every multi-directory run in this session, including after the first commits.
- No browser (Dusk or axe) tests were skipped: they run inside the full suite.

---

_Fixed: 2026-10-25_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
