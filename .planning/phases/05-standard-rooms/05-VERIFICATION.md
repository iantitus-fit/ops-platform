---
phase: 05-standard-rooms
verified: 2026-10-26T19:00:00Z
status: passed
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
  - "app/Icm/Validator.php"
  - "app/Rooms/CompanyKnowledge/CompanyKnowledgeSection.php"
  - "app/Rooms/CompanyKnowledge/CompanyKnowledgeServiceProvider.php"
  - "app/Rooms/ContentGeneration/ContentBriefType.php"
  - "app/Rooms/ContentGeneration/ContentGenerationSection.php"
  - "app/Rooms/ContentGeneration/ContentGenerationServiceProvider.php"
  - "app/Rooms/GoogleBusinessProfile/FakeGoogleListingSource.php"
  - "app/Rooms/GoogleBusinessProfile/GbpCategoriesType.php"
  - "app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileSection.php"
  - "app/Rooms/GoogleBusinessProfile/GoogleBusinessProfileServiceProvider.php"
  - "app/Rooms/GoogleBusinessProfile/GoogleListingSource.php"
  - "app/Rooms/Marketing/MarketingSection.php"
  - "app/Rooms/Marketing/MarketingServiceProvider.php"
  - "app/Rooms/Money/AccountNumberScan.php"
  - "app/Rooms/Money/MoneyNotesType.php"
  - "app/Rooms/Money/MoneySection.php"
  - "app/Rooms/Money/MoneyServiceProvider.php"
  - "app/Rooms/Money/NumberCheckCouldNotFinish.php"
  - "app/Rooms/Room.php"
  - "app/Rooms/RoomFileType.php"
  - "app/Rooms/RoomRegistry.php"
  - "app/Rooms/RoomSection.php"
  - "bootstrap/providers.php"
  - "examples/example-hvac/CLAUDE.md"
  - "examples/example-hvac/connectors/google-business-profile.md"
  - "examples/example-hvac/rooms/company_knowledge/CONTEXT.md"
  - "examples/example-hvac/rooms/content_generation/CONTEXT.md"
  - "examples/example-hvac/rooms/content_generation/brief.md"
  - "examples/example-hvac/rooms/google_business_profile/CONTEXT.md"
  - "examples/example-hvac/rooms/google_business_profile/categories.md"
  - "examples/example-hvac/rooms/marketing/CONTEXT.md"
  - "examples/example-hvac/rooms/money/CONTEXT.md"
  - "examples/example-hvac/rooms/money/notes.md"
covered_digest: "v3:sha256:1c70bf6ec733acde6e078e88d3fcd284bd73ba9b0b9f71bd8b8d3edf3d23072e"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: human_needed
  previous_score: 4/4
  gaps_closed: []
  gaps_remaining: []
  regressions: []
---

# Phase 5: Standard Rooms Verification Report

**Phase Goal:** The rooms that need only the standard join the example business: marketing, content generation, Google Business Profile, company knowledge and money, each a folder the owner can open and furnish.
**Verified:** 2026-10-26
**Status:** passed
**Re-verification:** Yes. The earlier report was stale (covered source changed after it: review fixes WR-01..WR-03, IN-01..IN-05, D-28 to D-30, and the UI-review fix D-32). This report is regenerated against the current code, and the covered fingerprint was recomputed through `verification.fingerprint`.

All four roadmap success criteria hold in the current code. I did not rely on SUMMARY.md. I read the five room providers, `RoomSection`, `MarketingSection`, `AccountNumberScan`, `MoneyNotesType`, the connector slot interface, the example folder's room contracts and connector file, `bootstrap/providers.php`, and the review and UAT records. I ran the room and ICM tests myself (`tests/Feature/Rooms`, `tests/Unit/Rooms`, `tests/Unit/Icm`: 422 passed, 1789 assertions). The orchestrator's earlier full-suite run (2196 passed, 16948 assertions) is taken as given evidence.

## Goal Achievement

### Observable Truths (ROADMAP success criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `icm:sync` on `examples/example-hvac` activates a version with the five room folders, each with `CONTEXT.md` and one example file; reducing any room folder to `CONTEXT.md` alone still syncs; each boot test passes on SQLite and Postgres | VERIFIED | `examples/example-hvac/rooms/` holds company_knowledge, marketing, content_generation (`brief.md`), google_business_profile (`categories.md`), money (`notes.md`), plus `connectors/google-business-profile.md`. Company knowledge and marketing own files the sync requires, so their "example file" is the existing files they claim (D-03). Five `*BootTest.php` files in `tests/Feature/Rooms` run each room empty and with the example folder and pass here. `05-UAT.md` test 2 records CI run #10 on 755957f passing on SQLite and Postgres. |
| 2 | Owner sees marketing, content generation, GBP and company knowledge sections on the dashboard; money meets the full standard (module, boot test empty and with example) with no payment-link slot and no payment records | VERIFIED | All five providers extend `RoomServiceProvider`, return a `Room` with `sections: [...Section::class]`, and are listed in `bootstrap/providers.php` after `AppServiceProvider`/`AdminPanelProvider`. `RoomSection::canView()` asks `ActiveConfig::hasRoom()`; empty line is platform-drawn; D-32 try/catch in `getViewData()` makes a failing section show one line and not take down `/admin`. `MoneyServiceProvider` passes no `slot`, `slotInterface`, tables or timeline entries; no migration for payments exists (latest migration is the unrelated 2026_10_22 activities columns); `MoneyBootTest` includes 'adds no payment table or record'. |
| 3 | Marketing owns `business/lead-sources.md` and `business/offers/`, both still where they are; offers and lead sources behave as before; existing suite passes unchanged | VERIFIED | `rooms/marketing/CONTEXT.md` owns `[business/lead-sources.md, business/offers/*.md]`; `MarketingServiceProvider` uses `handles: ['lead_sources','offer']` with no new file type. `git diff` across the phase shows no change under `app/SendRule`. `MarketingSection` takes "live today" from `OfferSentences::liveToday()`, the front desk's own code (D-17). The only edits to offer and classifier tests are the D-24 count changes. UAT checked the offers line on the day an offer ends. |
| 4 | GBP room has a connector slot (interface plus fake driver) and a connector file declaring `owns` and `reads`; with only the fake nothing reaches Google; content generation's `CONTEXT.md` says it has no connector | VERIFIED | `GoogleListingSource` has one read-only method `fetchListing()`; `FakeGoogleListingSource` is bound in `GoogleBusinessProfileServiceProvider::register()`, and an unknown driver throws. `connectors/google-business-profile.md` has `slot: google_business_profile`, `owns: none`, `reads: none` (bare words, D-10). Grep for curl, `Http::`, Guzzle, googleapis under `app/Rooms` finds nothing. Content generation `CONTEXT.md` has `connector: none` and the line "It has no connector and no outside system." |

**Score:** 4/4 truths verified (0 behavior-unverified)

### Phase-level decisions checked beyond the roadmap criteria

| Decision | Status | Evidence |
|----------|--------|----------|
| D-02 never-left-out (company knowledge, marketing) | VERIFIED | `neverLeftOutBecause` set on both providers; `NeverLeftOutTest` passes. Content generation, GBP and money stay optional. |
| D-27 / D-29 single-file types (brief, notes, categories) | VERIFIED | Each type implements `singleFileLabel()` and `singleFileDefaultPath()` (`MoneyNotesType` read: 'money notes file', `rooms/money/notes.md`); `SingleFileTypeTest` and the boot tests pass. |
| D-19 / D-25 hidden characters | VERIFIED | `HiddenCharacters`, `Loader`, `ValidationReport` wired; `HiddenCharacterTest` passes. |
| D-16 / D-22 / D-26 / D-28 / D-30 money notes number refusal | VERIFIED as decided (see WR-01 below) | `AccountNumberScan` reads spans joined by dot, Unicode dash or horizontal whitespace with at most one line break; hits on 9-digit spans (ZIP+4 excepted) and 13 to 19 digit spans passing the check digit; a scan that cannot finish throws `NumberCheckCouldNotFinish` and `MoneyNotesType::checks()` refuses with a fixed sentence that never quotes a digit. I probed `4111-1111-1111-1111` (refused). |
| D-32 section failure isolation | VERIFIED | `RoomSection::getViewData()` wraps empty-line and content building in try/catch, reports, and shows `FAILED_LINE`. Test in `RoomSectionTest` passes. |
| D-23 / D-24 moved and re-counted tests | VERIFIED | `tests/Unit/Icm/RoomChecksTest.php` exists; counts use `exampleFolderFileCount()`. |

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Rooms/{CompanyKnowledge,Marketing,ContentGeneration,GoogleBusinessProfile,Money}/*ServiceProvider.php` | VERIFIED | Real `Room` descriptions, registered in `bootstrap/providers.php` |
| `*Section.php` x5 | VERIFIED | Extend `RoomSection`; read the live version through `ActiveConfig`; no stub lines |
| `ContentBriefType`, `GbpCategoriesType`, `MoneyNotesType`, `AccountNumberScan`, `NumberCheckCouldNotFinish` | VERIFIED | Substantive, wired through `Room::fileTypes` |
| `GoogleListingSource`, `FakeGoogleListingSource` | VERIFIED | Interface bound to the fake; no outbound code |
| `app/Icm/HiddenCharacters.php`, `Loader`, `ValidationReport`, `Validator`, `RoomChecks` edits | VERIFIED | Wired and exercised by tests |
| `examples/example-hvac/rooms/*`, `connectors/google-business-profile.md` | VERIFIED | Present, fictional, loaded by the sync |

### Key Link Verification

| From | To | Status | Details |
|------|----|--------|---------|
| `bootstrap/providers.php` | five room providers | WIRED | All five listed |
| Room `sections` | `/admin` dashboard | WIRED | Boot tests see `data-room-section` markup and furnished lines; axe clean |
| `MarketingSection` | `OfferSentences::liveToday()` | WIRED | Same code the front desk uses |
| `GoogleListingSource` | `FakeGoogleListingSource` | WIRED | Container binding in provider `register()` |
| `MoneyNotesType::checks()` | `AccountNumberScan` | WIRED | Whole file scanned |
| `singleFileLabel()` | `RoomChecks` single-file check | WIRED | Tests refuse a second file |

### Data-Flow Trace (Level 4)

| Artifact | Data | Source | Real data | Status |
|----------|------|--------|-----------|--------|
| `CompanyKnowledgeSection` | service count, FAQ | `ActiveConfig` | Yes, live version | FLOWING |
| `MarketingSection` | lead source count, live offers | `ActiveConfig::leadSources()`, `OfferSentences` | Yes | FLOWING |
| `ContentGenerationSection`, `MoneySection` | brief / notes in place | `ActiveConfig` files of type | Yes | FLOWING |
| `GoogleBusinessProfileSection` | primary category, connected flag | gbp_categories file, config (driver not built, WR-02 fixed) | Yes (listing not fetched from Google by design, D-11) | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Room and ICM tests | `php artisan test --compact tests/Feature/Rooms tests/Unit/Rooms tests/Unit/Icm` | 422 passed, 1789 assertions | PASS |
| Full suite (given by orchestrator) | `php artisan test` | 2196 passed, 16948 assertions | PASS (not re-run) |
| Number scan, ASCII hyphens | tinker `findsAccountNumber("Card 4111-1111-1111-1111")` | true | PASS |
| Number scan, invalid-UTF-8 separator | tinker `findsAccountNumber("Card 4111\x961111\x961111\x961111")` | false | Confirms WR-01 |

### Probe Execution

Step 7c: SKIPPED. The phase declares no probe scripts.

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|-------------|--------|----------|
| ROOM-08 | 05-01, 05-02, 05-03, 05-04, 05-05 | Company knowledge room to the standard | SATISFIED | Provider, section, contract, boot test, never-left-out |
| ROOM-09 | 05-01, 05-02, 05-05 | Marketing room, owns lead sources and offers | SATISFIED | Provider, contract, section reusing `OfferSentences`; offer suite unchanged |
| ROOM-10 | 05-01, 05-06 | Content generation room, no connector | SATISFIED | Provider, `ContentBriefType`, single-file rule, `connector: none` |
| ROOM-11 | 05-01, 05-08, 05-09 | GBP room with read-only slot and fake driver | SATISFIED | Slot, fake, `GbpCategoriesType`, connector file `owns: none` / `reads: none` |
| ROOM-14 | 05-01, 05-07 | Money room to the standard, no slot, no payment records | SATISFIED | Provider, `MoneyNotesType`, boot test empty and with notes, no payment tables |

Orphan check: REQUIREMENTS.md maps exactly ROOM-08, 09, 10, 11 and 14 to Phase 5 (all marked Complete), and every ID appears in at least one PLAN's `requirements` (05-01 lists all five). No orphaned IDs. CONN-01 is a Future Requirement that Phase 5 touches only by decision D-15; it is not a Phase 5 requirement.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| (phase source files) | n/a | TBD/FIXME/XXX/TODO/HACK grep across `app`, `bootstrap`, `examples` files changed in the phase | none found | Debt-marker gate clear |

No stubs, empty returns or console-only handlers found in `app/Rooms/*`.

### Judgment on the open code-review warning (05-REVIEW.md)

**WR-01: non-UTF-8 bytes between card or ID digit groups get past `AccountNumberScan` (WARNING, does not fail a must-have).** I reproduced it: `Card 4111<0x96>1111<0x96>1111<0x96>1111` returns false, while the same line with ASCII hyphens returns true. The cause is `mb_scrub()` turning each invalid byte into `?`, which is not a span separator.

Why it does not fail a must-have:
- No roadmap success criterion mentions the number check. SC2 for money asks for module, boot tests and no payment slot or records, all verified.
- The plan-level truths for 05-07 (D-16, D-22, D-26, D-28) define separators as dots, dashes and horizontal whitespace and require tests through `icm:sync`; the code meets each as written. Stray invalid bytes from a legacy Windows-1252 file are outside those wordings, and D-31 already records accepted misses of the same kind.
- The room's own `CONTEXT.md` tells the owner that the check cannot see everything, and the CONN-01 no-secrets scan (a later requirement) is the backstop.

Why it still matters: it works against the check's stated purpose (lean toward refusing), and the fix is small (turn invalid bytes into spaces before scanning, add two test cases). Recommend Ian either fixes it in a follow-up or records it as an accepted miss next to D-31 before real money notes are used. It does not block closing the phase. The two info items (IN-01, IN-02) are likewise outside every must-have; `05-REVIEW-DISPOSITION.md` still lists WR-01, IN-01, IN-02 and IN-03 as `open`, and IN-03 is in fact already fixed in code (`GbpCategoriesTest`), so that row should be updated.

### Human Verification

The two manual-only items from the earlier report are now recorded in `05-UAT.md` (status complete, 2 passed, 0 issues):

1. Keyboard and screen reader walk of the five sections: pass. Per Ian's call, the screen reader structure was checked from Chrome's accessibility tree (each section a region with a real H2, lines in on-screen order, no focusable elements, 200% zoom clean) instead of a live VoiceOver walk. The full VoiceOver walk is carried by Phase 11 (A11Y-03). This is a recorded, accepted substitution, not an unresolved item.
2. Postgres CI leg: pass, CI run #10 on 755957f passed on SQLite and Postgres.

No human verification items remain open, so the status is `passed`.

### Gaps Summary

No gaps. Every roadmap success criterion is met by code I read and tests I ran. WR-01 is a recorded warning with a recommended small follow-up, and the live VoiceOver walk is formally deferred to Phase 11.

---

_Verified: 2026-10-26_
_Verifier: Claude (gsd-verifier)_
