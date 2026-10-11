---
phase: "1"
slug: "foundation"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-10-10"
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 + pest-plugin-laravel 5.0.1 (+ pest-plugin-arch); Livewire testing via `Livewire\Livewire::test()` |
| **Config file** | `phpunit.xml` (Unit + Feature suites; sqlite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`); `tests/Pest.php` |
| **Quick run command** | `php artisan test --compact <narrowest test file or dir for the task>` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run the narrowest test file(s) for the task with `php artisan test --compact <path>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

Filled in from the PLAN.md files (seven plans, one per wave; waves are serial because every plan appends to the docs/sprints/sprint-0.md Walk-through log and execution is sequential with no worktrees). Requirement → test file mapping from RESEARCH.md:

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 01-01-T1 | 01 | 1 | ICM-01 | T-01-05 | generated records/changelog ignored by git | feature (tracer) | `php artisan test --compact tests/Feature/Icm/SyncTest.php` | ✅ | ✅ green |
| 01-01-T2 | 01 | 1 | ICM-01 | T-01-01, T-01-04 | Sync never reads `records/`; lock blocks concurrent syncs | feature | `php artisan test --compact tests/Feature/Icm/SyncTest.php tests/Feature/Icm/SyncIgnoresRecordsTest.php` | ✅ | ✅ green |
| 01-01-T3 | 01 | 1 | ICM-01, QUAL-01 | T-01-02, T-01-03 | Yaml/Finder only in Loader; rules recorded | feature + arch | `php artisan test --compact tests/Feature/Icm/ActiveConfigTest.php tests/Feature/Quality/ProjectRulesTest.php tests/Unit/ArchitectureTest.php` | ✅ | ✅ green |
| 01-02-T1 | 02 | 2 | ICM-02 | T-01-06 | rejected candidate never activates | feature (tracer) | `php artisan test --compact tests/Feature/Icm/SyncTest.php` | ✅ | ✅ green |
| 01-02-T2 | 02 | 2 | ICM-02 | — | N/A | feature | `php artisan test --compact tests/Feature/Icm/ValidatorTest.php` | ✅ | ✅ green |
| 01-02-T3 | 02 | 2 | ICM-02 | T-01-07, T-01-09 | echoed values truncated; every attempt recorded | feature | `php artisan test --compact tests/Feature/Icm/ChangelogTest.php` | ✅ | ✅ green |
| 01-03-T1 | 03 | 3 | LEAD-01, LEAD-03 | T-01-13, T-01-SC | explicit DTO fields; approved package only | feature (tracer) | `php artisan test --compact tests/Feature/Inbound/FakeTextWebhookTest.php` | ✅ | ✅ green |
| 01-03-T2 | 03 | 3 | LEAD-01 | T-01-12 | duplicate MessageSid recorded once | feature | `php artisan test --compact tests/Feature/Inbound/FakeTextWebhookTest.php` | ✅ | ✅ green |
| 01-03-T3 | 03 | 3 | LEAD-01 | T-01-10, T-01-11, T-01-14 | 404 unless text driver is `fake`; CSRF exempt `webhooks/*`; no bodies in logs outside local | feature | `php artisan test --compact tests/Feature/Inbound` | ✅ | ✅ green |
| 01-04-T1 | 04 | 4 | LEAD-02, LEAD-03 | — | N/A | feature (tracer) | `php artisan test --compact tests/Feature/Inbound/LeadFormTest.php` | ✅ | ✅ green |
| 01-04-T2 | 04 | 4 | LEAD-03 | T-01-17 | a form never overwrites a stored phone/email | feature | `php artisan test --compact tests/Feature/Inbound/ContactMatchingTest.php tests/Feature/Inbound/LeadFormTest.php` | ✅ | ✅ green |
| 01-04-T3 | 04 | 4 | LEAD-02 | T-01-16, T-01-19 | throttled; 422 without phone/email; CSRF exempt `leads` | feature | `php artisan test --compact tests/Feature/Inbound` | ✅ | ✅ green |
| 01-05-T1 | 05 | 5 | RECS-01 | T-01-24 | atomic writes | feature (tracer) | `php artisan test --compact tests/Feature/Records/CustomerCardTest.php` | ✅ | ✅ green |
| 01-05-T2 | 05 | 5 | RECS-01 | T-01-21, T-01-22 | customer text blockquoted; slug+id filenames | feature | `php artisan test --compact tests/Feature/Records` | ✅ | ✅ green |
| 01-05-T3 | 05 | 5 | RECS-01 | T-01-23, T-01-25 | table cells escaped; summaries under lock; cards gitignored | feature | `php artisan test --compact tests/Feature/Records` | ✅ | ✅ green |
| 01-06-T1 | 06 | 6 | LEAD-04, DASH-01 | T-01-26 | `FilamentUser` users reach `/admin` | feature (tracer, Livewire + HTTP) | `php artisan test --compact tests/Feature/Inbound/OwnerReplyTest.php` | ✅ | ✅ green |
| 01-06-T2 | 06 | 6 | LEAD-04, DASH-01 | T-01-27, T-01-28, T-01-30 | escaped thread; failed sends never stored as sent | feature (Livewire) | `php artisan test --compact tests/Feature/Inbound/OwnerReplyTest.php tests/Feature/Filament/ConversationResourceTest.php` | ✅ | ✅ green |
| 01-06-T3 | 06 | 6 | DASH-01 | T-01-26 | guest redirected; owner gets 200 outside local | feature (Livewire + HTTP) | `php artisan test --compact tests/Feature/Filament tests/Feature/Inbound/OwnerReplyTest.php` | ✅ | ✅ green |
| 01-07-T1 | 07 | 7 | DASH-02 | T-01-31 | `/dev/chat` 404 outside `local` (route and component) | feature (tracer) | `php artisan test --compact tests/Feature/DevChatTest.php` | ✅ | ✅ green |
| 01-07-T2 | 07 | 7 | DASH-01 | T-01-33, T-01-34 | escaped errors; lock-aware Sync Now | feature (Livewire + HTTP) | `php artisan test --compact tests/Feature/Filament/BusinessFolderPageTest.php` | ✅ | ✅ green |
| 01-07-T3 | 07 | 7 | QUAL-01 | T-01-03 | integer cents; no config read from disk outside the sync | feature + arch | `php artisan test --compact tests/Feature/Quality tests/Unit/ArchitectureTest.php` then `php artisan test --compact` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Pest.php` — global `beforeEach` copying `examples/example-hvac` to a temp dir and setting `config(['icm.path' => …])`, `afterEach` cleanup; helpers `activateExampleFolder()` and `breakFile(string $path, callable $mutate)`
- [x] `phpunit.xml` — `ICM_PATH` env pointing at a nonexistent path so a missed override fails loudly
- [x] `tests/Feature/{Icm,Inbound,Records,Filament,Quality}/…` and `tests/Unit/ArchitectureTest.php` (created with `php artisan make:test --pest`)
- [x] `examples/example-hvac/` fixture exists before any sync test (plan 01-01's first task)
- [x] Factories for `Contact`, `Conversation`, `Message`, `Activity`, `IcmVersion`
- [x] `composer require giggsey/libphonenumber-for-php-lite` — the only new dependency

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Real (non-test) POSTs pass CSRF | LEAD-01, LEAD-02 | `PreventRequestForgery` skips itself under unit tests | `curl -X POST` to `/leads` and `/webhooks/text/fake` against `php artisan serve`; expect not-419 |
| Records gitignore rules | RECS-01 | Depends on the working tree and `core.ignorecase` | `git check-ignore -v` on a rendered card, `records/README.md`, `records/CLAUDE.md`, and `app/Records/…` |
| Sync against the real example folder | ICM-01 | Writes the tracked `changelog.md` | `php artisan icm:sync` then `php artisan icm:status` on `examples/example-hvac` |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** validated 2026-10-11 (validate-phase audit: 21/21 tasks green; full suite 263 passed)

## Validation Audit 2026-10-11

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
