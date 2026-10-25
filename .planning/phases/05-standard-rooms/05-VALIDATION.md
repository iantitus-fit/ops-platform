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
| TBD | TBD | TBD | ROOM-08 | TBD | Company knowledge boots empty (required files kept) and with example; contract owns equals the SPEC rows | feature + browser axe | `php artisan test --compact tests/Feature/Rooms/CompanyKnowledgeBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-09 | TBD | Marketing boots; section's live offers equal the `OfferSentences` answer; existing offer and lead-source suites pass | feature | `php artisan test --compact tests/Feature/Rooms/MarketingBootTest.php tests/Feature/Icm/OfferValidationTest.php` | ❌ W0 (boot), ✅ (offers) | ⬜ pending |
| TBD | TBD | TBD | ROOM-10 | TBD | Content generation boots empty and with brief; second brief refused; `connector: none` | feature | `php artisan test --compact tests/Feature/Rooms/ContentGenerationBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-11 | TBD | GBP boots; categories shape checks; slot resolves to the fake; `[none]` refused, `none` accepted; nothing reaches Google | feature + Http fake | `php artisan test --compact tests/Feature/Rooms/GoogleBusinessProfileBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-14 | TBD | Money boots; card (Luhn) and 9-digit refusals; phones skipped; no slot, no records | feature | `php artisan test --compact tests/Feature/Rooms/MoneyBootTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | G-04-1 (D-19) | TBD | Hidden-character names refused; marks on file prefixes and quoted values; NFC and NFD `café.md` accepted | feature | `php artisan test --compact tests/Feature/Icm/HiddenCharacterTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-02 | TBD | Never-left-out line once per room; per-file lines for that room skipped | feature | `php artisan test --compact tests/Feature/Rooms/NeverLeftOutTest.php` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ROOM-05 on Postgres | — | Same boot tests on `pgsql` | CI matrix | push; CI `php artisan test` with `DB_CONNECTION=pgsql` | CI only | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Rooms/NeverLeftOutTest.php` — D-02 and the router wildcard skip
- [ ] `tests/Feature/Icm/HiddenCharacterTest.php` — D-19, including NFC and NFD `café.md`, ZWJ, RLO, U+2028, malformed UTF-8
- [ ] One boot test per room, modelled on `PretendRoomBootTest.php`'s `it('boots the pretend room'` block, each ending with the `RoomSectionAxe` check
- [ ] Fixes to the existing cases RESEARCH.md lists, each assigned to the plan whose room causes it (04 D-16)
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
