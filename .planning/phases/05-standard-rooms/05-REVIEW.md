---
phase: 05-standard-rooms
reviewed: 2026-10-26T00:00:00Z
depth: standard
files_reviewed: 33
files_reviewed_list:
  - app/Icm/Loader.php
  - app/Icm/RoomChecks.php
  - app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php
  - app/Rooms/ContentGeneration/ContentBriefType.php
  - app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php
  - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php
  - app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php
  - app/Rooms/Marketing/MarketingSection.php
  - app/Rooms/Money/AccountNumberScan.php
  - app/Rooms/Money/MoneyNotesType.php
  - app/Rooms/Money/NumberCheckCouldNotFinish.php
  - app/Rooms/RoomFileType.php
  - app/Rooms/RoomRegistry.php
  - app/Rooms/RoomSection.php
  - docs/BLUEPRINT.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/CLAUDE.md
  - tests/Feature/Rooms/CompanyKnowledgeBootTest.php
  - tests/Feature/Rooms/ExampleRouterTest.php
  - tests/Feature/Rooms/GbpCategoriesTest.php
  - tests/Feature/Rooms/GoogleBusinessProfileBootTest.php
  - tests/Feature/Rooms/MarketingBootTest.php
  - tests/Feature/Rooms/MoneyBootTest.php
  - tests/Feature/Rooms/MoneyNotesCheckTest.php
  - tests/Feature/Rooms/RoomOwnershipTest.php
  - tests/Feature/Rooms/RoomSectionTest.php
  - tests/Feature/Rooms/SpecTablesTest.php
  - tests/Rooms/PretendRoom/PretendNoteType.php
  - tests/Rooms/SpecTables.php
  - tests/Unit/Icm/RoomChecksTest.php
  - tests/Unit/Icm/SingleFileTypeTest.php
  - tests/Unit/Rooms/RoomRegistryTest.php
findings:
  critical: 0
  warning: 1
  info: 2
  total: 3
status: issues_found
---

# Phase 5: Code Review Report (incremental re-review)

**Reviewed:** 2026-10-26
**Depth:** standard
**Files Reviewed:** 33
**Status:** issues_found

## Prior review

The earlier review (WR-01 to WR-03, IN-01 to IN-05) was fixed and dispositioned in `05-REVIEW-DISPOSITION.md`. I checked each fix against the code and found none wrong or incomplete. The recorded decisions D-28 to D-32 are not reported as defects, and neither are the accepted misses in D-31 (a 9-digit number right after another number in a span of 19 digits or fewer, for example `1 123-45-6789`).

One record-keeping note: the disposition file still lists IN-03 as `open`, but `GbpCategoriesType` and `GbpCategoriesTest` now carry a change labelled IN-03 (invalid UTF-8 is no longer reported as an empty entry). The record may need to be updated to `fixed`. This is not a code defect.

## Summary

I read the diff `8f7e1a7..HEAD` for every listed file, then read `AccountNumberScan`, `RoomChecks::singleFileErrors` and `RoomSection` in full. I probed the money number scan directly with about 30 inputs, including line-break variants, dots, non-breaking spaces, dashes, a blank line, a 12,000-group file and a 60,000-group file. I ran `Loader` and `Validator` against a copy of the example folder to confirm the one real gap below.

The rest holds up:
- The `\z` anchors work.
- The D-29 default-path choice works, and an interface implementer that was missed would fail the SPEC-table test.
- The D-30 limit path refuses as intended: 60,000 groups throws `NumberCheckCouldNotFinish` with "Recursion limit exhausted".
- The D-32 try/catch sits at the right layer.
- The IN-02, IN-04 and WR-02 changes behave as their tests say.
- The docs changes (SPEC file types table, BLUEPRINT, router) match the code. No statement is now false.

## Warnings

### WR-01: A file with non-UTF-8 bytes between card or ID digit groups gets past the number check

**File:** `app/Rooms/Money/AccountNumberScan.php:48`
**Issue:** `findsAccountNumber()` runs `mb_scrub($text, 'UTF-8')` so that the `/u` pattern can read the text. `mb_scrub` replaces each invalid byte with `?` (the default substitute character, 63). A `?` is not one of the span separators, so it splits the span. The Loader does not reject non-UTF-8 files: it reads them with `file_get_contents`. The money notes type then scans `$file->raw` as it is.

A notes file saved in Windows-1252 (Notepad's older ANSI default, or text pasted from Word) holds an en dash as byte `0x96` and a non-breaking space as byte `0xA0`. Both are invalid UTF-8. I confirmed this with a copy of the example folder:
- `Card 4111<0x96>1111<0x96>1111<0x96>1111 on file.` in `rooms/money/notes.md` gives `Validator::validate()->hasErrors() === false`, so the version would be accepted.
- The same line with ASCII hyphens gives `true` (refused).
- The scan returns `false` for `4111<0xA0>1111<0xA0>1111<0xA0>1111` and for `SSN 123<0x96>45<0x96>6789`.

This is the failure the scan exists to stop, and it is the same kind of separator gap D-28 closed for valid dashes and spaces. D-28 does not accept it: it says dashes and horizontal whitespace of any kind join groups, and a stray byte is only the file's encoding. The tests cover Unicode separators (NBSP, en dash) but only as valid UTF-8, so nothing pins this.
**Fix:** Do not let an invalid byte split a span. Replace each run of invalid bytes with a space, which is a separator, before scanning. For example, with no global state:
```php
private function readable(string $text): string
{
    if (mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    // Each invalid byte becomes a space so it joins digit groups the way a dash or a non-breaking space does.
    return (string) preg_replace('/[\x80-\xFF]/', ' ', $text);
}
```
Applied only on the invalid path, this changes multi-byte characters in an already-broken file to spaces. That can only add refusals, which matches the stated bias. Add `MoneyNotesCheckTest` cases for `\x96` and `\xA0` between the groups of a card and of a 9-digit number. The same `mb_scrub` pattern is in `HiddenCharacters::marked()`, where it only renders a message and is harmless.

## Info

### IN-01: `GoogleBusinessProfileSection::connectedToGoogle()` can never return true, and the live-driver list repeats a fact the provider already holds

**File:** `app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php:41-61`
**Issue:** `LIVE_DRIVERS` is `[]`, so `in_array(...)` is always false and the method always returns `false`. The connector-file lookup after it is unreachable. The constant sits between two methods, and the class docblock line is far over the width of the surrounding comments. The WR-02 fix is correct, but the driver names now live in two places: the provider's `match` and `LIVE_DRIVERS`. When the real connector lands and someone adds a case to the `match` without adding it to `LIVE_DRIVERS`, the section keeps saying "Not connected to Google" and nothing fails. The current tests cannot catch that, because they only exercise bad settings.
**Fix:** Keep the shape, but declare the constant with the other constants at the top of the class. Better, expose the live driver names from one place (a constant on the provider, used by both the `match` and this check), and add one test that every driver the provider can build is either `fake` or listed live. Until then, a one-line note on `LIVE_DRIVERS` saying it must change together with the provider's `match` is enough.

### IN-02: The scan reads only ASCII digits, so fullwidth and other Unicode digits are not seen

**File:** `app/Rooms/Money/AccountNumberScan.php:29-32`
**Issue:** The docblock says digits are written `0-9` "so a Unicode digit is never read as a group". That is deliberate, but the effect is that `４１１１ １１１１ １１１１ １１１１` (fullwidth digits, which an input method on a phone or a CJK keyboard can produce) returns `false` and passes. It is unlikely for the business owners this targets, and the docblock already states the choice, so this is not a defect against the decisions. It is a quiet gap in a check whose purpose is to lean toward refusing.
**Fix:** Either note the gap next to the "bank account numbers have no shape" warning in `rooms/money/CONTEXT.md`, or fold digits first with `preg_replace_callback('/\p{Nd}/u', fn ($m) => (string) IntlChar::charDigitValue($m[0]), $text)` before the span match. Ask Ian which, since D-28 only names `0-9`.

---

_Reviewed: 2026-10-26_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
