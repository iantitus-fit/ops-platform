---
phase: 05-standard-rooms
verified: 2026-10-26T01:30:00Z
status: human_needed
score: 4/4 must-haves verified
covered_files:
  - ".planning/phases/05-standard-rooms/05-01-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-01-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-02-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-02-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-03-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-03-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-04-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-04-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-05-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-05-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-06-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-06-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-07-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-07-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-08-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-08-SUMMARY.md"
  - ".planning/phases/05-standard-rooms/05-09-PLAN.md"
  - ".planning/phases/05-standard-rooms/05-09-SUMMARY.md"
  - "app/Icm/HiddenCharacters.php"
  - "app/Icm/Loader.php"
  - "app/Icm/RoomChecks.php"
  - "app/Icm/ValidationReport.php"
  - "app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php"
  - "app/Rooms/ContentGeneration/ContentGenerationServiceProvider.php"
  - "app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php"
  - "app/Rooms/Marketing/MarketingServiceProvider.php"
  - "app/Rooms/Money/AccountNumberScan.php"
  - "app/Rooms/Money/MoneyServiceProvider.php"
  - "app/Rooms/Room.php"
  - "app/Rooms/RoomFileType.php"
  - "bootstrap/providers.php"
  - "examples/example-hvac/connectors/google-business-profile.md"
  - "examples/example-hvac/rooms/company_knowledge/CONTEXT.md"
  - "examples/example-hvac/rooms/content_generation/CONTEXT.md"
  - "examples/example-hvac/rooms/google_business_profile/CONTEXT.md"
  - "examples/example-hvac/rooms/marketing/CONTEXT.md"
  - "examples/example-hvac/rooms/money/CONTEXT.md"
covered_digest: "v3:sha256:0f807609371d593454aa4d391f9283974e555effc3a1cb63afdb73fe22672b12"
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Keyboard-only and VoiceOver walk of each new room section on /admin: company knowledge, marketing, content generation (empty and with the brief), money (empty and with the notes), Google Business Profile (empty and with the categories file). Also read the marketing offers line against the front desk on the day an offer ends."
    expected: "Every section is reachable and readable by keyboard and screen reader, headings and lines are announced in a sensible order, and the offers line matches what the front desk would send."
    why_human: "axe rules catch only part of WCAG 2.2 AA (CLAUDE.md). Recorded as WINDOWS.md unrun-verify entries 20 to 24 per Ian's standing instruction not to pause for live hand checks."
  - test: "Push the branch and read the CI result for the pgsql leg of .github/workflows/tests.yml."
    expected: "All five room boot tests (empty and with example folder) and the rest of the suite pass on Postgres 17 as they do on SQLite."
    why_human: "No local Postgres. Roadmap SC1 requires each room's boot test to pass on SQLite and Postgres; the SQLite half was run here (2148/2148). The rooms add no tables or migrations and the boot tests carry no driver guard, so risk is low, but it is unobserved."
---

# Phase 5: Standard Rooms Verification Report

**Phase Goal:** The rooms that need only the standard join the example business: marketing, content generation, Google Business Profile, company knowledge and money, each a folder the owner can open and furnish.
**Verified:** 2026-10-26
**Status:** human_needed
**Re-verification:** No, initial verification

All four roadmap success criteria hold in the code. I did not rely on SUMMARY.md. I read the providers, file types, sections, RoomChecks, Loader, HiddenCharacters, ValidationReport, the example folder files and the boot tests, and I ran the full suite myself (`php artisan test --compact`: 2148 passed, 16781 assertions, SQLite). What remains is human or CI work only: the keyboard and VoiceOver walks and the Postgres leg.

## Goal Achievement

### Observable Truths (ROADMAP success criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `icm:sync` on `examples/example-hvac` activates a version with the five room folders, each with `CONTEXT.md` and one example file; reducing any room folder to `CONTEXT.md` alone still syncs; each boot test passes on SQLite and Postgres | VERIFIED on SQLite, Postgres UNOBSERVED (human item 2) | `examples/example-hvac/rooms/` holds company_knowledge, marketing, content_generation (brief.md), google_business_profile (categories.md), money (notes.md), plus `connectors/google-business-profile.md`. Example folder has 23 typed files (14 + 5 contracts + 3 room files + 1 connector), matching `exampleFolderFileCount()` = 23. Five `*BootTest.php` files run each room empty and with its example folder (content generation and money delete the one file; GBP deletes categories.md and the connector file; company knowledge and marketing keep the sync-required files per D-03). All pass. CI matrix `[sqlite, pgsql]` exists in `.github/workflows/tests.yml`; boot tests have no driver guard. |
| 2 | Owner sees marketing, content generation, GBP and company knowledge sections on the dashboard; money meets the full standard (module, boot test empty and with example) with no payment-link slot and no payment records | VERIFIED | Each provider extends `RoomServiceProvider`, returns a `Room` with `sections: [...Section::class]`, and is registered in `bootstrap/providers.php` after `AppServiceProvider`. Boot tests assert `/admin` shows `data-room-section="<key>"` and furnished lines (e.g. "Lead sources: 4", "Offers live today: First visit promotion"), and the platform empty line when empty. Each runs `RoomSectionAxe::assertClean` in a browser visit. Money: `MoneyServiceProvider` has no slot, tables or timeline entries; `MoneyBootTest` includes 'adds no payment table or record'; no payment migration exists. |
| 3 | Marketing owns `business/lead-sources.md` and `business/offers/`, still where they are; offers and lead sources behave as before; existing suite for them passes unchanged | VERIFIED | `rooms/marketing/CONTEXT.md` owns `[business/lead-sources.md, business/offers/*.md]`; `MarketingServiceProvider` uses `handles: ['lead_sources','offer']`, no new file type. `git diff` of the phase touches nothing under `app/SendRule`, and the only edits to offer and classifier tests are the D-24 file-count changes (`14` to `exampleFolderFileCount()`; `JevClassifierTest` pins the live version's count to that helper). `MarketingSection` takes "live today" from `OfferSentences::liveToday()`, the front desk's own code; a four-moment test checks the dashboard against it, including the inclusive end date in the business time zone. Full suite passes. |
| 4 | GBP room has a connector slot (interface plus fake driver) and a connector file declaring `owns` and `reads`; with only the fake nothing reaches Google; content generation's `CONTEXT.md` says it has no connector | VERIFIED | `GoogleListingSource` (one method, `fetchListing()`), `FakeGoogleListingSource` (fictional data, fetch counter), bound in `GoogleBusinessProfileServiceProvider::register()` the `TextChannel` way; an unknown driver throws. `connectors/google-business-profile.md` has `slot: google_business_profile`, `owns: none`, `reads: none` (bare word, per D-10); `[none]` is refused (test). No HTTP client, curl or Guzzle anywhere under `app/Rooms`. Tests: 'never fetches the listing when the dashboard is drawn', 'declares exactly one method on the slot interface'. Content generation `CONTEXT.md` has `connector: none` and the body "It has no connector and no outside system." |

**Score:** 4/4 truths verified (0 behavior-unverified)

### Phase-level decisions checked beyond the roadmap criteria

| Decision | Status | Evidence |
|----------|--------|----------|
| D-02 never-left-out: one line, no per-file lines, for company knowledge and marketing | VERIFIED | `Room::$neverLeftOutBecause` set on both providers; `RoomChecks::errors()` emits one "can never be left out, because ..." line when the room's folder is absent; `unclaimedOrAbsentError` suppresses the per-file lines when a never-left-out room handles the type. `NeverLeftOutTest` (feature and unit) pass. Content generation, GBP and money stay optional. |
| D-27 single-file types (brief, notes, categories) refused wherever `owns` points | VERIFIED | `RoomFileType::singleFileLabel()` implemented by the three types; `RoomChecks::singleFileErrors()` works from the loaded files in the room folder, independent of `owns`. Boot tests cover shipped contract, a contract edited to own two paths, and a pattern. |
| D-19 / D-25 / G-04-1 hidden characters | VERIFIED | `HiddenCharacters` (Cc, Cf, Zl, Zp, ZWJ and ZWNJ included); `Loader::load()` refuses a name before the README skip; `ValidationReport::lines()` marks every finished line after `PlainWords`. `HiddenCharacterTest` and unit test pass. |
| D-16 / D-22 / D-26 money notes number refusal | VERIFIED as decided (see Warning W1) | `AccountNumberScan`: 9-digit spans refused (ZIP+4 and phones excepted), 13 to 19 digit spans passing the check digit refused, fixed message that never quotes the number. I probed it: `4111 1111 1111 1111`, `123-45-6789`, `12-3456789` refused; `60601-1234` and `+1 312 555 0147` pass. |
| D-23 two Phase 4 feature tests moved to unit tests | VERIFIED | `tests/Unit/Icm/RoomChecksTest.php` and `ConnectorChecksTest.php` added; suite green. |
| D-21 SPEC reader changes and new file-type rows | VERIFIED | `docs/SPEC.md` diff adds GBP and content generation as readers and rows for `content_brief`, `money_notes`, `gbp_categories`; `SpecTablesTest` checks them against code. |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Rooms/{CompanyKnowledge,Marketing,ContentGeneration,GoogleBusinessProfile,Money}/*ServiceProvider.php` | VERIFIED | Real `Room` descriptions, registered in `bootstrap/providers.php` |
| `*Section.php` x5 | VERIFIED | Extend `RoomSection`; read live version through `ActiveConfig`; no stub lines |
| `ContentBriefType`, `GbpCategoriesType`, `MoneyNotesType`, `AccountNumberScan` | VERIFIED | Substantive checks, wired through `Room::fileTypes` |
| `GoogleListingSource`, `FakeGoogleListingSource` | VERIFIED | Interface bound to fake; no outbound code |
| `app/Icm/HiddenCharacters.php`, `Loader`, `ValidationReport`, `RoomChecks` edits | VERIFIED | Wired and exercised by tests |
| `examples/example-hvac/rooms/*`, `connectors/google-business-profile.md` | VERIFIED | Present, fictional, loaded by the sync |

### Key Link Verification

| From | To | Status | Details |
|------|----|--------|---------|
| `bootstrap/providers.php` | five room providers | WIRED | All five listed |
| Room `sections` | `/admin` dashboard | WIRED | Boot tests see `data-room-section` markup and furnished lines |
| `MarketingSection` | `OfferSentences::liveToday()` | WIRED | Same code the front desk uses |
| `GoogleListingSource` | `FakeGoogleListingSource` | WIRED | Container binding in provider `register()` |
| Room `fileTypes` | `Loader`/`Validator` | WIRED | `RoomRegistry::registeredTypes()`; example files compile into the version |
| `singleFileLabel()` | `RoomChecks::singleFileErrors()` | WIRED | Tests refuse a second file |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| `CompanyKnowledgeSection` | service count, FAQ | `ActiveConfig::services()`, `file()` | Yes, live version | FLOWING |
| `MarketingSection` | lead source count, live offers | `ActiveConfig::leadSources()`, `OfferSentences` | Yes | FLOWING |
| `ContentGenerationSection`, `MoneySection` | brief / notes in place | `ActiveConfig::filesOfType()` | Yes | FLOWING |
| `GoogleBusinessProfileSection` | primary category, connected flag | `filesOfType('gbp_categories')`, container driver | Yes (not fetched from Google by design, D-11) | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Whole suite on SQLite | `php artisan test --compact` | 2148 passed, 16781 assertions | PASS |
| Example folder file count | boot tests assert the activated version's `file_count` against `exampleFolderFileCount()` | 23 = 14 earlier files + 5 contracts + 3 room files + 1 connector file | PASS |
| Number scan | tinker probe of `AccountNumberScan` | See D-16 row above | PASS (as decided) |

### Probe Execution

Step 7c: SKIPPED. The phase declares no probe scripts (`scripts/*/tests/probe-*.sh`).

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|-------------|--------|----------|
| ROOM-08 | 05-01, 05-02, 05-03, 05-04, 05-05 | Company knowledge room to the standard | SATISFIED | Provider, section, contract, boot test, never-left-out; REQUIREMENTS.md marks Complete |
| ROOM-09 | 05-01, 05-02, 05-05 | Marketing room, owns lead sources and offers | SATISFIED | Provider, contract, section reusing `OfferSentences`; offer suite unchanged |
| ROOM-10 | 05-01, 05-06 | Content generation room, no connector | SATISFIED | Provider, `ContentBriefType`, single-file rule, `connector: none` |
| ROOM-11 | 05-01, 05-08, 05-09 | GBP room with read-only slot and fake driver | SATISFIED | Slot, fake, `GbpCategoriesType` (max 9, no repeats, none equal primary), connector file `owns: none` / `reads: none` |
| ROOM-14 | 05-01, 05-07 | Money room to the standard, no slot, no payment records | SATISFIED | Provider, `MoneyNotesType`, boot test empty and with notes, no payment tables |

Orphan check: REQUIREMENTS.md maps exactly ROOM-08, 09, 10, 11 and 14 to Phase 5; every one appears in at least one PLAN's `requirements`. No orphaned IDs. (05-04 lists ROOM-08 for G-04-1, which is a phase-boundary item and not a separate requirement.)

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| (phase files) | n/a | TBD/FIXME/XXX/TODO/HACK scan across files changed in the phase | none found | Debt-marker gate clear |
| No stubs, empty returns or console-only handlers found in `app/Rooms/*` | | | | |

### Warnings (not gaps against the roadmap)

- **W1. Money notes number scan has gaps (05-REVIEW WR-01, disposition `open`).** I confirmed with a probe that `Paid 2026-10-25 4111 1111 1111 1111` and `4111.1111.1111.1111` return "nothing found". The first follows from D-22 (span is digits joined by single spaces or dashes, decided whole); dots, tabs, newlines and non-breaking spaces are not covered by D-22's wording. This is not a roadmap success criterion and the code matches the recorded decision, so it does not fail the phase. It does work against the check's stated purpose, so Ian should decide whether to widen D-22 before the money notes are used for real.
- **W2. (05-REVIEW WR-02, open)** `GoogleBusinessProfileSection::connectedToGoogle()` constructs the driver to answer a display question; a bad `rooms.google_business_profile.driver` setting would 500 the dashboard. Only the fake exists and no config key is set, so it is latent.
- **W3. (05-REVIEW WR-03, open)** `RoomChecks::bothClaimError` prints the raw owner `owns` entry without `PlainWords::oneLine`. Hidden characters are now marked at print time, so no forged line is possible; length is uncapped.
- Info items IN-01 to IN-05 remain open in `05-REVIEW-DISPOSITION.md`; none affects a success criterion.

### Human Verification Required

#### 1. Keyboard-only and VoiceOver walk of the five new sections

**Test:** On /admin, walk each new room section by keyboard and with VoiceOver: company knowledge, marketing, content generation (empty and with brief), money (empty and with notes), Google Business Profile (empty and with categories). Read the marketing offers line against the front desk on the day an offer ends.
**Expected:** Sections reachable and read sensibly; offers line matches the front desk.
**Why human:** axe covers part of WCAG 2.2 AA only. Logged as WINDOWS.md entries 20 to 24 per Ian's standing instruction.

#### 2. Postgres leg in CI

**Test:** Push the branch and check the pgsql job of `.github/workflows/tests.yml`.
**Expected:** All five room boot tests and the suite pass on Postgres 17.
**Why human:** No local Postgres; success criterion 1 names Postgres explicitly.

### Gaps Summary

No gaps. Every roadmap success criterion is satisfied by code I read and by tests I ran. The status is `human_needed` only because of the two items above (the accessibility walks Ian deferred on purpose, and the Postgres CI leg). The three open review warnings are worth a decision from Ian, W1 most of all, but none breaks a must-have.

---

_Verified: 2026-10-26_
_Verifier: Claude (gsd-verifier)_
