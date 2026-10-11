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
| TBD | TBD | TBD | AGNT-01, D-35 | — | Broken agent file rejected in plain words; one agent per channel | feature | `php artisan test --compact --filter=AgentFile` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-08, D-09 | — | price/promise/discount/complaint can never be in `send_on_own`; unknown key rejected | feature | `php artisan test --compact --filter=SendingRulesValidation` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-10..D-12 | — | Fixed replies with dollar/discount/promise/`never_say` text rejected at sync | feature | `php artisan test --compact --filter=RepliesValidation` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | AGNT-02, D-36, D-41 | — | Agent never texts a number that has not texted in that conversation | feature | `php artisan test --compact --filter=ProcessInbound` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | AGNT-03, D-33, D-34 | — | N/A | feature | `php artisan test --compact --filter=IntakeAnswers` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | AGNT-04 | — | Provider failure falls through; all fail → owner alerted, nothing sent | feature | `php artisan test --compact --filter=AgentFallback` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | GATE-01, D-01..D-04, D-07 | — | Scripted model labelling a price reply sendable still drafts; Jev failure holds | feature + unit | `php artisan test --compact --filter=Gate` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-02 | — | Code checks are a hard veto | unit | `php artisan test --compact --filter=CodeChecks` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-03, D-05, D-06 (Jev driver) | — | Fail closed on any error/missing answer; no name or phone sent to Jev | feature | `php artisan test --compact --filter=JevClassifier` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | GATE-02 | — | Disclosure + sign-off; never a `never_say` phrase; ask for person → handoff + pause | feature | `php artisan test --compact --filter=Disclosure` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-12..D-14 | — | Holding-reply time is always true (computed from hours) | feature + unit | `php artisan test --compact --filter=FixedReplies` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-15..D-17 | — | Life-safety line sends first, even when paused; never gives repair steps | feature | `php artisan test --compact --filter=LifeSafety` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | GATE-03, D-23..D-26 | — | Paused conversation: agent sends nothing; every customer text alerts | feature | `php artisan test --compact --filter=TakeOver` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | GATE-04, D-27, D-28 | — | Replaced draft cannot be sent | feature | `php artisan test --compact --filter=DraftReview` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-18..D-22 | — | Only tappable link in an alert is ours; alert never fails silently | feature | `php artisan test --compact --filter=OwnerAlert` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-39, D-40 | — | Opt-out blocks every outbound path | feature | `php artisan test --compact --filter=OptOut` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | TXT-01 | — | Bad/missing Twilio signature → 403 | feature | `php artisan test --compact --filter=Twilio` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-42 | — | A message is never processed twice | feature | `php artisan test --compact --filter=Dispatch` | ✅ (changes) | ⬜ pending |
| TBD | TBD | TBD | D-43 | — | A stored name is never overwritten | feature | `php artisan test --compact --filter=ContactName` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-18, D-31 | — | N/A | feature | `php artisan test --compact --filter=Profile` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-29, D-32 | — | N/A | feature + manual | `php artisan test --compact --filter=ConversationView` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-07 | — | N/A | feature | `php artisan test --compact --filter=GateExport` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | INFRA-01 | — | N/A | CI + local docker | full suite commands above | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

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
