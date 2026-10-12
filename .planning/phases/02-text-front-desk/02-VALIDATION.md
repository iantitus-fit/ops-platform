---
phase: "02"
slug: "text-front-desk"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-10-11"
---

# Phase 02 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Source: `02-RESEARCH.md` § Validation Architecture. TDD mode is on, so each behaviour starts as a failing test.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.3.1 on PHPUnit (`laravel/pao` agent output) |
| **Config file** | `phpunit.xml` (SQLite in memory, sync queue, array cache); `tests/Pest.php` (RefreshDatabase, temp copy of `examples/example-hvac` per test) |
| **Quick run command** | `php artisan test --compact --filter=<Name>` (or a file path) |
| **Full suite command** | `php artisan test --compact` (SQLite). Postgres: `docker run --rm -d -p 55432:5432 -e POSTGRES_PASSWORD=probe -e POSTGRES_DB=ops_test postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=probe php artisan test --compact` |
| **Estimated runtime** | ~8 seconds on SQLite (281 tests at baseline) |

Baseline before Wave 0: SQLite 281 passed; Postgres 263 passed, 17 failed, 1 error.

---

## Sampling Rate

- **After every task commit:** the narrowest `php artisan test --compact --filter=<Name>` for the files touched, plus `vendor/bin/pint --dirty --format agent`
- **After every plan wave:** `php artisan test --compact` (SQLite)
- **Before `/gsd-verify-work`:** full suite green on SQLite **and** on the Postgres recipe above, then the manual items
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

Requirement-level map from research. The planner assigns task IDs; execution fills Status.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-02-T1, 02-02-T2 | 02-02 | 2 | AGNT-01, D-35 | T-02-04, T-02-05, T-02-06 | Broken agent file rejected in plain words; one agent per channel | feature | `php artisan test --compact tests/Feature/Icm/AgentFileTest.php` | ✅ | ✅ green |
| 02-04-T1 | 02-04 | 4 | D-08, D-09 | T-02-07, T-02-58 | price/promise/discount/complaint can never be in `send_on_own`; unknown key rejected | feature | `php artisan test --compact tests/Feature/Icm/SendingRulesValidationTest.php` | ✅ | ✅ green |
| 02-03-T1 | 02-03 | 3 | D-10..D-12 | T-02-08 | Fixed replies with dollar/discount/promise/`never_say` text rejected at sync | feature + unit | `php artisan test --compact tests/Feature/Icm/RepliesValidationTest.php tests/Unit/SendRule/CodeChecksTest.php` | ✅ | ✅ green |
| 02-09-T1, 02-13-T2, 02-14-T1 | 02-09, 02-13, 02-14 | 9, 13, 14 | AGNT-02, D-36, D-41 | T-02-16, T-02-22 | Agent never texts a number that has not texted in that conversation | feature | `php artisan test --compact tests/Feature/FrontDesk/ProcessInboundTest.php tests/Feature/FrontDesk/FrontDeskPromptTest.php` | ✅ | ✅ green |
| 02-18-T1, 02-18-T2 | 02-18 | 18 | AGNT-03, D-33, D-34 | T-02-34, T-02-36 | N/A | feature | `php artisan test --compact tests/Feature/FrontDesk/IntakeAnswersTest.php` | ✅ | ✅ green |
| 02-13-T3, 02-15-T2 | 02-13, 02-15 | 13, 15 | AGNT-04 | T-02-23, T-02-29 | Provider failure falls through; all fail → owner alerted, nothing sent | feature | `php artisan test --compact tests/Feature/FrontDesk/AgentFallbackTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php` | ✅ | ✅ green |
| 02-14-T1, 02-15-T1, 02-17-T1 | 02-14, 02-15, 02-17 | 14, 15, 17 | GATE-01, D-01..D-04, D-07 | T-02-26, T-02-27, T-02-28 | Scripted model labelling a price reply sendable still drafts; Jev failure holds | feature | `php artisan test --compact tests/Feature/FrontDesk/GateTest.php tests/Feature/FrontDesk/GateVerdictTest.php tests/Feature/FrontDesk/ProcessInboundTest.php` | ✅ | ✅ green |
| 02-16-T1 | 02-16 | 16 | GATE-01, D-07 | T-02-60 | A held draft reaches the owner with its plain reason and lands on the timeline | feature | `php artisan test --compact tests/Feature/FrontDesk/HeldDraftTest.php` | ✅ | ✅ green |
| 02-03-T1 | 02-03 | 3 | D-02 | T-02-08 | Code checks are a hard veto | unit | `php artisan test --compact tests/Unit/SendRule/CodeChecksTest.php` | ✅ | ✅ green |
| 02-06-T1, 02-07-T1, 02-07-T2 | 02-06, 02-07 | 6, 7 | D-03, D-05, D-06, D-45..D-47 (Jev driver) | T-02-10, T-02-11, T-02-12, T-02-13 | Fail closed on any error/missing answer; no name or phone sent to Jev | feature + unit | `php artisan test --compact tests/Feature/SendRule/JevClassifierTest.php tests/Unit/SendRule/TriageReadingTest.php` | ✅ | ✅ green |
| 02-15-T1, 02-20-T2 | 02-15, 02-20 | 15, 20 | GATE-02 | T-02-26 | Disclosure + sign-off; never a `never_say` phrase; ask for person → handoff + pause | feature | `php artisan test --compact tests/Feature/FrontDesk/DisclosureTest.php tests/Feature/FrontDesk/HandoffTest.php` | ✅ | ✅ green |
| 02-05-T1, 02-17-T2 | 02-05, 02-17 | 5, 17 | D-12..D-14 | T-02-09, T-02-32 | Holding-reply time is always true (computed from hours) | feature | `php artisan test --compact tests/Feature/Icm/BusinessClockTest.php tests/Feature/SendRule/FixedRepliesTest.php tests/Feature/FrontDesk/HoldingReplyTest.php` | ✅ | ✅ green |
| 02-21-T1, 02-21-T2 | 02-21 | 21 | D-15..D-17, D-46, D-49 | T-02-41, T-02-42, T-02-43 | Life-safety line sends first, even when paused; never gives repair steps | feature + unit | `php artisan test --compact tests/Feature/FrontDesk/LifeSafetyTest.php tests/Unit/SendRule/LifeSafetyTest.php` | ✅ | ✅ green |
| 02-19-T1, 02-20-T1, 02-20-T2 | 02-19, 02-20 | 19, 20 | GATE-03, D-23..D-26, D-52 | T-02-37, T-02-38 | Paused conversation: agent sends nothing; every customer text alerts | feature | `php artisan test --compact tests/Feature/FrontDesk/TakeOverTest.php tests/Feature/FrontDesk/ReplyCapTest.php` | ✅ | ✅ green |
| 02-17-T1, 02-22-T1 | 02-17, 02-22 | 17, 22 | GATE-04, D-27, D-28, D-54 | T-02-31, T-02-44, T-02-46 | Replaced draft cannot be sent | feature | `php artisan test --compact tests/Feature/Filament/DraftReviewTest.php tests/Feature/FrontDesk/DraftReplacementTest.php` | ✅ | ✅ green |
| 02-10-T1, 02-11-T1 | 02-10, 02-11 | 10, 11 | D-18..D-22 | T-02-17, T-02-18, T-02-59 | Only tappable link in an alert is ours; alert never fails silently | feature | `php artisan test --compact tests/Feature/Alerts/OwnerAlertTest.php tests/Feature/Alerts/OwnerAlertTimingTest.php` | ✅ | ✅ green |
| 02-24-T1, 02-25-T1, 02-25-T2 | 02-24, 02-25 | 24, 25 | D-39, D-40 | T-02-47, T-02-48, T-02-49, T-02-62 | Opt-out blocks every outbound path | feature + unit | `php artisan test --compact tests/Feature/Inbound/OptOutTest.php tests/Unit/Channels/TextKeywordsTest.php` | ✅ | ✅ green |
| 02-26-T1, 02-27-T1, 02-27-T2 | 02-26, 02-27 | 26, 27 | TXT-01 | T-02-50, T-02-51, T-02-52, T-02-53, T-02-63 | Bad/missing Twilio signature → 403 | feature | `php artisan test --compact tests/Feature/Channels` | ✅ | ✅ green |
| 02-08-T1, 02-09-T1 | 02-08, 02-09 | 8, 9 | D-42 | T-02-14, T-02-15 | A message is never processed twice | feature | `php artisan test --compact tests/Feature/Inbound/DispatchInboundTest.php tests/Feature/Inbound/SweepUndispatchedTest.php` | ✅ | ✅ green |
| 02-18-T2 | 02-18 | 18 | D-43 | T-02-35 | A stored name is never overwritten | feature | `php artisan test --compact tests/Feature/Inbound/ContactNameTest.php` | ✅ | ✅ green |
| 02-12-T1 | 02-12 | 12 | D-18, D-19, D-31 | T-02-19, T-02-21 | N/A | feature | `php artisan test --compact tests/Feature/Alerts/ProfileTest.php tests/Feature/Filament/BusinessFolderPageTest.php` | ✅ | ✅ green |
| 02-23-T1, 02-23-T2 | 02-23 | 23 | D-19, D-29, D-32 | T-02-61 | N/A | feature + manual | `php artisan test --compact tests/Feature/Filament/ConversationViewTest.php tests/Feature/Filament/DashboardWidgetsTest.php` | ✅ | ✅ green |
| 02-28-T1 | 02-28 | 28 | D-07 | T-02-54 | N/A | feature | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php` | ✅ | ✅ green |
| 02-01-T1, 02-01-T2, 02-28-T2 | 02-01, 02-28 | 1, 28 | INFRA-01 | T-02-01, T-02-02, T-02-03, T-02-55 | N/A | CI + local docker | full suite commands above (SQLite and the Postgres recipe) | ✅ | ✅ green |

| 02-29-T1, 02-29-T3 | 02-29 | 22 | AGNT-02, GATE-01 (map of) | N/A | N/A | structural check | the plan's own verify block (citation check: every `path:line` in `map/` resolves) | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Plan 02-01 covers the first two items, the CI workflow and the `exampleFolderFileCount()` test helper (the example folder's file count in one place); the fakes arrive with the code they stand in for (FakeClassifier in 02-07, FrontDeskAgent fakes in 02-13, the ProcessInbound queue fake in 02-14, the fake channel opt-out simulation in 02-25, Twilio payload fixtures in 02-26 and 02-27), and each plan creates its own test files and factories.

- [x] Fix the 17 failing tests and 1 error on Postgres (hard-coded version labels; 360-character name in a `varchar(255)`), and convert the three `type: agent` "later sprint" fixtures
- [x] `tests/Pest.php` safety net: `Http::preventStrayRequests()`, agent fakes that prevent stray prompts, `Classifier` bound to a fake
- [x] `FakeClassifier`, an extended `FakeTextChannel` (opt-out, refused sends) and Twilio payload fixtures
- [x] `tests/Feature/FrontDesk/`, `tests/Feature/Channels/`, `tests/Feature/Icm/AgentFile*` (every Pest helper gets a file-specific name)
- [x] `.github/workflows/tests.yml` and a README line for the Postgres recipe
- [x] Factories for any new model

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Alert link opens the conversation on a real phone over Wi-Fi; held reason, draft and Send visible without scrolling | D-29, D-30 | Needs a physical phone | Trigger a held draft from `/dev/chat`, open the alert link on a phone on the same Wi-Fi |
| Live model reply through `/dev/chat` | AGNT-02 | Needs a provider key in `.env` | Set the key, text from `/dev/chat`, confirm a disclosed, signed reply |
| First CI run on Postgres | INFRA-01 | No git remote; Ian pushes | Push the branch, confirm the workflow is green |
| Live Twilio number | TXT-01 | Waits on A2P approval; does not gate this phase | After approval, text the test number |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** validated 2026-10-12 (validate-phase, State A: every mapped test file exists; full suite 931/931 green on SQLite at wave 22 and on PostgreSQL 17 in 02-28)

## Validation Audit 2026-10-12

| Metric | Count |
|---|---|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |
