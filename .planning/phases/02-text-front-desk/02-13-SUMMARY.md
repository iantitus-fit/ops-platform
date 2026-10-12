---
phase: 02-text-front-desk
plan: 13
subsystem: ai-agent
tags: [laravel-ai, structured-output, agent, provider-fallback, prompt, pest]

requires:
  - phase: 02-text-front-desk
    provides: AgentFile and its provider chain (plan 02-02); ActiveConfig::file() and files(); CustomerCard::render(); ReplyLabel; ClaimInboundBurst (plan 02-08)
provides:
  - App\Ai\Agents\FrontDeskAgent: laravel/ai structured agent (reply, label, wants_person, service_slug, intake_answers, customer_name), no tools
  - App\Ai\AgentReply::fromStructured(): field-by-field validation of the model's untrusted answer
  - App\Ai\FrontDeskPrompt: instructions (code rules, agent body, whole loaded files, fresh card), history, promptText, loadedPaths
  - App\Ai\RunFrontDeskAgent::handle(): provider chain loop returning AgentRun, or throwing FrontDeskUnavailable with every attempt
  - config/gate.php agent.timeout_seconds (40) and agent.lock_wait_seconds (5)
  - tests/Pest.php: FrontDeskAgent::fake()->preventStrayPrompts() before every Feature test, and scriptFrontDeskReplies()
  - Arch rule: FrontDeskAgent only used in App\Ai and Tests
affects: [02-14 send path (ProcessInbound, OutboundSender), 02-15 intake, 02-23 phone check]

plan_head_before: e96604a279f5b1c06599f5d83562bc26967dbde9
plan_head_after: d7811bf0b0d413532c0f88f368df7bb7c7a35fb6

actuals:
  tokens: 17800
  tasks: 3
  commits: 7

tech-stack:
  added: []
  patterns:
    - "laravel/ai structured agent: Agent + Conversational + HasStructuredOutput, constructor-fed instructions and history, no HasTools"
    - "Own provider loop around prompt(provider: [name => model]) so any Throwable or invalid answer moves to the next entry"
    - "Pest global fake in beforeEach with scriptFrontDeskReplies() filling defaults"

key-files:
  created:
    - app/Ai/Agents/FrontDeskAgent.php
    - app/Ai/FrontDeskPrompt.php
    - app/Ai/AgentReply.php
    - app/Ai/AgentRun.php
    - app/Ai/RunFrontDeskAgent.php
    - app/Ai/FrontDeskUnavailable.php
    - tests/Feature/FrontDesk/FrontDeskAgentTest.php
    - tests/Feature/FrontDesk/FrontDeskPromptTest.php
    - tests/Feature/FrontDesk/AgentFallbackTest.php
  modified:
    - config/gate.php
    - tests/Pest.php
    - tests/Unit/ArchitectureTest.php
    - docs/sprints/sprint-1.md

key-decisions:
  - "The agent declares no tools at all, so the model can only propose words and a label; sending stays in code (T-02-22)"
  - "A thread that ends on an unanswered customer text gets a placeholder assistant turn '(No reply was sent.)' so the turns still alternate before the new prompt"
  - "RunFrontDeskAgent::handle() throws InvalidArgumentException for an empty burst instead of FrontDeskUnavailable, so a harmless duplicate run is never mistaken for an outage"
  - "AgentReply never checks reply length; the 1,600-character limit belongs to the send rule in plan 02-14 (Pitfall 10)"

patterns-established:
  - "Business facts reach the model only through ActiveConfig::file(); front matter is shown as pretty JSON so the app never parses YAML outside the Loader"
  - "Anything a model returns is untrusted input, validated in a readonly value object before use"

requirements-completed: [AGNT-02, AGNT-04]

coverage:
  - id: D1
    description: "The agent built from the live sms agent file, its loaded files and the fresh card returns a validated reply and names the provider and model that answered (AGNT-02, D-36)"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskAgentTest.php#it answers a customer text with a validated reply and names who answered"
        status: pass
    human_judgment: false
  - id: D2
    description: "Instructions hold whole routed files (folder entries expanded), never records/ or unrouted files, and a card rendered fresh from the database (D-36, DEC-03)"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it puts the whole routed files, the agent body and the rules into the instructions"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it never reads records/ or a file the agent does not load"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it renders the customer card fresh from the database on every run"
        status: pass
    human_judgment: false
  - id: D3
    description: "The model sees the whole sent thread as alternating turns (drafts and failed sends left out), the burst as one prompt, and empty or emoji text is handled (D-37, D-38)"
    requirement: AGNT-02
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it shows the thread as alternating user and assistant turns, joining same-role texts"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it leaves drafts, failed sends and texts not yet sent out of the history"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it tells the model when a text was only a picture or was empty"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskPromptTest.php#it passes emoji and accented text through unchanged"
        status: pass
    human_judgment: false
  - id: D4
    description: "Any failure or bad answer from one provider falls through the chain in file order; total failure throws FrontDeskUnavailable listing every attempt; no fallback tries only the primary (AGNT-04)"
    requirement: AGNT-04
    verification:
      - kind: integration
        ref: "tests/Feature/FrontDesk/AgentFallbackTest.php (overload, HTTP 500, malformed json, bogus label, empty reply, all fail, no fallback)"
        status: pass
    human_judgment: false
  - id: D5
    description: "FrontDeskAgent is used only inside App\\Ai and tests, has no tools, and no Feature test can prompt a real model (D-48)"
    verification:
      - kind: unit
        ref: "tests/Unit/ArchitectureTest.php#the front desk agent is only prompted from App\\Ai"
        status: pass
      - kind: integration
        ref: "tests/Feature/FrontDesk/FrontDeskAgentTest.php#it turns a prompt nobody scripted into FrontDeskUnavailable instead of a real model call"
        status: pass
    human_judgment: false
  - id: D6
    description: "The reply wording quality of the RULES text and the before-a-real-client provider data-handling check are judgment calls no test can assert"
    verification: []
    human_judgment: true
    rationale: "Rule wording only proves itself against a live model (checked in the 02-23 phone check), and the ZDR check is a human review of provider terms"

duration: 6min
completed: 2026-10-12
status: complete
---

# Phase 2 Plan 13: Front Desk Agent Summary

**A laravel/ai structured agent (no tools) built from the live agent file, whole routed files and a fresh customer card, with a validated reply and an own provider-chain loop that falls through on any failure**

## Performance

- **Duration:** 6 min
- **Started:** 2026-10-12T02:20:16Z
- **Completed:** 2026-10-12T02:26:29Z
- **Tasks:** 3
- **Files modified:** 13

## Accomplishments

- `FrontDeskAgent` answers in six named fields; `AgentReply::fromStructured()` rejects empty, malformed or out-of-vocabulary answers.
- `FrontDeskPrompt` builds instructions from code-fixed rules, the agent body, every loaded file whole (folder entries expanded to typed files) and the card rendered from the database each run; nothing is read from disk and `records/` is never touched.
- The thread becomes alternating user and assistant turns (drafts and failed sends out, same-role texts joined), the burst becomes one prompt, picture-only and empty texts get a note, emoji pass through unchanged.
- `RunFrontDeskAgent` tries the chain in file order, reports each failure, and throws `FrontDeskUnavailable` with all attempts when none answers.
- `sprint-1.md` has the "Before a real client" provider check (D-37) and the laravel/ai walk-through line.

## TDD Gate Compliance

- Task 1 (tracer): RED `3060253` (12 failing tests; `RED_EVIDENCE_OK` from the JUnit report; target test fails on missing implementation classes inside its body), GREEN `eee6231`, small refactor `f07779e`. Tracer verified end-to-end by re-running the plan verify before expansion.
- Task 2: RED `a2ca502` (7 of 13 tests fail on the planned assertions against the Task 1 first version of `FrontDeskPrompt`; `RED_EVIDENCE_OK`), GREEN `004e26d`.
- Task 3: RED `26d86f7`, GREEN `d7811bf`. Nine of the ten fallback tests and the arch test were already green because the Task 1 loop implemented them (unexpected green, investigated). They were confirmed to be real tests by two mutations (narrowing the catch to `ProviderOverloadedException`, and bypassing validation) that made them fail. The one genuinely new behavior, "a run with nothing to answer prompts no provider", was the failing RED target (`RED_EVIDENCE_OK`).

## Task Commits

1. **Task 1: Tracer, agent returns a validated reply** - `3060253` (test), `eee6231` (feat), `f07779e` (refactor)
2. **Task 2: Prompt carries whole files, fresh card, real thread** - `a2ca502` (test), `004e26d` (feat)
3. **Task 3: Provider fallback** - `26d86f7` (test), `d7811bf` (feat)

**Plan metadata:** committed with this summary (docs).

## Files Created/Modified

- `app/Ai/Agents/FrontDeskAgent.php` - the structured agent, no tools
- `app/Ai/FrontDeskPrompt.php` - RULES, instructions, history, promptText, loadedPaths
- `app/Ai/AgentReply.php` - validated reply value object
- `app/Ai/AgentRun.php` - successful run: reply, provider, model, failed attempts
- `app/Ai/RunFrontDeskAgent.php` - provider chain loop
- `app/Ai/FrontDeskUnavailable.php` - all-providers-failed exception with `attempts`
- `config/gate.php` - `agent.timeout_seconds` 40, `agent.lock_wait_seconds` 5
- `tests/Pest.php` - global agent fake with stray-prompt prevention, `scriptFrontDeskReplies()`
- `tests/Feature/FrontDesk/{FrontDeskAgentTest,FrontDeskPromptTest,AgentFallbackTest}.php`, `tests/Unit/ArchitectureTest.php` - coverage
- `docs/sprints/sprint-1.md` - before-a-real-client note, walk-through line

## Decisions Made

- The agent has no tools, so it cannot send; this is enforced by a test and the docblock (T-02-22).
- A trailing unanswered customer turn gets a `(No reply was sent.)` assistant turn so history always alternates before the new user prompt.
- An empty burst is a caller error (`InvalidArgumentException`), not an outage; plan 02-14 must return early when `ClaimInboundBurst` hands back nothing.
- Reply length is not validated here; the send rule owns the limit.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Alternating turns when an earlier customer text got no reply**
- **Found during:** Task 2 (history)
- **Issue:** A draft held for the owner is left out of history, so a thread could end on a customer turn and the new prompt would be a second user turn in a row, which some providers reject (research assumption A6).
- **Fix:** history appends an assistant turn `(No reply was sent.)` when it ends on a user turn; a test pins it.
- **Files modified:** app/Ai/FrontDeskPrompt.php, tests/Feature/FrontDesk/FrontDeskPromptTest.php
- **Committed in:** a2ca502 (test), 004e26d (code)

**2. [Rule 2 - Missing Critical] Empty burst guard**
- **Found during:** Task 3 (the plan's "a run with nothing to answer prompts no provider" truth had no code path)
- **Fix:** `handle()` throws `InvalidArgumentException` before any prompt when the burst is empty.
- **Files modified:** app/Ai/RunFrontDeskAgent.php, tests/Feature/FrontDesk/AgentFallbackTest.php
- **Committed in:** 26d86f7 (test), d7811bf (code)

**3. [Rule 3 - Blocking] RED commit ordering for the global fake**
- **Found during:** Task 1 RED
- **Issue:** Adding `FrontDeskAgent::fake()` to the Pest `beforeEach` before the class exists crashed every Feature test in the fixture, which is an invalid RED.
- **Fix:** The RED commit carries the test file and the `scriptFrontDeskReplies()` helper; the `beforeEach` line landed in the GREEN commit with the class.
- **Committed in:** 3060253, eee6231

**4. [Rule 1 - Bug] Docblock contained the word the acceptance criterion forbids**
- **Fix:** Reworded the agent docblock so the file does not contain `HasTools`.
- **Committed in:** f07779e

---

**Total deviations:** 4 auto-fixed (2 missing critical, 1 blocking, 1 bug)
**Impact on plan:** All necessary for correctness or a valid TDD gate. No scope creep.

## Issues Encountered

None beyond the deviations above. `requirements.ready-ids` reported AGNT-02 and AGNT-04 not ready because sibling plans in the phase also declare them, so they stay unchecked until the last declaring plan finishes.

## User Setup Required

**External services require manual configuration for live replies.** Set `ANTHROPIC_API_KEY` (Anthropic Console, primary) and `OPENAI_API_KEY` (OpenAI dashboard, fallback) in `.env`. Tests never call them. No USER-SETUP.md was generated; the steps are the two keys above.

## Next Phase Readiness

- Plan 02-14 can call `RunFrontDeskAgent::handle(AgentFile, Conversation, Collection)`, catch `FrontDeskUnavailable` (`->attempts`) to raise the owner alert, and read `config('gate.agent.lock_wait_seconds')`. It must return early on an empty burst.
- Before a real client: the provider data-handling check in `docs/sprints/sprint-1.md` ("Before a real client").
- RULES wording is proven only against a fake until the live phone check.

## Known Stubs

None.

## Self-Check: PASSED

All created files exist on disk; commits 3060253, eee6231, f07779e, a2ca502, 004e26d, 26d86f7 and d7811bf are on the branch; `evaluation-scope` resolves 7 commits for 02-13; the full suite passes (568 tests).

---
*Phase: 02-text-front-desk*
*Completed: 2026-10-12*
