---
phase: 02-text-front-desk
verified: 2026-10-12T14:30:00Z
status: gaps_found
score: 5/5 roadmap success criteria verified (all 10 requirement IDs satisfied); 0 behavior-unverified
covered_files: [".github/workflows/tests.yml", "app/Actions/OutboundSender.php", "app/Actions/RunFrontDesk.php", "app/Ai/RunFrontDeskAgent.php", "app/Channels/TwilioTextChannel.php", "app/Filament/Pages/BusinessFolder.php", "app/Jobs/ProcessInbound.php", "app/SendRule/CodeChecks.php", "app/SendRule/DecideSendOrDraft.php", "resources/views/livewire/conversation-thread.blade.php", "tests/Feature/FrontDesk/GateTest.php", "tests/Feature/Inbound/OptOutTest.php"]
covered_digest: "v3:sha256:d72aaa80d0d7889c413b1c156b935c98bcc0213e47750aeff5752fd2bbe52b4b"
behavior_unverified: 0
overrides_applied: 0
warnings_recommended_before_live_traffic:
  - id: CR-01
    summary: "A claimed burst is lost with no owner alert when the front desk run throws (anything other than FrontDeskUnavailable or NoActiveVersion) or the worker times out"
    confirmed_in_code: true
    defeats_must_have: false
  - id: CR-02
    summary: "Business Folder page 500s while a Phase 1 version is live (owner_alert is a string); true in the local dev database right now (v1, 8 files)"
    confirmed_in_code: true
    defeats_must_have: false
gaps_source: "Ian's ruling 2026-10-12: CR-01, CR-02, WR-01, WR-02, WR-05, WR-06, WR-07 and WR-16 from 02-REVIEW.md are fixed before UAT, as gap closure"
gaps:
  - truth: "A customer text that the front desk claims is either answered or reaches the owner; a run that throws or outlives the worker never leaves it claimed and silent (AGNT-04, phase goal)"
    status: failed
    reason: "CR-01: ProcessInbound claims the burst (handled_at) before RunFrontDesk runs; any throwable other than NoActiveVersion/FrontDeskUnavailable/sender DomainException, or a worker timeout, leaves the texts claimed, unanswered and un-alerted, and neither a retry nor the sweep can find them"
    artifacts:
      - path: "app/Jobs/ProcessInbound.php"
        issue: "No $timeout, $tries or failed(); claim happens before the run and is never released"
      - path: "app/Actions/RunFrontDesk.php"
        issue: "Docblock says it never throws; only FrontDeskUnavailable and sender DomainException are caught"
      - path: "app/Ai/RunFrontDeskAgent.php"
        issue: "40 s per provider x chain + 8 s Jev can exceed the default 60 s worker timeout"
    missing:
      - "Job $timeout above the worst-case provider chain plus Jev, and queue retry_after above the job timeout"
      - "A failed() hook (or a catch Throwable around RunFrontDesk) that releases the claim or raises a FrontDeskFailed owner alert for the burst's last text, with a timeline note"
      - "Tests: an unexpected exception inside the run alerts the owner and leaves no claimed-but-silent text; the timeout values are pinned"
  - truth: "The Business Folder page and Sync Now load while a Phase 1 folder version is still live, so the owner can sync forward from the dashboard (DASH-02 regression)"
    status: failed
    reason: "CR-02: BusinessFolder::alertWarning() indexes sendingRules()['owner_alert']['channels'], but in a Phase 1 version owner_alert is a free-text string; the TypeError 500s the whole page including Sync Now. True in the local dev database now (v1, 8 files)"
    artifacts:
      - path: "app/Filament/Pages/BusinessFolder.php"
        issue: "alertWarning() assumes owner_alert is an array (line 132)"
    missing:
      - "Guard owner_alert with is_array (and any other reader of owner_alert that assumes the Phase 2 shape)"
      - "Test: the page renders with a compiled version whose owner_alert is a string"
  - truth: "A live folder with no text agent never drops customer texts silently"
    status: failed
    reason: "WR-01: RunFrontDesk returns with the burst claimed, no timeline note and no owner alert when the live version has no agent for sms; the Validator does not require one"
    artifacts:
      - path: "app/Actions/RunFrontDesk.php"
        issue: "Silent return at lines 108-112 when AgentFile::forChannel() is null"
    missing:
      - "Treat a missing sms agent like the unavailable case: timeline note plus a FrontDeskFailed owner alert ('No front desk agent is set up for texts'), or make the Validator require an sms agent"
      - "Test for a live version with no sms agent"
  - truth: "Every inbound text is queued; a failed queue push is recovered by the sweep"
    status: failed
    reason: "WR-02: DispatchInbound sets dispatched_at before pushing ProcessInbound; if the push throws, the row keeps dispatched_at and SweepUndispatched (whereNull dispatched_at) never retries it"
    artifacts:
      - path: "app/Actions/DispatchInbound.php"
        issue: "Claim at lines 28-39 is not undone when the dispatch fails"
    missing:
      - "Release dispatched_at when the push throws (and rethrow), or give the sweep a staleness path for dispatched-but-unhandled rows"
      - "Test: a failing push leaves the text sweepable"
  - truth: "A customer who describes a danger always gets the safety line or the owner is alerted; a failed or timed-out safety send is never followed by the generic holding reply"
    status: failed
    reason: "WR-05: SendLifeSafetyLine catches only DomainException; RespondToLifeSafety has $tries = 3 and no failed(); a lock timeout returns null with no alert or pause; RunFrontDesk's classifier path ignores that null and can send the holding reply to someone describing a fire or gas leak"
    artifacts:
      - path: "app/Jobs/RespondToLifeSafety.php"
        issue: "No failed() hook"
      - path: "app/Actions/SendLifeSafetyLine.php"
        issue: "Non-DomainException failures and LockTimeoutException end silently (lines 56-60, 94-98)"
      - path: "app/Actions/RunFrontDesk.php"
        issue: "Holding reply not suppressed when the verdict is life-safety (lines 136-138, 367-369)"
    missing:
      - "failed() on RespondToLifeSafety raising AlertReason::LifeSafety with 'Safety message could not be sent'"
      - "Owner alert on lock timeout in SendLifeSafetyLine (the alert is idempotent)"
      - "RunFrontDesk skips the holding reply when the verdict is life-safety"
      - "Tests for each path"
  - truth: "Common ways of describing a gas leak, fire or burning smell trigger the safety line, and ordinary leak and appliance repair texts do not"
    status: failed
    reason: "WR-06: LifeSafety's phrase lists miss 'gas is leaking', 'gas leaking', 'smells of gas', 'gas odor', 'electrical fire', 'the house is burning', 'burnt smell'; the water-plus-electrical pairing over-triggers on bare 'appliance(s)' and 'panel' (e.g. 'my dishwasher appliance is leaking')"
    artifacts:
      - path: "app/SendRule/LifeSafety.php"
        issue: "GAS_PHRASES / FIRE_PHRASES gaps; ELECTRICAL_WORDS includes bare 'appliance', 'appliances', 'panel'"
    missing:
      - "Add the missed gas and fire phrasings AS PHRASES. Keep the 93ce078 rule: phrases only, never the bare words propane, natural gas, sulfur or sulphur (no 'gas near leak/smell' proximity match on those words)"
      - "Narrow the water-electrical pairing (drop bare 'appliance(s)' and 'panel'; keep electrical panel, breaker panel, outlet, socket, plugged in, wiring)"
      - "Unit examples for each added phrase and for the narrowed pairing; keep 'how much to switch from propane to natural gas?' as a no-hazard example (tests/Unit/SendRule/LifeSafetyTest.php:27)"
  - truth: "A web form that describes a danger alerts the owner (no text is sent to the lead, per D-41)"
    status: failed
    reason: "WR-07: respondToDanger() returns early for non-SMS messages and ProcessInbound drops web-only bursts, so 'I smell gas in the basement' on a form only appears on the dashboard"
    artifacts:
      - path: "app/Actions/RecordInbound.php"
        issue: "respondToDanger() skips non-SMS (lines 109-122)"
      - path: "app/Jobs/ProcessInbound.php"
        issue: "Web-only burst returns at lines 63-65"
    missing:
      - "Raise AlertReason::LifeSafety ('Web form describes a danger') when LifeSafety::hazardIn() matches a form message; send no text"
      - "Test for a hazard form and a non-hazard form"
  - truth: "The test suite stays green and never reads live credentials when .env is configured for production as docs/SETUP.md section 11 instructs (INFRA-01, tests-first rule)"
    status: failed
    reason: "WR-16: tests/Pest.php pins the folder, HTTP, classifier, agent and queue but not TEXT_CHANNEL, TWILIO_* or JEV_*; with TEXT_CHANNEL=twilio in .env every fake-webhook test 404s and the JEV_MODEL pin test fails"
    artifacts:
      - path: "tests/Pest.php"
        issue: "Shared beforeEach does not pin channels.text.driver or the Twilio and Jev config (lines 34-64)"
      - path: "phpunit.xml"
        issue: "No <env> lines for TEXT_CHANNEL, TWILIO_*, JEV_*"
    missing:
      - "Pin channels.text.driver = fake and null the Twilio and Jev credentials (Jev model at its default) in the shared beforeEach or phpunit.xml"
      - "A guard test that the suite runs on the fake driver with no live keys regardless of .env"
human_verification:
  - test: "Open an owner-alert link on a real phone over Wi-Fi after triggering a held draft from /dev/chat"
    expected: "Lands on the conversation; held reason, draft and Send are visible without scrolling"
    why_human: "Needs a physical phone (D-29, D-30)"
  - test: "Set JEV_API_KEY, ANTHROPIC_API_KEY and OPENAI_API_KEY, run icm:sync for the Phase 2 folder, run a queue worker, text from /dev/chat"
    expected: "A reply that opens with the AI disclosure, ends with the sign-off, asks the matching service's intake question, and a price question is held as a draft"
    why_human: "Needs live provider keys; every automated test uses laravel/ai fakes and a scripted Jev"
  - test: "Push the branch (no git remote exists yet) and watch the tests workflow"
    expected: "Both matrix legs (sqlite, pgsql) green"
    why_human: "CI has never run. Local evidence: 931 passed on SQLite (re-run in this verification) and 02-28 recorded the same on PostgreSQL 17"
---

# Phase 02: Text Front Desk Verification Report

**Phase Goal:** A customer who texts gets a reply that moves the conversation forward without the owner, from an agent defined in a file, and nothing risky is ever sent without the owner.
**Verified:** 2026-10-12
**Status:** gaps_found (set by Ian's ruling 2026-10-12; the verifier's own call was human_needed)
**Re-verification:** No, initial verification

## Goal Achievement

The goal is achieved in the codebase. The code and tests deliver every roadmap success criterion, and no must-have truth fails. Two confirmed review defects (CR-01, CR-02) are real and I recommend closing them before live traffic, but neither defeats a must-have. The remaining items are the manual checks the phase always listed.

### Observable Truths (ROADMAP Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A text through the fake channel gets an agent reply in the same conversation; first reply discloses AI, uses the sign-off, never uses a `never_say` phrase; intake questions asked and answers on the timeline and card | VERIFIED | `DevChat` route `/dev/chat` (routes/web.php:34, local-only, 404 test). `RunFrontDesk::handle()` runs the agent, `ComposeAutomatedText`, then `OutboundSender::send()`. `DisclosureTest` (first text only, even when owner texted first, new conversation after close, sign-off on every agent text, never_say held). `IntakeAnswersTest` (stored against conversation/message/version, `intake_answered` timeline entry, shown on card). `ProcessInboundTest` end-to-end with scripted agent. Needs a live model for real wording (human item 2) |
| 2 | Price/promise/discount/complaint is never sent: drafted, owner alerted; intake/scheduling/FAQ send on their own, even when a scripted model tries to send the draft | VERIFIED | `DecideSendOrDraft` runs three checks in code: label floor (`ReplyLabel::neverOnItsOwn()` ANDed with the stored list, so a stored rule cannot lower it), `CodeChecks` hard veto, then Jev (fail closed, can only hold). `GateTest` dataset covers each label, `$`/% discount/"guarantee" in text mislabelled `faq_answer`, Jev down, Jev unsure, too long. `ProcessInboundTest` "keeps the floor in code: a stored rule that lists price as sendable still holds a price reply". Arch test limits `TextChannel` to `OutboundSender`, `SendOwnerAlert`, `App\Channels`, `App\Providers`. `HeldDraftTest` for the alert. See WR-10 below for the limit of the code veto |
| 3 | Owner can take over (agent silent until handed back), approve/edit/discard a draft; "asks for a person" goes to `escalate_to` | VERIFIED | `PauseConversation`/`HandBackConversation`, `OutboundSender::whyBlocked()` refuses Automated sends while paused (checked again inside the send lock). `TakeOverTest` (13 cases incl. a reply composed before Take over is held, hand back resumes). `DraftReviewTest` (approve as written, edit, discard, replaced draft refused, 1,600 boundary). `HandoffTest` (every way of asking, agent `wants_person`, Jev at 0.9, unsure Jev only holds) |
| 4 | Provider/model/fallback is an edit to `agents/*.md` via normal sync; broken agent file rejected in plain words; primary failure falls to fallback | VERIFIED | `examples/example-hvac/agents/front-desk.md` carries provider, model, fallback. `RunFrontDeskAgent` loops `providerChain()` and catches any Throwable. `AgentFileTest` (parametrised plain-words rejections, previous version stays live, one agent per channel). `AgentFallbackTest` (overloaded, SDK-unsafe error, malformed JSON/bogus label/empty reply, all fail, no fallback) |
| 5 | Twilio driver behind `TextChannel`, selected by config alone, verified on Twilio-shaped payloads; suite passes on Postgres in CI and SQLite locally | VERIFIED (CI run pending, human item 3) | `AppServiceProvider` binds `TextChannel` by `config('channels.text.driver')` (`TEXT_CHANNEL`, default `fake`). `tests/Feature/Channels/*` (signature check against Twilio's published vector, 403 on missing/wrong/altered, MessageSid dedupe, 21610, status callbacks). `.github/workflows/tests.yml` matrix `[sqlite, pgsql]` with postgres:17. 931 passed / 3961 assertions on SQLite, re-run by me. Postgres pass relies on the 02-28 summary; no CI run has occurred |

**Score:** 5/5 truths verified, 0 present-but-behavior-unverified.

Plan-level must-haves spot-read against code and tests: 02-08/09 (claim by `handled_at`, `DebounceFor(20, maxWait: 90)`, sweep, forms never reach the agent), 02-13 (agent given no tool, provider chain), 02-15 (all providers fail: nothing sent, timeline note, `front_desk_failed` alert, job does not throw), 02-24 (opt-out). All present and tested.

### Opt-out indicator (question from the UI audit)

The UI auditor's "no opt-out indicator" finding is incorrect. 02-24 promised "the reply box shows 'Opted out by texting STOP on `<date>`.' in place of the form". It exists: `OutboundSender::whyBlocked()` (app/Actions/OutboundSender.php:124) returns that sentence first for every origin, `SendOwnerReply::whyCannotReply()` passes it through, `ConversationThread::replyBlockedReason()` computes it, and `conversation-thread.blade.php` lines 69-77 render it above a disabled box. `OptOutTest` "shows the owner why they cannot reply, in place of the form" asserts it with `assertSee`. The audit grepped for "opt" in views and missed the computed property path. It is present only on the conversation thread (not on the contact list), which matches what 02-24 promised.

### Required Artifacts / Key Links

Plan artifacts and key links for the plans read (02-08, 02-09, 02-13, 02-15, 02-24) are present, substantive and wired (grep and call-site reading). Data flows from the database: `FrontDeskPrompt` renders the card from the DB and loads files through `ActiveConfig`. No hollow props or static returns found.

### Requirements Coverage

All 10 IDs appear in plan frontmatter and are marked Complete in REQUIREMENTS.md. No orphaned requirement IDs.

| Requirement | Source Plans | Status | Evidence |
|-------------|--------------|--------|----------|
| AGNT-01 | 02-02 | SATISFIED | `AgentFileTest`; Validator rejects broken agent file |
| AGNT-02 | 02-08, 09, 13, 14, 29 | SATISFIED | `ProcessInbound` -> `RunFrontDesk` -> `FrontDeskPrompt` (files via `ActiveConfig`, fresh card) |
| AGNT-03 | 02-18 | SATISFIED | `IntakeAnswersTest` (timeline entry and card) |
| AGNT-04 | 02-02, 13, 15 | SATISFIED as specified (fallback; all-fail alerts owner). Gap outside the spec: CR-01 | `AgentFallbackTest`, `FrontDeskFailureTest` |
| GATE-01 | 02-03..07, 10..12, 14..17, 21, 28, 29 | SATISFIED (defence in depth, see WR-10) | `GateTest`, `HeldDraftTest`, alert tests |
| GATE-02 | 02-03..05, 15, 20, 21 | SATISFIED | `DisclosureTest`, `HandoffTest`, `LifeSafetyTest` |
| GATE-03 | 02-19, 20 | SATISFIED | `TakeOverTest`, `ReplyCapTest` |
| GATE-04 | 02-17, 22, 23 | SATISFIED | `DraftReviewTest`, `ConversationViewTest` |
| TXT-01 | 02-24..27 | SATISFIED (live number waits on A2P, does not gate) | `tests/Feature/Channels`, `OptOutTest` |
| INFRA-01 | 02-01, 28 | SATISFIED locally; CI run pending | workflow matrix present; SQLite 931 green |

### Anti-Patterns

No `TBD`/`FIXME`/`XXX` in any of the 180 non-planning files changed on the branch, and no `TODO`/`HACK`/`PLACEHOLDER` in `app`, `config`, `routes`, `resources`, `database`. No probes are declared by the plans (Step 7c not applicable). Security: 02-SECURITY.md reports 92/92 threats closed, `threats_open: 0`.

### Code review findings weighed against the goal

**CR-01 (confirmed, WARNING, does not defeat a must-have).** I read the code path. `ProcessInbound::handle()` claims the burst (`ClaimInboundBurst` sets `handled_at`), then calls `RunFrontDesk`, catching only `NoActiveVersion`. `RunFrontDesk` catches `FrontDeskUnavailable` and `DomainException` from the sender only. Any other throwable, or a worker kill, leaves the texts permanently claimed. A retry finds nothing, and `SweepUndispatched` selects on `dispatched_at`, so it cannot rescue them. The job has no `$timeout`, `$tries` or `failed()`. Worst-case chain time (2 providers x 40 s + 8 s Jev) exceeds the default 60 s worker timeout. This makes the `RunFrontDesk` docblock ("This never throws ... the owner is alerted instead (AGNT-04)") false for unguarded failures. Why it does not fail a truth: the AGNT-04 behaviour the roadmap and plans specify (provider falls through; every provider failing alerts the owner and sends nothing) is verified and tested, and CR-01 never causes anything risky to be sent, so "nothing risky is ever sent without the owner" holds. It is a real hole in "a customer who texts gets a reply" under failure, and the customer's text sits unanswered with no owner alert. Fix before live traffic: `failed()` hook or a catch-all around `RunFrontDesk` that raises `FrontDeskFailed` and/or un-claims, plus an explicit `$timeout` above the provider chain and `retry_after` above that.

**CR-02 (confirmed, WARNING, does not defeat a Phase 2 must-have, but is a live defect).** `BusinessFolder::alertWarning()` indexes `['owner_alert']['channels']` and catches only `NoActiveVersion`. I ran `app(ActiveConfig::class)->sendingRules()['owner_alert']` against the local database and got the Phase 1 free-text string, so `icm:status` shows v1 with 8 files live and the Business Folder page would currently return a 500, including its Sync Now button. This regresses a Phase 1 deliverable (DASH-02) in an upgrade state. Phase 2 SC 4 still holds because `php artisan icm:sync` goes live normally and all roadmap criteria are met on a fresh Phase 2 folder. Immediate workaround: run `php artisan icm:sync`. Also note that until the Phase 2 folder is synced there is no `sms` agent, which hits WR-01 (claimed and silently dropped). Fix: the guard in the review's CR-02 section plus a test.

**Warnings that touch a must-have, none defeating one.** WR-10 (spelled-out amounts such as "two hundred dollars" and "free estimate" pass the code veto): GATE-01 says "whatever the model outputs". The label floor and Jev (fail closed, thresholds 0.075) still stand behind the code veto, and with no Jev key every reply is held. So the guarantee is layered, not absolute on the code veto alone. Worth tightening. WR-05/06/07 (life-safety coverage and failure paths) concern a safety feature beyond the five roadmap criteria. WR-01 and WR-02 are the same silent-drop family as CR-01. The remaining warnings (WR-03/04 duplicate alerts, WR-08, WR-09, WR-11..21) are hardening and test-isolation items. All 38 findings are still `open` in 02-REVIEW-DISPOSITION.md.

### Human Verification Required

1. **Alert link on a real phone** (D-29, D-30). Trigger a held draft from `/dev/chat`, open the alert link on a phone on the same Wi-Fi. Expected: conversation opens, held reason, draft and Send visible without scrolling.
2. **Live model reply through `/dev/chat`** (AGNT-02). Needs `JEV_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, the Phase 2 folder synced, and a queue worker (database queue, 20 s debounce). Expected: disclosed, signed reply that asks the intake question; a price question becomes a draft.
3. **First CI run on Postgres** (INFRA-01). Ian pushes; both matrix legs should be green.
4. **Live Twilio number**: waits on A2P approval (DEC-05) and does not gate this phase.

## Gaps Summary

**Ian's ruling (2026-10-12): fix these before UAT, as gaps.** The verifier found no failed roadmap criterion or plan must-have and returned `human_needed`; Ian chose the stricter reading and routes eight confirmed code-review findings (`02-REVIEW.md`) to gap closure. They are in the `gaps:` frontmatter for `/gsd-plan-phase 2 --gaps`.

| Gap | Finding | What it breaks |
|-----|---------|----------------|
| 1 | CR-01 | A claimed text is lost with no owner alert when the run throws or the worker times out (AGNT-04 "never silent") |
| 2 | CR-02 | Business Folder page and Sync Now 500 while a Phase 1 version is live (DASH-02 regression) |
| 3 | WR-01 | A live folder with no `sms` agent drops texts silently |
| 4 | WR-02 | A failed queue push strands the text past the sweep |
| 5 | WR-05 | Life-safety failures and lock timeouts are silent, and the holding reply can follow |
| 6 | WR-06 | Safety phrase gaps and appliance/panel over-triggering. Keep the 93ce078 rule: phrases only, never bare propane, natural gas, sulfur or sulphur; keep "how much to switch from propane to natural gas?" as a no-hazard test |
| 7 | WR-07 | A web form describing a danger alerts no one |
| 8 | WR-16 | The suite reads `.env`; the documented go-live step turns it red |

Not gaps (recorded in `02-REVIEW-DISPOSITION.md`): WR-15 before first deploy (same root as T-01-37), WR-08 and WR-10 before first client, WR-03 and WR-04 Phase 2.1. Every other finding stays open.

The three human verification items above still apply after the gaps close; `02-UAT.md` holds them.
---

_Verified: 2026-10-12_
_Verifier: Claude (gsd-verifier)_
