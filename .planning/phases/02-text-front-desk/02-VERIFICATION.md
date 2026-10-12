---
phase: 02-text-front-desk
verified: 2026-10-12T20:30:00Z
status: human_needed
score: 5/5 roadmap success criteria verified; 8/8 prior gaps closed; 0 behavior-unverified
covered_files: [".planning/phases/02-text-front-desk/02-01-PLAN.md", ".planning/phases/02-text-front-desk/02-01-SUMMARY.md", ".planning/phases/02-text-front-desk/02-02-PLAN.md", ".planning/phases/02-text-front-desk/02-02-SUMMARY.md", ".planning/phases/02-text-front-desk/02-03-PLAN.md", ".planning/phases/02-text-front-desk/02-03-SUMMARY.md", ".planning/phases/02-text-front-desk/02-04-PLAN.md", ".planning/phases/02-text-front-desk/02-04-SUMMARY.md", ".planning/phases/02-text-front-desk/02-05-PLAN.md", ".planning/phases/02-text-front-desk/02-05-SUMMARY.md", ".planning/phases/02-text-front-desk/02-06-PLAN.md", ".planning/phases/02-text-front-desk/02-06-SUMMARY.md", ".planning/phases/02-text-front-desk/02-07-PLAN.md", ".planning/phases/02-text-front-desk/02-07-SUMMARY.md", ".planning/phases/02-text-front-desk/02-08-PLAN.md", ".planning/phases/02-text-front-desk/02-08-SUMMARY.md", ".planning/phases/02-text-front-desk/02-09-PLAN.md", ".planning/phases/02-text-front-desk/02-09-SUMMARY.md", ".planning/phases/02-text-front-desk/02-10-PLAN.md", ".planning/phases/02-text-front-desk/02-10-SUMMARY.md", ".planning/phases/02-text-front-desk/02-11-PLAN.md", ".planning/phases/02-text-front-desk/02-11-SUMMARY.md", ".planning/phases/02-text-front-desk/02-12-PLAN.md", ".planning/phases/02-text-front-desk/02-12-SUMMARY.md", ".planning/phases/02-text-front-desk/02-13-PLAN.md", ".planning/phases/02-text-front-desk/02-13-SUMMARY.md", ".planning/phases/02-text-front-desk/02-14-PLAN.md", ".planning/phases/02-text-front-desk/02-14-SUMMARY.md", ".planning/phases/02-text-front-desk/02-15-PLAN.md", ".planning/phases/02-text-front-desk/02-15-SUMMARY.md", ".planning/phases/02-text-front-desk/02-16-PLAN.md", ".planning/phases/02-text-front-desk/02-16-SUMMARY.md", ".planning/phases/02-text-front-desk/02-17-PLAN.md", ".planning/phases/02-text-front-desk/02-17-SUMMARY.md", ".planning/phases/02-text-front-desk/02-18-PLAN.md", ".planning/phases/02-text-front-desk/02-18-SUMMARY.md", ".planning/phases/02-text-front-desk/02-19-PLAN.md", ".planning/phases/02-text-front-desk/02-19-SUMMARY.md", ".planning/phases/02-text-front-desk/02-20-PLAN.md", ".planning/phases/02-text-front-desk/02-20-SUMMARY.md", ".planning/phases/02-text-front-desk/02-21-PLAN.md", ".planning/phases/02-text-front-desk/02-21-SUMMARY.md", ".planning/phases/02-text-front-desk/02-22-PLAN.md", ".planning/phases/02-text-front-desk/02-22-SUMMARY.md", ".planning/phases/02-text-front-desk/02-23-PLAN.md", ".planning/phases/02-text-front-desk/02-23-SUMMARY.md", ".planning/phases/02-text-front-desk/02-24-PLAN.md", ".planning/phases/02-text-front-desk/02-24-SUMMARY.md", ".planning/phases/02-text-front-desk/02-25-PLAN.md", ".planning/phases/02-text-front-desk/02-25-SUMMARY.md", ".planning/phases/02-text-front-desk/02-26-PLAN.md", ".planning/phases/02-text-front-desk/02-26-SUMMARY.md", ".planning/phases/02-text-front-desk/02-27-PLAN.md", ".planning/phases/02-text-front-desk/02-27-SUMMARY.md", ".planning/phases/02-text-front-desk/02-28-PLAN.md", ".planning/phases/02-text-front-desk/02-28-SUMMARY.md", ".planning/phases/02-text-front-desk/02-29-PLAN.md", ".planning/phases/02-text-front-desk/02-29-SUMMARY.md", ".planning/phases/02-text-front-desk/02-30-PLAN.md", ".planning/phases/02-text-front-desk/02-30-SUMMARY.md", ".planning/phases/02-text-front-desk/02-31-PLAN.md", ".planning/phases/02-text-front-desk/02-31-SUMMARY.md", ".planning/phases/02-text-front-desk/02-32-PLAN.md", ".planning/phases/02-text-front-desk/02-32-SUMMARY.md", ".planning/phases/02-text-front-desk/02-33-PLAN.md", ".planning/phases/02-text-front-desk/02-33-SUMMARY.md", ".planning/phases/02-text-front-desk/02-34-PLAN.md", ".planning/phases/02-text-front-desk/02-34-SUMMARY.md", "app/Actions/DispatchInbound.php", "app/Actions/RaiseOwnerAlert.php", "app/Actions/ReportFrontDeskFailure.php", "app/Actions/RunFrontDesk.php", "app/Actions/SendLifeSafetyLine.php", "app/Ai/RunFrontDeskAgent.php", "app/Filament/Pages/BusinessFolder.php", "app/Jobs/ProcessInbound.php", "app/Jobs/RespondToLifeSafety.php", "app/SendRule/LifeSafety.php", "config/gate.php", "config/queue.php", "phpunit.xml", "tests/Feature/FrontDesk/FrontDeskFailureTest.php", "tests/Feature/FrontDesk/LifeSafetyTest.php", "tests/Feature/Quality/TestSafetyNetTest.php", "tests/Pest.php"]
covered_digest: "v3:sha256:8b1785a06ca78df1eb55c159736afa524a342190adc93716997dd0c2f00e2485"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: "5/5 roadmap criteria; 8 gaps open (CR-01, CR-02, WR-01, WR-02, WR-05, WR-06, WR-07, WR-16)"
  gaps_closed:
    - "CR-01: a claimed text is answered or reaches the owner when the run throws or the worker times out"
    - "CR-02: Business Folder page and Sync Now load while a Phase 1 version is live"
    - "WR-01: a live folder with no sms agent alerts the owner"
    - "WR-02: a failed queue push releases the dispatch claim"
    - "WR-05: life-safety send failures, lock timeouts and job failure pause and alert; no holding reply after a classifier danger"
    - "WR-06: gas and fire phrasings added as phrases; appliance and panel over-trigger removed"
    - "WR-07: a web form describing a danger alerts the owner, no text sent to the lead"
    - "WR-16: the suite is pinned to the fake driver and null keys regardless of .env"
  gaps_remaining: []
  regressions: []
warnings_recommended_before_live_traffic:
  - id: "REVIEW CR-01 (new)"
    summary: "SendLifeSafetyLine::alreadyAnswered() short-circuits before the pause and the owner alert. If the process dies or PauseConversation/RaiseOwnerAlert throws after the line was sent, a retry (RespondToLifeSafety $tries 3, or RunFrontDesk's second look) returns null and never pauses or alerts. The customer did get the line; the conversation is not paused and the owner is not told."
    confirmed_in_code: true
    defeats_must_have: false
  - id: "REVIEW WR-01 (new)"
    summary: "The keyword net still misses 'I am smelling gas', 'I smelled gas', 'gas is hissing', 'something is burning', 'smells burning', 'electrocuted' (run in this verification: hazardIn returns null for all). Jev is the only backstop for these."
    confirmed_in_code: true
    defeats_must_have: false
  - id: "REVIEW WR-03 (new)"
    summary: "ProcessInbound::failed() reports on the newest inbound customer text, with no channel or handled_at filter, so it can report a later unclaimed text or a web form, and double-alert for a burst whose own text was already reported."
    confirmed_in_code: true
    defeats_must_have: false
  - id: "REVIEW WR-06 (new)"
    summary: "LifeSafety::hazardIn() fails open on a regex error: hazardIn(\"smoke \\xff\") returns null (run in this verification). Low reachability."
    confirmed_in_code: true
    defeats_must_have: false
human_verification:
  - test: "Open an owner-alert link on a real phone over Wi-Fi after triggering a held draft from /dev/chat"
    expected: "Lands on the conversation; held reason, draft and Send are visible without scrolling"
    why_human: "Needs a physical phone (D-29, D-30)"
  - test: "Set JEV_API_KEY, ANTHROPIC_API_KEY and OPENAI_API_KEY, run icm:sync for the Phase 2 folder, run a queue worker, text from /dev/chat"
    expected: "A reply that opens with the AI disclosure, ends with the sign-off, asks the matching service's intake question, and a price question is held as a draft"
    why_human: "Needs live provider keys; every automated test uses laravel/ai fakes and a scripted Jev"
  - test: "Push the branch (no git remote exists yet) and watch the tests workflow"
    expected: "Both matrix legs (sqlite, pgsql) green"
    why_human: "CI has never run. Local evidence: 985 passed on SQLite (re-run in this verification); 02-28 recorded a Postgres 17 pass at the earlier suite size"
---

# Phase 02: Text Front Desk Verification Report

**Phase Goal:** A customer who texts gets a reply that moves the conversation forward without the owner, from an agent defined in a file, and nothing risky is ever sent without the owner.
**Verified:** 2026-10-12
**Status:** human_needed
**Re-verification:** Yes, after gap closure (plans 02-30 to 02-34)

## Goal Achievement

The goal is achieved in the codebase. All five roadmap success criteria hold, all eight gaps from the first verification are closed in code and pinned by tests, and the full suite passes. The fresh code review (02-REVIEW.md: 1 critical, 6 warnings, 5 info) was weighed finding by finding against the phase must-haves. None of them defeats a must-have. They are hardening items on the safety-line and failure-report paths, and four of them are worth fixing before live traffic (see below). What remains is the three manual checks the phase always listed, so the status is `human_needed`.

### Observable Truths (ROADMAP Success Criteria)

Regression check on the five criteria verified in the first run. The files this round touched (`RunFrontDesk`, `ProcessInbound`, `RunFrontDeskAgent`, `DispatchInbound`, `RaiseOwnerAlert`, `LifeSafety`) were re-read, and the full suite was re-run.

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A text through the fake channel gets an agent reply in the same conversation; first reply discloses AI, uses the sign-off, never uses `never_say`; intake answers on the timeline and card | VERIFIED | `RunFrontDesk::handle()` still runs agent, `ComposeAutomatedText::forReply`, `OutboundSender::send`. `DisclosureTest`, `IntakeAnswersTest`, `ProcessInboundTest` pass in the 985. Needs a live model for real wording (human item 2) |
| 2 | Price/promise/discount/complaint never sent: drafted, owner alerted; intake/scheduling/FAQ send on their own | VERIFIED | `DecideSendOrDraft` three-layer check unchanged this round. `storeDraft()` now also skips the holding reply when `$verdict->lifeSafety` (RunFrontDesk.php:~380), which only removes a text, never adds one. `GateTest`, `HeldDraftTest` green. WR-10 (spelled-out amounts) stays deferred with its reason |
| 3 | Owner can take over, approve/edit/discard; "asks for a person" goes to `escalate_to` | VERIFIED | `handOffToOwner()`, pause check at RunFrontDesk.php:92-95 and the pause refusal inside `OutboundSender` unchanged. `TakeOverTest`, `DraftReviewTest`, `HandoffTest` green |
| 4 | Provider/model/fallback is an edit to `agents/*.md`; broken agent file rejected; primary failure falls to fallback | VERIFIED | `RunFrontDeskAgent` loop now adds a chain time budget (`gate.agent.chain_budget_seconds` 150, line 87): a fallback starts only if time spent plus the 40 s per-provider limit fits, and a skipped entry is recorded as `skipped: not enough time left in this run`. Order is never changed. `AgentFileTest`, `AgentFallbackTest` green |
| 5 | Twilio driver behind `TextChannel`, selected by config alone, verified on Twilio-shaped payloads; suite passes on Postgres in CI and SQLite locally | VERIFIED locally (CI run pending, human item 3) | `tests/Feature/Channels/*` green. 985 passed / 4158 assertions on SQLite, run by me (30 s). `.github/workflows/tests.yml` matrix `[sqlite, pgsql]` unchanged. Postgres pass relies on 02-28's record; no CI run has happened |

**Score:** 5/5 roadmap truths verified, 0 present-but-behavior-unverified.

### Prior Gaps: Closure Evidence

Each gap was checked in the code, then against the test that pins it. The tests were read, not just listed.

| Gap | Closed by | Code evidence | Test evidence | Status |
|-----|-----------|---------------|---------------|--------|
| CR-01 claimed text lost on throw/timeout | 02-32 | `ProcessInbound`: `$tries = 1`, `$timeout = 300`, `$failOnTimeout = true`, `catch (Throwable)` around `RunFrontDesk` calling `ReportFrontDeskFailure`, and `failed()`. `ReportFrontDeskFailure` writes the note and the `front_desk_failed` alert once per text with fixed words only. Claim is deliberately not released (no second customer text). `config/queue.php` `retry_after` 360 for database and redis, above 300. `gate.agent.chain_budget_seconds` 150 + Jev 8 + 120 < 300 | `FrontDeskFailureTest`: crash alerts owner and texts the customer nothing and leaks no exception words; `failed()` path; report-once; missing conversation; timeout values pinned | CLOSED |
| CR-02 Business Folder 500 on Phase 1 version | 02-31 | `BusinessFolder::alertWarning()` guards `is_array($alert)` and treats a string as the text channel. `RaiseOwnerAlert::ownerAlertSetting()` returns null for a non-array and for no live version | `BusinessFolderPageTest`: page and Sync Now open, sync forward, phone warning under a Phase 1 version | CLOSED |
| WR-01 no sms agent drops text | 02-32 | `RunFrontDesk.php:~108-114`: null agent calls `ReportFrontDeskFailure` ("No front desk agent is set up for texts") | `FrontDeskFailureTest`: Phase 2 folder without `agents/front-desk.md`, and a live Phase 1 version | CLOSED |
| WR-02 failed push strands text | 02-31 | `DispatchInbound`: `try { ProcessInbound::dispatch(...)->afterCommit(); } catch (Throwable) { dispatched_at => null; throw }` | `DispatchInboundTest`: a queue whose `push` and `later` throw; claim cleared and rethrown; the sweep queues it; the provider retry queues it once | CLOSED (see review WR-04 for the latent `afterCommit` caveat) |
| WR-05 silent life-safety failures | 02-33 | `SendLifeSafetyLine` catches `Throwable` from the send, still pauses and alerts "Safety message could not be sent"; `LockTimeoutException` now pauses and alerts. `RespondToLifeSafety::failed()` pauses and raises the alert. `storeDraft()` skips the holding reply when the verdict is life-safety | `LifeSafetyTest`: failed job, any-error send, lock held past the wait, no holding reply after a classifier danger | CLOSED (see review CR-01 for the residual retry hole) |
| WR-06 phrase gaps / appliance over-trigger | 02-33 | `LifeSafety::GAS_PHRASES` and `FIRE_PHRASES` hold the added phrasings as phrases only; `ELECTRICAL_WORDS` drops bare `appliance(s)` and `panel`; `water heater` stripped before the water check. No bare propane/natural gas/sulfur/sulphur entry | I ran `hazardIn()`: "gas is leaking" -> gas, "my house is on fire" -> general, "how much to switch from propane to natural gas?" -> null, "my dishwasher appliance is leaking" -> null, "water is pouring onto the outlet" -> water | CLOSED for the named phrasings (see review WR-01 for further misses) |
| WR-07 web form danger alerts no one | 02-33 | `ProcessInbound::alertOwnerToDangerousForms()` raises `AlertReason::LifeSafety` "Web form describes a danger" for each Web message where `LifeSafety::hazardIn()` matches, before the SMS-less early return; nothing is texted | `LifeSafetyTest`: hazard form alerts and texts the lead nothing; non-hazard form alerts no one; hazard form in a burst with a customer text still answers the text | CLOSED |
| WR-16 suite reads `.env` | 02-30 | `phpunit.xml` forces `TEXT_CHANNEL=fake`, null Twilio and Jev credentials, pinned `JEV_MODEL` and `JEV_BASE_URL` (both `<env force>` and `<server>`). `tests/Pest.php` shared `beforeEach` pins the same via `config([...])` | `TestSafetyNetTest`: in-suite pin assertions; a child Pest process under a hostile environment; `JEV_MODEL` default stays `typesafe/jev-1.13` | CLOSED |

### Required Artifacts / Key Links

Artifacts named in plans 02-30 to 02-34 exist, are substantive and are wired:

| Artifact | Wired through | Status |
|----------|---------------|--------|
| `app/Actions/ReportFrontDeskFailure.php` | Injected into `RunFrontDesk` (no-agent, all-failed) and `ProcessInbound::handle()` and `failed()` | VERIFIED |
| `app/Jobs/ProcessInbound.php` (`failed`, `catch Throwable`, `alertOwnerToDangerousForms`) | Queued only through `DispatchInbound` | VERIFIED |
| `app/Jobs/RespondToLifeSafety.php` (`failed`) | Dispatched by `RecordInbound::respondToDanger()` | VERIFIED |
| `app/Actions/RaiseOwnerAlert.php` (`ownerAlertSetting`) | Used by `isUrgent()` and `dueAt()` | VERIFIED |
| `config/gate.php` (`chain_budget_seconds`, `life_safety.lock_wait_seconds`) | Read by `RunFrontDeskAgent` and `SendLifeSafetyLine` | VERIFIED |
| `tests/Pest.php`, `phpunit.xml` pins | Shared `beforeEach`; guard test spawns a child process | VERIFIED |
| `map/**` cards (02-34) | Spot-read `answer-inbound.md`: the line cites for `ProcessInbound.php:95`, `RunFrontDesk.php:82`, the step 7 failure report match the code | VERIFIED (spot-check) |

Data flows from the database: alerts read the live `owner_alert` rule through `ActiveConfig`, never from disk.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full suite | `php artisan test --compact` (run once) | 985 passed, 4158 assertions, 30 s | PASS |
| Keyword net on the fixed phrasings | `LifeSafety::hazardIn()` on 17 samples | Named fixes behave as claimed; the nine review-listed variants still return null | PASS for gap scope, see warnings |
| Fail-open on bad bytes | `hazardIn("smoke \xff")` | null | confirms review WR-06 |

Probes: none declared by the plans (Step 7c not applicable).

### Requirements Coverage

All 10 IDs are declared in plan frontmatter and marked Complete in REQUIREMENTS.md. No orphaned IDs.

| Requirement | Source Plans | Status | Evidence |
|-------------|--------------|--------|----------|
| AGNT-01 | 02-02 | SATISFIED | `AgentFileTest`; Validator rejects broken agent files |
| AGNT-02 | 02-08, 09, 13, 14, 29, 31, 32, 34 | SATISFIED | `ProcessInbound` -> `RunFrontDesk` -> `FrontDeskPrompt`; claim released on failed dispatch |
| AGNT-03 | 02-18 | SATISFIED | `IntakeAnswersTest` |
| AGNT-04 | 02-02, 13, 15, 32, 34 | SATISFIED, now including the unexpected-failure and timeout paths | `AgentFallbackTest`, `FrontDeskFailureTest` |
| GATE-01 | 02-03..07, 10..12, 14..17, 21, 28, 29, 31, 33, 34 | SATISFIED (layered; WR-10 deferred) | `GateTest`, `HeldDraftTest`, alert tests |
| GATE-02 | 02-03..05, 15, 20, 21, 33, 34 | SATISFIED | `DisclosureTest`, `HandoffTest`, `LifeSafetyTest` |
| GATE-03 | 02-19, 20 | SATISFIED | `TakeOverTest`, `ReplyCapTest` |
| GATE-04 | 02-17, 22, 23 | SATISFIED | `DraftReviewTest`, `ConversationViewTest` |
| TXT-01 | 02-24..27 | SATISFIED (live number waits on A2P, does not gate) | `tests/Feature/Channels`, `OptOutTest` |
| INFRA-01 | 02-01, 28, 30 | SATISFIED locally, now independent of `.env`; CI run pending | pinned env, SQLite 985 green, workflow matrix present |

### Anti-Patterns Found

No `TBD`, `FIXME` or `XXX` in `app`, `config`, `routes`, `resources`, `database`, `tests/Pest.php` or `phpunit.xml`. No stubs or hollow returns in the files this round changed.

### Fresh Review (02-REVIEW.md) Weighed Against the Must-Haves

Counts are not the test. Each finding was checked against what a roadmap criterion or a plan must-have actually promises. I confirmed CR-01, WR-01, WR-03 and WR-06 in code (reading plus running `hazardIn()`), and read WR-02, WR-04 and WR-05 against the source. None defeats a must-have.

| Finding | Confirmed | Defeats a must-have? | Reasoning |
|---------|-----------|----------------------|-----------|
| CR-01 (reviewer: critical) | Yes | No | `SendLifeSafetyLine::handle()` returns null from `alreadyAnswered()` before pausing or alerting. After a successful send, a crash or a DB error in `PauseConversation`/`RaiseOwnerAlert` leaves a retry as a no-op, so there is no pause and no alert. This is a double fault on the safety path. The customer did receive the line (the gap truth "always gets the safety line or the owner is alerted" holds), and the plan 02-33 truths (send throws, lock timeout, job failed) are all pinned and pass. It contradicts the class docblock ("never neither") and is the reason it is the first fix to make. The reviewer's suggested fix (pause and alert in the already-answered branch, both idempotent) is three lines |
| WR-01 | Yes | No | Keyword net misses "smelling gas", "smelled gas", "gas is hissing", "something is burning", "electrocuted". 02-33's must-have named a specific phrase list, all of which work. The roadmap does not promise a complete hazard vocabulary and Jev is the backstop. Worth extending before real traffic because "I am smelling gas" is a natural phrasing |
| WR-02 | Read | No | Utility phone in `(312) 555-0188` form is dropped from the gas sentence (the regex needs a leading digit or plus). The safety sentence itself still sends. Cosmetic loss of the gas company line |
| WR-03 | Yes | No | `failed()` picks `latest('id')` inbound customer text with no channel or `handled_at` filter. It can alert on an unclaimed later text (and then dedup suppresses that text's own later failure report), or double-alert. It always alerts someone; it cannot lose an alert for the burst it fires on |
| WR-04 | Read | No | `afterCommit()` would defer the push outside the `try` if a caller wrapped `DispatchInbound` in a transaction. Today's callers (`RecordInbound`, `SweepUndispatched`) run outside a transaction, so the claim rollback works as the tests show |
| WR-05 | Read | No | `RespondToLifeSafety` docblock and `failed()` text are misleading (a send error is caught and never retried by the job; `failed()` always says "could not be sent"). Wording and an explicit `$timeout`, not a behavior hole |
| WR-06 | Yes | No | `hazardIn("smoke \xff")` returns null. Low reachability (Twilio sends valid UTF-8; Postgres rejects bad bytes), but the form and fake webhook accept any bytes |
| IN-01..IN-05 | Read | No | Message-prefix exception classification, raw provider error stored in `gate`, owner-alert settings parsed twice, non-atomic alert check-then-insert, scaffold leftovers and a narrow queue guard. Hardening |

Recommended order if Ian wants a short follow-up before live traffic: CR-01, then WR-01 (phrasings), then WR-03, then WR-06. None blocks UAT.

### Human Verification Required

These three carry over unchanged; `02-UAT.md` holds them.

1. **Alert link on a real phone** (D-29, D-30). Trigger a held draft from `/dev/chat`, open the alert link on a phone on the same Wi-Fi. Expected: the conversation opens; held reason, draft and Send are visible without scrolling.
2. **Live model reply through `/dev/chat`** (AGNT-02). Needs `JEV_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, the Phase 2 folder synced and a queue worker (database queue, 20 s debounce). Expected: a disclosed, signed reply that asks the intake question; a price question becomes a draft.
3. **First CI run on Postgres** (INFRA-01). Ian pushes; both matrix legs should be green.

The live Twilio number waits on A2P approval (DEC-05) and does not gate this phase.

## Gaps Summary

No gaps. All eight gaps from the first verification are closed and pinned by tests. The fresh review's 1 critical and 6 warnings are real in the four places I confirmed by running the code, but each sits on top of a must-have that still holds. They are recorded above as recommended-before-live-traffic warnings, and the 38 earlier review rows plus the 12 new ones stay with their dispositions in `02-REVIEW-DISPOSITION.md`.

---

_Verified: 2026-10-12_
_Verifier: Claude (gsd-verifier)_
