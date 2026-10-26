---
phase: 05-standard-rooms
reviewed: 2026-10-25T00:00:00Z
depth: standard
files_reviewed: 68
files_reviewed_list:
  - app/Icm/HiddenCharacters.php
  - app/Icm/Loader.php
  - app/Icm/RoomChecks.php
  - app/Icm/ValidationReport.php
  - app/Icm/Validator.php
  - app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php
  - app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php
  - app/Rooms/ContentGeneration/ContentBriefType.php
  - app/Rooms/ContentGeneration/ContentGenerationSection.php
  - app/Rooms/ContentGeneration/ContentGenerationServiceProvider.php
  - app/Rooms/GoogleBusinessProfile/FakeGoogleListingSource.php
  - app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php
  - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php
  - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php
  - app/Rooms/GoogleBusinessProfile/GoogleListingSource.php
  - app/Rooms/Marketing/MarketingSection.php
  - app/Rooms/Marketing/MarketingServiceProvider.php
  - app/Rooms/Money/AccountNumberScan.php
  - app/Rooms/Money/MoneyNotesType.php
  - app/Rooms/Money/MoneySection.php
  - app/Rooms/Money/MoneyServiceProvider.php
  - app/Rooms/Room.php
  - app/Rooms/RoomFileType.php
  - bootstrap/providers.php
  - docs/BLUEPRINT.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/connectors/google-business-profile.md
  - examples/example-hvac/rooms/company_knowledge/CONTEXT.md
  - examples/example-hvac/rooms/content_generation/brief.md
  - examples/example-hvac/rooms/content_generation/CONTEXT.md
  - examples/example-hvac/rooms/google_business_profile/categories.md
  - examples/example-hvac/rooms/google_business_profile/CONTEXT.md
  - examples/example-hvac/rooms/marketing/CONTEXT.md
  - examples/example-hvac/rooms/money/CONTEXT.md
  - examples/example-hvac/rooms/money/notes.md
  - tests/Feature/Icm/HiddenCharacterTest.php
  - tests/Feature/Icm/OfferValidationTest.php
  - tests/Feature/Icm/SyncIgnoresRecordsTest.php
  - tests/Feature/Icm/ValidatorTest.php
  - tests/Feature/Rooms/CompanyKnowledgeBootTest.php
  - tests/Feature/Rooms/ConnectorFileTest.php
  - tests/Feature/Rooms/ContentGenerationBootTest.php
  - tests/Feature/Rooms/GbpCategoriesTest.php
  - tests/Feature/Rooms/GoogleBusinessProfileBootTest.php
  - tests/Feature/Rooms/MarketingBootTest.php
  - tests/Feature/Rooms/MoneyBootTest.php
  - tests/Feature/Rooms/MoneyNotesCheckTest.php
  - tests/Feature/Rooms/NeverLeftOutTest.php
  - tests/Feature/Rooms/OrderIndependenceTest.php
  - tests/Feature/Rooms/PretendRoomBootTest.php
  - tests/Feature/Rooms/RoomContractTest.php
  - tests/Feature/Rooms/RoomFileTypeTest.php
  - tests/Feature/Rooms/RoomOwnershipTest.php
  - tests/Feature/Rooms/RouterClaimTest.php
  - tests/Feature/SendRule/JevClassifierTest.php
  - tests/Fixtures/annex-room/rooms/annex/CONTEXT.md
  - tests/Pest.php
  - tests/Rooms/PretendRoom/AnnexRoom.php
  - tests/Rooms/PretendRoom/PretendNoteType.php
  - tests/Unit/Icm/ConnectorChecksTest.php
  - tests/Unit/Icm/HiddenCharactersTest.php
  - tests/Unit/Icm/NeverLeftOutTest.php
  - tests/Unit/Icm/RoomChecksTest.php
  - tests/Unit/Icm/SingleFileTypeTest.php
  - tests/Unit/Rooms/RoomRegistryTest.php
findings:
  critical: 0
  warning: 3
  info: 5
  total: 8
status: issues_found
---

# Phase 5: Code Review Report

**Reviewed:** 2026-10-25
**Depth:** standard
**Files Reviewed:** 68
**Status:** issues_found

## Summary

I read every app file in scope in full, along with the supporting code they depend on: `RoomRegistry`, `RoomSection`, `RoomServiceProvider`, `ConnectorChecks`, `FolderSync::compile`, `ActiveConfig`, `OfferSentences` and `PhoneNumber`. I also read the example folder files, the SPEC and BLUEPRINT diffs, and the test diffs. I ran `tests/Feature/Rooms`, `tests/Feature/Icm`, `tests/Unit/Icm` and `tests/Unit/Rooms`: 661 passed.

I also probed `AccountNumberScan`, `GbpCategoriesType::checks()` and `HiddenCharacters` directly with edge inputs.

I found no blockers. The ownership, single-file, never-left-out and hidden-name logic is consistent with D-02, D-19, D-25 and D-27. The owned paths and file types in the example contracts agree with the SPEC rows and the code (room keys, handled types, slot name, `none` handling).

The warnings are gaps in the safety checks. They do not break the sync. Recorded decisions D-xx are not reported as defects. Wording is not reported.

## Warnings

### WR-01: The card/ID scan can be bypassed by ordinary separators and by a preceding number

**File:** `app/Rooms/Money/AccountNumberScan.php:21`
**Issue:** The span regex joins digits only across a single ASCII space or hyphen. I confirmed with a probe that each of these returns `false` (nothing found):
- `4111.1111.1111.1111` (dots)
- `4111 1111` + newline + `1111 1111` (a card wrapped across two lines)
- groups separated by a non-breaking space, an en dash, a tab or two spaces
- `123.45.6789`
- `2026-10-25 4111 1111 1111 1111`

The last one is the most worrying. Any number joined to the card by one space or dash makes the span 24 digits long, which is over 19, so it passes. A line such as "Paid 2026-10-25 4111 1111 1111 1111" slips through. The scan's own header says it leans toward refusing because a missed card number costs far more than a false refusal.

D-22 fixes the span as "digits joined by single spaces or dashes" and says to decide on the whole span, so the card-after-a-date case follows from that decision. I am flagging it because it defeats the stated purpose, not because the code departs from the decision. The separator gaps (dot, newline, NBSP, en dash) are not covered by D-22's wording, only by its examples.
**Fix:** Ask Ian whether D-22 should also try each span's trailing 13 to 19 digit windows (or each group boundary). At minimum, widen the separator class so the span also joins `.`, `\t`, a newline and `\x{00A0}`, `\x{2011}`, `\x{2013}`:
```php
private const SPAN = '/\+?\d+(?:[ \-.\t\x{00A0}\x{2010}-\x{2015}]\d+)*/u';
```
and add `\R` (at most one). Update `MoneyNotesCheckTest` with the cases above. Keep the "bank account numbers have no shape" warning in `rooms/money/CONTEXT.md`.

### WR-02: `GoogleBusinessProfileSection` can take down the whole dashboard on a bad driver setting

**File:** `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php:46`, `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php:39-45`
**Issue:** `connectedToGoogle()` calls `app(GoogleListingSource::class)` only to test `instanceof`. If `config('rooms.google_business_profile.driver')` is set to anything but `fake`, the binding throws `InvalidArgumentException` while a Filament widget renders. Every page that draws the section then returns a 500, and the owner cannot reach the dashboard.

Failing fast is the `TextChannel` pattern. But `TextChannel` is not resolved while drawing an owner screen. A non-string config value (for example an array) also hits "Array to string conversion" in the message.
**Fix:** Do not construct the driver to answer a display question. Either read the configured driver name directly:
```php
return config('rooms.google_business_profile.driver', 'fake') !== 'fake'
    && collect(app(ActiveConfig::class)->filesOfType('connector'))
        ->contains(fn (array $file): bool => ($file['slot'] ?? null) === 'google_business_profile');
```
or catch `InvalidArgumentException` and treat it as "not connected". Cast the driver with `(string)` or `is_string` in the exception message.

### WR-03: `RoomChecks::bothClaimError` puts the owner's raw `owns` entry in the message

**File:** `app/Icm/RoomChecks.php:394-397` (called from `:384`)
**Issue:** On the "same owns entry written in two contracts, no file matches it yet" path, `$claimed` is the raw owner-written `owns` entry. Everywhere else in this file and in `ConnectorChecks`, owner values go through `PlainWords::oneLine()` or `PlainWords::value()`, which cap length at 80 characters and collapse whitespace. `ValidationReport::lines()` now marks line breaks, so a forged line is no longer possible. But a very long or space-heavy entry still prints in full into the stored error and `changelog.md`, and the code contradicts its own rule that quoted owner values pass through `PlainWords`. A path-error line for the same field (`pathErrors`) does use `PlainWords::value`.
**Fix:**
```php
return $this->error($later, 'owns', PlainWords::oneLine($claimed)." is claimed by both {$first} and {$later}; pick one, the other can read it");
```
`$claimed` from the file-path branch is a loaded path and is unaffected by `oneLine` unless it is over 80 characters. If that matters, apply `oneLine` only to the entry branch.

## Info

### IN-01: `Loader::ROOM_CONTRACT` ends in `$`, which accepts a trailing newline

**File:** `app/Icm/Loader.php:44`
**Issue:** In PCRE, `$` also matches before a final `\n`, so `rooms/x/CONTEXT.md\n` would count as a contract. Today the hidden-character check in `load()` refuses such a name before any contract match, so this cannot be reached. But `RoomChecks` and `FolderSync::compile` rely on this regex alone for the contract path, and Phase 5 just made the safety of that depend on a separate check running first.
**Fix:** Use `\z`, or the `D` modifier: `'#^rooms/([^/]+)/CONTEXT\.md\z#'`.

### IN-02: `CompanyKnowledgeSection` hardcodes the FAQ path

**File:** `app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php:28`
**Issue:** `$config->file('business/faq.md')` ignores what the contract owns, and `file()` returns a file of any type. If the owner moves the FAQ to another owned path (a `knowledge` file elsewhere), the section keeps saying "FAQ: not added yet". If a non-knowledge file is placed at that path, the section says "in place". The rest of the app has `compiled['index']['knowledge']`, but `ActiveConfig` has no accessor for it.
**Fix:** Ask for the knowledge files by type (`filesOfType('knowledge')` works with the index shape `FolderSync::compile` builds), and check for a `business/faq.md` entry in that list. Or document the fixed path as the contract.

### IN-03: `GbpCategoriesType` comparison edge cases

**File:** `app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php:126-129`
**Issue:**
- `normalized()` does not strip hidden characters. `"Furnace repair"` and `"Furnace\u{200B} repair"` are treated as different categories, so the repeat and primary-equals checks miss them. SPEC says hidden characters inside values are not checked yet (a deferred item), so this is a known gap rather than a bug.
- If `preg_replace` fails (invalid UTF-8 in the value), `(string) null` gives `''`. The entry is then reported as "item N is empty", which is misleading.
**Fix:** Strip `\p{Cf}` in `normalized()` if the deferred decision lands. Optionally treat a `null` from `preg_replace` as a distinct error line.

### IN-04: `OfferSentences` usage in `MarketingSection` indexes the result of `offer()` without a null guard

**File:** `app/Rooms/Marketing/MarketingSection.php:29`
**Issue:** `$config->offer($live['slug'])['name']` is safe today because `liveToday()` iterates the same `offers()` list. It would raise a warning (and a 500 on the dashboard) if the two ever diverged, for example an offer whose slug normalises differently as an array key. The `(string)` cast would then hide a `null`.
**Fix:** `$config->offer($live['slug'])['name'] ?? $live['slug']`.

### IN-05: Example folder router does not mention the new folders

**File:** `examples/example-hvac/CLAUDE.md`
**Issue:** The router points to `business/`, `voice/`, `rules/` and `records/` only. It says nothing about `rooms/` or `connectors/`, which now hold seven of the example's typed files. `SPEC.md` lists `CLAUDE.md` as the house's file and routers are only pointers, so nothing breaks. An owner or AI reading the folder via this file will not find the rooms.
**Fix:** Add one line to the router: "What each part of the business does and which files it owns: `rooms/`. Outside systems: `connectors/`."

---

_Reviewed: 2026-10-25_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
