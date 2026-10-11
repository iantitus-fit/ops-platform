---
phase: "02"
slug: "text-front-desk"
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| 02-02-T1, 02-02-T2 | 02-02 | 2 | AGNT-01, D-35 | T-02-04, T-02-05, T-02-06 | Broken agent file rejected in plain words; one agent per channel | feature | `php artisan test --compact tests/Feature/Icm/AgentFileTest.php` | ❌ created in 02-02 | ⬜ pending |
| 02-03-T2 | 02-03 | 3 | D-08, D-09 | T-02-07 | price/promise/discount/complaint can never be in `send_on_own`; unknown key rejected | feature | `php artisan test --compact tests/Feature/Icm/SendingRulesValidationTest.php` | ❌ created in 02-03 | ⬜ pending |
| 02-03-T1 | 02-03 | 3 | D-10..D-12 | T-02-08 | Fixed replies with dollar/discount/promise/`never_say` text rejected at sync | feature + unit | `php artisan test --compact tests/Feature/Icm/RepliesValidationTest.php tests/Unit/SendRule/CodeChecksTest.php` | ❌ created in 02-03 | ⬜ pending |
| 02-05-T2, 02-07-T2, 02-08-T1 | 02-05, 02-07, 02-08 | 5, 7, 8 | AGNT-02, D-36, D-41 | T-02-16, T-02-22 | Agent never texts a number that has not texted in that conversation | feature | `php artisan test --compact tests/Feature/FrontDesk/ProcessInboundTest.php tests/Feature/FrontDesk/FrontDeskPromptTest.php` | ❌ created in 02-07 / 02-08 | ⬜ pending |
| 02-10-T1, 02-10-T2 | 02-10 | 10 | AGNT-03, D-33, D-34 | T-02-34, T-02-36 | N/A | feature | `php artisan test --compact tests/Feature/FrontDesk/IntakeAnswersTest.php` | ❌ created in 02-10 | ⬜ pending |
| 02-07-T3, 02-08-T3 | 02-07, 02-08 | 7, 8 | AGNT-04 | T-02-23, T-02-29 | Provider failure falls through; all fail → owner alerted, nothing sent | feature | `php artisan test --compact tests/Feature/FrontDesk/AgentFallbackTest.php tests/Feature/FrontDesk/FrontDeskFailureTest.php` | ❌ created in 02-07 / 02-08 | ⬜ pending |
| 02-08-T1, 02-08-T2, 02-09-T2 | 02-08, 02-09 | 8, 9 | GATE-01, D-01..D-04, D-07 | T-02-26, T-02-27, T-02-28 | Scripted model labelling a price reply sendable still drafts; Jev failure holds | feature | `php artisan test --compact tests/Feature/FrontDesk/GateTest.php tests/Feature/FrontDesk/GateVerdictTest.php tests/Feature/FrontDesk/ProcessInboundTest.php` | ❌ created in 02-08 / 02-09 | ⬜ pending |
| 02-03-T1 | 02-03 | 3 | D-02 | T-02-08 | Code checks are a hard veto | unit | `php artisan test --compact tests/Unit/SendRule/CodeChecksTest.php` | ❌ created in 02-03 | ⬜ pending |
| 02-04-T1, 02-04-T2, 02-04-T3 | 02-04 | 4 | D-03, D-05, D-06, D-45..D-47 (Jev driver) | T-02-10, T-02-11, T-02-12, T-02-13 | Fail closed on any error/missing answer; no name or phone sent to Jev | feature + unit | `php artisan test --compact tests/Feature/SendRule/JevClassifierTest.php tests/Unit/SendRule/TriageReadingTest.php` | ❌ created in 02-04 | ⬜ pending |
| 02-08-T2, 02-11-T3 | 02-08, 02-11 | 8, 11 | GATE-02 | T-02-26 | Disclosure + sign-off; never a `never_say` phrase; ask for person → handoff + pause | feature | `php artisan test --compact tests/Feature/FrontDesk/DisclosureTest.php tests/Feature/FrontDesk/HandoffTest.php` | ❌ created in 02-08 / 02-11 | ⬜ pending |
| 02-03-T3, 02-09-T3 | 02-03, 02-09 | 3, 9 | D-12..D-14 | T-02-09, T-02-32 | Holding-reply time is always true (computed from hours) | feature | `php artisan test --compact tests/Feature/Icm/BusinessClockTest.php tests/Feature/SendRule/FixedRepliesTest.php tests/Feature/FrontDesk/HoldingReplyTest.php` | ❌ created in 02-03 / 02-09 | ⬜ pending |
| 02-12-T1, 02-12-T2 | 02-12 | 12 | D-15..D-17, D-46, D-49 | T-02-41, T-02-42, T-02-43 | Life-safety line sends first, even when paused; never gives repair steps | feature + unit | `php artisan test --compact tests/Feature/FrontDesk/LifeSafetyTest.php tests/Unit/SendRule/LifeSafetyTest.php` | ❌ created in 02-12 | ⬜ pending |
| 02-11-T1, 02-11-T2, 02-11-T3 | 02-11 | 11 | GATE-03, D-23..D-26, D-52 | T-02-37, T-02-38 | Paused conversation: agent sends nothing; every customer text alerts | feature | `php artisan test --compact tests/Feature/FrontDesk/TakeOverTest.php tests/Feature/FrontDesk/ReplyCapTest.php` | ❌ created in 02-11 | ⬜ pending |
| 02-09-T2, 02-13-T1 | 02-09, 02-13 | 9, 13 | GATE-04, D-27, D-28, D-54 | T-02-31, T-02-44, T-02-46 | Replaced draft cannot be sent | feature | `php artisan test --compact tests/Feature/Filament/DraftReviewTest.php tests/Feature/FrontDesk/DraftReplacementTest.php` | ❌ created in 02-09 / 02-13 | ⬜ pending |
| 02-06-T1, 02-06-T2 | 02-06 | 6 | D-18..D-22 | T-02-17, T-02-18 | Only tappable link in an alert is ours; alert never fails silently | feature | `php artisan test --compact tests/Feature/Alerts/OwnerAlertTest.php tests/Feature/Alerts/OwnerAlertTimingTest.php` | ❌ created in 02-06 | ⬜ pending |
| 02-14-T1, 02-14-T2, 02-14-T3 | 02-14 | 14 | D-39, D-40 | T-02-47, T-02-48, T-02-49 | Opt-out blocks every outbound path | feature + unit | `php artisan test --compact tests/Feature/Inbound/OptOutTest.php tests/Unit/Channels/TextKeywordsTest.php` | ❌ created in 02-14 | ⬜ pending |
| 02-15-T1, 02-15-T2, 02-15-T3 | 02-15 | 15 | TXT-01 | T-02-50, T-02-51, T-02-52, T-02-53 | Bad/missing Twilio signature → 403 | feature | `php artisan test --compact tests/Feature/Channels` | ❌ created in 02-15 | ⬜ pending |
| 02-05-T1, 02-05-T2 | 02-05 | 5 | D-42 | T-02-14, T-02-15 | A message is never processed twice | feature | `php artisan test --compact tests/Feature/Inbound/DispatchInboundTest.php tests/Feature/Inbound/SweepUndispatchedTest.php` | ✅ (FakeTextWebhookTest changes) + ❌ created in 02-05 | ⬜ pending |
| 02-10-T2 | 02-10 | 10 | D-43 | T-02-35 | A stored name is never overwritten | feature | `php artisan test --compact tests/Feature/Inbound/ContactNameTest.php` | ❌ created in 02-10 | ⬜ pending |
| 02-06-T3 | 02-06 | 6 | D-18, D-19, D-31 | T-02-19, T-02-21 | N/A | feature | `php artisan test --compact tests/Feature/Alerts/ProfileTest.php tests/Feature/Filament/BusinessFolderPageTest.php` | ❌ created in 02-06 | ⬜ pending |
| 02-13-T2, 02-13-T3 | 02-13 | 13 | D-19, D-29, D-32 | T-02-45 | N/A | feature + manual | `php artisan test --compact tests/Feature/Filament/ConversationViewTest.php tests/Feature/Filament/DashboardWidgetsTest.php` | ❌ created in 02-13 | ⬜ pending |
| 02-16-T1 | 02-16 | 16 | D-07 | T-02-54 | N/A | feature | `php artisan test --compact tests/Feature/FrontDesk/GateExportTest.php` | ❌ created in 02-16 | ⬜ pending |
| 02-01-T1, 02-01-T2, 02-16-T2 | 02-01, 02-16 | 1, 16 | INFRA-01 | T-02-01, T-02-02, T-02-03, T-02-55 | N/A | CI + local docker | full suite commands above (SQLite and the Postgres recipe) | ❌ created in 02-01 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Plan 02-01 covers the first two items and the CI workflow; the fakes arrive with the code they stand in for (FakeClassifier in 02-04, FrontDeskAgent fakes in 02-07, the ProcessInbound queue fake in 02-08, the fake channel opt-out simulation in 02-14, Twilio payload fixtures in 02-15), and each plan creates its own test files and factories.

- [ ] Fix the 17 failing tests and 1 error on Postgres (hard-coded version labels; 360-character name in a `varchar(255)`), and convert the three `type: agent` "later sprint" fixtures
- [ ] `tests/Pest.php` safety net: `Http::preventStrayRequests()`, agent fakes that prevent stray prompts, `Classifier` bound to a fake
- [ ] `FakeClassifier`, an extended `FakeTextChannel` (opt-out, refused sends) and Twilio payload fixtures
- [ ] `tests/Feature/FrontDesk/`, `tests/Feature/Channels/`, `tests/Feature/Icm/AgentFile*` (every Pest helper gets a file-specific name)
- [ ] `.github/workflows/tests.yml` and a README line for the Postgres recipe
- [ ] Factories for any new model

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

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
