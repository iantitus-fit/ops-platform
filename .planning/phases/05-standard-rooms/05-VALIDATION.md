---
phase: "5"
slug: "standard-rooms"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-10-25"
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 with `pest-plugin-browser` 5.1.2 (axe), on PHPUnit |
| **Config file** | `phpunit.xml`; shared setup in `tests/Pest.php` (Feature tests copy `examples/example-hvac` to a temp folder) |
| **Quick run command** | `php artisan test --compact tests/Feature/Rooms` |
| **Full suite command** | `php artisan test --compact` (CI runs `php artisan test` on both `sqlite` and `pgsql`) |
| **Estimated runtime** | ~130 seconds for the full suite locally (measured in the research prototype) |

---

## Sampling Rate

- **After every task commit:** Run the narrowest file for the task, `php artisan test --compact <file>`
- **After every plan wave:** Run `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm`
- **Before `/gsd-verify-work`:** Full suite must be green on SQLite locally, then CI green on both databases
- **Max feedback latency:** 130 seconds

---

## Per-Task Verification Map

Requirement-level map seeded from RESEARCH.md § Validation Architecture. Task IDs are filled in from the plans.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-T1 | 05-01 | 1 | ROOM-08 (suite prep) | T-05-01 | Annex test room narrowed; ownership cases hold before and after rooms land; unit cases for two branches | feature + unit | `php artisan test --compact tests/Feature/Rooms/RoomOwnershipTest.php tests/Feature/Rooms/ConnectorFileTest.php tests/Feature/Rooms/OrderIndependenceTest.php tests/Unit/Icm/RoomChecksTest.php` | ❌ W0 (unit) | ⬜ pending |
| 05-01-T2 | 05-01 | 1 | ROOM-10, ROOM-11, ROOM-14 (suite prep) | T-05-01 | Room, type and slot lists built from code; case-insensitive near-miss fixed | feature | `php artisan test --compact tests/Feature/Rooms tests/Feature/Icm/ValidatorTest.php tests/Unit` | ✅ | ⬜ pending |
| 05-02-T1 | 05-02 | 1 | D-02 (ROOM-08, ROOM-09) | T-05-03 | Never-left-out line once per room; per-file lines for that room skipped | unit | `php artisan test --compact tests/Unit/Icm/NeverLeftOutTest.php tests/Feature/Rooms/RoomOwnershipTest.php tests/Feature/Rooms/RoomContractTest.php` | ❌ W0 | ⬜ pending |
| 05-02-T2 | 05-02 | 1 | ROOM-08 (Pitfall 1) | T-05-02 | A pattern in owns passes over routers; exact router names and walk tests still refused | feature | `php artisan test --compact tests/Feature/Rooms/RouterClaimTest.php tests/Feature/Rooms/RoomOwnershipTest.php tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| 05-03-T1 | 05-03 | 2 | ROOM-08 | T-05-04 | Company knowledge boots empty (required files kept) and with example; contract owns equals the SPEC rows; axe clean; D-23 unit case | feature + browser axe + unit | `php artisan test --compact tests/Feature/Rooms/CompanyKnowledgeBootTest.php tests/Unit/Icm/RoomChecksTest.php tests/Feature/Rooms/SpecTablesTest.php tests/Feature/Rooms/RouterClaimTest.php` | ❌ W0 | ⬜ pending |
| 05-03-T2 | 05-03 | 2 | ROOM-08 | T-05-05 | Tests company knowledge changes, named; D-23 removal; D-24 counts | feature | `php artisan test --compact` | ✅ | ⬜ pending |
| 05-04-T1 | 05-04 | 3 | G-04-1 (D-19) | T-05-06, T-05-08 | Hidden-character names refused; marks in every stored and printed line; malformed UTF-8 safe | unit + feature | `php artisan test --compact tests/Unit/Icm/HiddenCharactersTest.php tests/Feature/Icm/HiddenCharacterTest.php tests/Feature/Icm/ValidatorTest.php tests/Feature/Icm/LoaderTest.php` | ❌ W0 | ⬜ pending |
| 05-04-T2 | 05-04 | 3 | G-04-1 (D-19, D-25) | T-05-06, T-05-07 | Every category, folders and README files refused; NFC and NFD `café.md` accepted; quoted values marked | feature | `php artisan test --compact tests/Feature/Icm/HiddenCharacterTest.php tests/Feature/Rooms/RoomContractTest.php tests/Feature/Rooms tests/Unit/Icm` | ❌ W0 | ⬜ pending |
| 05-05-T1 | 05-05 | 3 | ROOM-09 | T-05-09 | Marketing boots; section's live offers equal the `OfferSentences` answer at four moments; axe clean | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/MarketingBootTest.php tests/Feature/Rooms/CompanyKnowledgeBootTest.php tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| 05-05-T2 | 05-05 | 3 | D-02 (ROOM-08, ROOM-09) | T-05-10 | Never-left-out through the sync for both rooms; offer, lead-source and price suites pass unchanged | feature | `php artisan test --compact tests/Feature/Rooms/NeverLeftOutTest.php tests/Unit/Icm/NeverLeftOutTest.php` | ❌ W0 | ⬜ pending |
| 05-06-T1 | 05-06 | 4 | ROOM-10 | T-05-12 | Content generation boots empty and with brief; axe clean | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/ContentGenerationBootTest.php tests/Feature/Rooms/SpecTablesTest.php tests/Feature/Rooms/RoomFileTypeTest.php tests/Feature/Icm/ValidatorTest.php` | ❌ W0 | ⬜ pending |
| 05-06-T2 | 05-06 | 4 | ROOM-10 (D-27) | T-05-20 | A second file of a single-file room type refused wherever the contract's owns points (shipped, two-path and pattern contracts), one line naming both files; previous version live; one brief accepted; pretend notes keep any number | unit + feature | `php artisan test --compact tests/Unit/Icm/SingleFileTypeTest.php tests/Feature/Rooms/ContentGenerationBootTest.php tests/Feature/Rooms/RoomFileTypeTest.php tests/Unit/Rooms/RoomRegistryTest.php tests/Feature/Rooms/RoomOwnershipTest.php` | ❌ W0 (unit) | ⬜ pending |
| 05-06-T3 | 05-06 | 4 | ROOM-10 | T-05-11, T-05-12 | Brief outside its folder refused; `connector: none` enforced; no agent file; SPEC rows | feature | `php artisan test --compact tests/Feature/Rooms/ContentGenerationBootTest.php tests/Feature/Rooms/SpecTablesTest.php` | ✅ | ⬜ pending |
| 05-07-T1 | 05-07 | 5 | ROOM-14 (D-27) | T-05-21, T-05-SC | Money boots empty and with notes; no slot, no tables, no migration; a second notes file refused under the shipped and an edited contract; axe clean | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/MoneyBootTest.php tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| 05-07-T2 | 05-07 | 5 | ROOM-14 (D-16, D-22, D-26) | T-05-13, T-05-14, T-05-15 | Card (Luhn) refusals; every 9-digit span refused however grouped, a dashed ZIP+4 excepted; a leading-+ 9-digit span passes only as a phone; phones pass; one line, no digits echoed | feature | `php artisan test --compact tests/Feature/Rooms/MoneyNotesCheckTest.php tests/Feature/Rooms/MoneyBootTest.php` | ❌ W0 | ⬜ pending |
| 05-08-T1 | 05-08 | 6 | ROOM-11 | T-05-16 | GBP boots empty on a read-only fake slot; nothing reaches Google; dashboard never fetches; axe clean | feature + Http fake + browser axe | `php artisan test --compact tests/Feature/Rooms/GoogleBusinessProfileBootTest.php tests/Feature/Rooms/SpecTablesTest.php` | ❌ W0 | ⬜ pending |
| 05-08-T2 | 05-08 | 6 | ROOM-11 (D-10, D-23) | T-05-17, T-05-18 | Connector file owns and reads none; `[none]` refused; D-23 slots test to unit | unit + feature | `php artisan test --compact tests/Unit/Icm/ConnectorChecksTest.php tests/Feature/Rooms/GoogleBusinessProfileBootTest.php tests/Feature/Rooms/ConnectorFileTest.php` | ❌ W0 (unit) | ⬜ pending |
| 05-08-T3 | 05-08 | 6 | ROOM-11 (D-06, D-27) | T-05-16, T-05-22 | Categories file furnishes the room; a second categories file refused under the shipped and an edited contract; primary category on the dashboard; SPEC rows | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/GoogleBusinessProfileBootTest.php tests/Feature/Rooms/SpecTablesTest.php tests/Feature/Rooms/PretendRoomBootTest.php` | ✅ | ⬜ pending |
| 05-09-T1 | 05-09 | 7 | ROOM-11 (D-07) | T-05-19 | At most nine additional categories | feature | `php artisan test --compact tests/Feature/Rooms/GbpCategoriesTest.php tests/Feature/Rooms/GoogleBusinessProfileBootTest.php` | ❌ W0 | ⬜ pending |
| 05-09-T2 | 05-09 | 7 | ROOM-11 (D-07) | T-05-19 | No repeats, none equal to the primary, ignoring case and spaces; one line per problem | feature | `php artisan test --compact tests/Feature/Rooms/GbpCategoriesTest.php tests/Feature/Rooms/GoogleBusinessProfileBootTest.php` | ✅ | ⬜ pending |
| phase gate | all | 7 | ROOM-05 on Postgres | — | Same boot tests on `pgsql` | CI matrix | Ian pushes the branch; CI `php artisan test` with `DB_CONNECTION=pgsql` | CI only | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/Icm/NeverLeftOutTest.php` (05-02) and `tests/Feature/Rooms/NeverLeftOutTest.php` (05-05) — D-02 at unit level, then through the sync for company knowledge and marketing
- [ ] `tests/Feature/Rooms/RouterClaimTest.php` (05-02) — the router wildcard skip (Pitfall 1)
- [ ] `tests/Unit/Icm/RoomChecksTest.php` (05-01, 05-03) and `tests/Unit/Icm/ConnectorChecksTest.php` (05-08) — branches real rooms make unreachable (D-23)
- [ ] `tests/Unit/Icm/SingleFileTypeTest.php` (05-06) — D-27 at unit level with a registry the test builds: a second file of a single-file type refused, the owned one kept, a null label keeping any number, a file outside the folder left to the placement line
- [ ] `tests/Unit/Icm/HiddenCharactersTest.php` and `tests/Feature/Icm/HiddenCharacterTest.php` (05-04) — D-19, including NFC and NFD `café.md`, ZWJ, RLO, U+2028, malformed UTF-8
- [ ] One boot test per room (05-03, 05-05, 05-06, 05-07, 05-08), modelled on `PretendRoomBootTest.php`'s `it('boots the pretend room'` block, each with the `RoomSectionAxe` check on both rows
- [ ] `tests/Feature/Rooms/MoneyNotesCheckTest.php` (05-07) and `tests/Feature/Rooms/GbpCategoriesTest.php` (05-09)
- [ ] Fixes to the existing cases RESEARCH.md lists: rewritten ahead of the rooms in 05-01, the rest named in the plan whose room causes them (04 D-16)
- [ ] Framework install: none

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Keyboard-only and VoiceOver walk of the first real room section | A11Y-01 (D-18) | Automated axe covers only part of WCAG | At UAT: sign in to /admin, reach each new room section by keyboard alone, then with VoiceOver; record any trap or unread content |
| Postgres leg of the boot tests | ROOM-05 | No local Postgres; CI runs both legs | Check the CI run on the phase branch shows both `sqlite` and `pgsql` legs green |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 130s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
