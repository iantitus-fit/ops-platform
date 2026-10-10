---
phase: "1"
slug: "foundation"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
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

Filled in from the PLAN.md files once the planner assigns task IDs. Requirement → test file mapping from RESEARCH.md:

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | 01 | 1 | ICM-01 | — | N/A | feature | `php artisan test --compact tests/Feature/Icm/SyncTest.php tests/Feature/Icm/ActiveConfigTest.php` | ❌ W0 | ⬜ pending |
| TBD | 01 | 1 | ICM-02 | — | Sync never reads `records/` | feature | `php artisan test --compact tests/Feature/Icm/ValidatorTest.php tests/Feature/Icm/SyncIgnoresRecordsTest.php` | ❌ W0 | ⬜ pending |
| TBD | 02 | 2 | LEAD-01 | fake webhook gated to `fake` driver | 404 unless text driver is `fake`; CSRF exempt | feature | `php artisan test --compact tests/Feature/Inbound/FakeTextWebhookTest.php` | ❌ W0 | ⬜ pending |
| TBD | 02 | 2 | LEAD-02 | `/leads` abuse | throttled; 422 without phone/email | feature | `php artisan test --compact tests/Feature/Inbound/LeadFormTest.php` | ❌ W0 | ⬜ pending |
| TBD | 02 | 2 | LEAD-03 | — | N/A | feature | `php artisan test --compact tests/Feature/Inbound/ContactMatchingTest.php` | ❌ W0 | ⬜ pending |
| TBD | 03 | 3 | LEAD-04 | — | N/A | feature (Livewire) | `php artisan test --compact tests/Feature/Inbound/OwnerReplyTest.php` | ❌ W0 | ⬜ pending |
| TBD | 03 | 3 | RECS-01 | card prompt injection | customer text blockquoted; table cells escaped | feature | `php artisan test --compact tests/Feature/Records` | ❌ W0 | ⬜ pending |
| TBD | 03 | 3 | DASH-01 | admin access | only `FilamentUser` users reach `/admin` | feature (Livewire + HTTP) | `php artisan test --compact tests/Feature/Filament` | ❌ W0 | ⬜ pending |
| TBD | 03 | 3 | DASH-02 | dev page exposure | `/dev/chat` 404 outside `local` | feature | `php artisan test --compact tests/Feature/DevChatTest.php` | ❌ W0 | ⬜ pending |
| TBD | 03 | 3 | QUAL-01 | — | N/A | feature + arch | `php artisan test --compact tests/Feature/Quality tests/Unit/ArchitectureTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Pest.php` — global `beforeEach` copying `examples/example-hvac` to a temp dir and setting `config(['icm.path' => …])`, `afterEach` cleanup; helpers `activateExampleFolder()` and `breakFile(string $path, callable $mutate)`
- [ ] `phpunit.xml` — `ICM_PATH` env pointing at a nonexistent path so a missed override fails loudly
- [ ] `tests/Feature/{Icm,Inbound,Records,Filament,Quality}/…` and `tests/Unit/ArchitectureTest.php` (created with `php artisan make:test --pest`)
- [ ] `examples/example-hvac/` fixture exists before any sync test (plan 01-01's first task)
- [ ] Factories for `Contact`, `Conversation`, `Message`, `Activity`, `IcmVersion`
- [ ] `composer require giggsey/libphonenumber-for-php-lite` — the only new dependency

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Real (non-test) POSTs pass CSRF | LEAD-01, LEAD-02 | `PreventRequestForgery` skips itself under unit tests | `curl -X POST` to `/leads` and `/webhooks/text/fake` against `php artisan serve`; expect not-419 |
| Records gitignore rules | RECS-01 | Depends on the working tree and `core.ignorecase` | `git check-ignore -v` on a rendered card, `records/README.md`, `records/CLAUDE.md`, and `app/Records/…` |
| Sync against the real example folder | ICM-01 | Writes the tracked `changelog.md` | `php artisan icm:sync` then `php artisan icm:status` on `examples/example-hvac` |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
