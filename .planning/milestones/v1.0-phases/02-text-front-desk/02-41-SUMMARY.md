---
phase: 02-text-front-desk
plan: 41
subsystem: cartographer-map
tags: [map, citations, gap-closure, sent_at, d-56, holding-reply]
status: complete

requires:
  - phase: 02-text-front-desk
    provides: the code changes of 02-35 to 02-40 (per-question Jev bars, sent_at and thread order, send-time reply cap, newest holding reply, customer's-phone statuses, dated prompt, customer's last message in the Needs you card)
provides:
  - map cards whose every citation of a changed file lands on the symbol the card names
  - message card that tells a cold reader about sent_at, send-time order, happenedAt(), onTheCustomersPhone() and the D-56 bars
  - conversation card that says the reply cap counts at send time
  - process card that says the prompt is dated, each Jev question has its own bar and one holding reply goes out per wait
affects: [end-of-phase UAT, Phase 02.1 map work]

actuals:
  tokens: 14000
  tasks: 3
  commits: 3
plan_head_before: 2bac8cb1f757427a30154025f4bc807c432c2b42
plan_head_after: acc2c1f9309f976a307e241a2d33624c4ffe1bb6

tech-stack:
  added: []
  patterns:
    - "A :N shorthand is used only where the file it continues is the last one named in the same sentence; otherwise the file is named again"
    - "A card citing a symbol points at the symbol (function line), a range is widened to cover what the card says it covers"

key-files:
  created: []
  modified:
    - map/objects/messages/message-and-verdict.md
    - map/objects/conversations/conversation-and-pause.md
    - map/processes/answer-inbound.md
    - map/objects/folder/agent-file-and-version.md
    - map/objects/contacts/contact-opt-out.md
    - map/objects/intake/intake-answer.md
    - map/VERIFICATION.md

key-decisions:
  - "map/CLAUDE.md was not edited: config/gate.php:41 still holds the approval log sentence and no catalog line changed, so its header stamp (2026-10-12 @ 04f95e7) stays"
  - "map/VERIFICATION.md changed in one line number only (entry 1, FrontDeskPrompt.php:106 to :112); its intro stamp stays"

requirements-completed: [AGNT-02, GATE-01, GATE-04]

coverage:
  - id: D1
    description: "Every .php:N citation in map/ points at an existing file and a non-blank line (185 distinct citations, 0 BAD)"
    requirement: GATE-04
    verification:
      - kind: command
        ref: "plan Task 3 whole-map citation check over map/"
        status: pass
    human_judgment: false
  - id: D2
    description: "Each citation of a changed file points at the symbol its card names (listed below, checked by reading the source line)"
    requirement: AGNT-02
    verification:
      - kind: command
        ref: "cites.py listing of every citation with its source line, read by the executor"
        status: pass
    human_judgment: false
  - id: D3
    description: "The cards describe send time, send-time order, the customer's-phone statuses, the per-question bars, the dated prompt and the newest-holding-reply rule, each clause with a citation"
    requirement: GATE-01
    verification:
      - kind: command
        ref: "grep for sent_at, happenedAt, onTheCustomersPhone, D-56, replyCapReached, holdingAlreadySent, Right now in the named cards"
        status: pass
    human_judgment: true
    rationale: "Whether a cold reader finds the prose clear is a judgment no test asserts; Ian reads the cards at UAT"
---

# Phase 02 Plan 41: Re-point the cartographer map to the final code Summary

**Every citation in the cartographer map now lands on the symbol its card names after 02-35 to 02-40, and the message, conversation and process cards describe send time, send-time order, the customer's-phone statuses, the per-question Jev bars (D-56), the dated prompt and the newest-holding-reply rule.**

## Performance

- **Duration:** about 30 min (the wall clock was not recorded at the start)
- **Tasks:** 3 (1 tracer, 2 auto)
- **Files modified:** 7 under `map/` (plus this SUMMARY and the tracking files)
- **Start HEAD:** 2bac8cb (the last 02-40 commit)

## Accomplishments

- Tracer: the message card carries `sent_at` (migrations 000010 and 000011), send-time order, `Message::happenedAt()`, `MessageStatus::onTheCustomersPhone()` with its two readers, the D-56 bars with the threshold array stored on the verdict, and D-56 in See. The tracer's citation check printed no BAD line, so expansion went ahead.
- The conversation card says the reply cap counts each text when it went out (`sent_at`, or `created_at` while queued) and cites `replyCapReached`; "has many messages" now says "in the order they happened".
- The process card gains, with citations: step 7 the Right now section and per-text send times (burst untimed because it also feeds Jev and the asks-for-a-person check), step 9 the per-question bars, step 10 the newest-holding-reply rule (`holdingAlreadySent`).
- The agent card's Prompt bullet says the instructions end with a Right now section after the customer card.
- Whole-map check: 185 distinct `file.php:N` citations, 0 BAD, 0 fenced blocks, no `04f95e7` left in any card this plan re-checked.

## Task Commits

1. **Task 1 (tracer): message card** - `3ed646b` (docs)
2. **Task 2: conversation and process cards** - `9cd3238` (docs)
3. **Task 3: agent, opt-out, intake cards, work list** - `acc2c1f` (docs)

## Citations of changed files, with the source line each points at

Format: citation, then the statement or symbol at that line in the code as 02-40 left it (HEAD acc2c1f). Shorthands (`:N`) are listed with the file they continue.

### message-and-verdict.md
- Message.php:44 `class Message`; :54 `casts()`; :79-82 `happenedAt()`; :89 `conversation()`; :109 `ownerAlerts()`
- 2026_10_12_000010_add_sent_at_to_messages_table.php:24 `$table->timestamp('sent_at')->nullable()`
- 2026_10_12_000011_backfill_sent_at_on_messages.php:19-22 update from `coalesce(resolved_at, created_at)`
- OutboundSender.php:185 queued message created in `deliver`; :214 draft claimed in `deliverExisting`; :267 `'sent_at' => $sentAt` in `transmit`; :320 the same in `markSentAfterRecordingFailed`
- Conversation.php:73-76 `messages()` ordered by `coalesce(sent_at, created_at)`, then id
- ConversationThread.php:120 `messages()`; :123 the `whereNotIn` draft/withdrawn filter
- DevChat.php:88 `thread()`; :94 `whereIn('status', MessageStatus::onTheCustomersPhone())`
- FrontDeskPrompt.php:13 `AiMessage` import; :123 the `onTheCustomersPhone()` filter in `history()`; :219 `timeOf()`
- RecordsWriter.php:261 `waitingSince()`
- MessageStatus.php:9-17 the enum cases; :26-29 `onTheCustomersPhone()`
- RunFrontDesk.php:334-341 `if ($replaced !== null)` withdraw as replaced; :346 draft created; :113-117 no agent file for texts
- SendFixedReply.php:48 `kind => HoldingReply`; :72 `kind => HandoffReply`
- config/gate.php:71-80 the `classifier` array; TriageReading.php:61-65 the `GATE_CHECKS` loop reading `{check}_max_yes`; DecideSendOrDraft.php:59 `'thresholds' => config('gate.classifier')`; :49 `'version' => 1`
- tests/Pest.php:61 `new FakeClassifier` bound (was :45, see Deviations)

### conversation-and-pause.md
- Conversation.php:15 class; :41 `contact()`; :51 `pausedBy()`; :56-63 `isPaused()`; :73 `messages()`; :83 `intakeAnswers()`
- config/gate.php:124-127 `reply_cap`; RunFrontDesk.php:237-247 `replyCapReached()` (was :239-246, widened to the whole function); :100 pause on reply cap; :260 pause on asked for a person; :92 paused early exit
- OutboundSender.php:117 `whyBlocked`; :128 closed refusal; :132 paused refusal (cited twice)
- ConversationThread.php:188 `pausedBanner()`

### answer-inbound.md
- RunFrontDesk.php:82 `handle`; :82-109 early exits; :84 `answerDanger`; :86 opted-out; :92 paused; :98 `replyCapReached` call; :105 asks-for-a-person text check; :256-261 `handOffToOwner`; :111 `AgentFile::forChannel`; :113-117 no agent; :119-125 every model failed; :127 `wantsPerson`; :133 `recordIntakeAnswers`; :134 name fill; :138 `decide`; :140-141 life safety; :143 `asksForPerson`; :156 `outboundSender->send`; :166-170, :172-177, :179 send refusals; :326-365 `storeDraft` transaction; :369-371 owner alert; :373-375 holding reply
- config/gate.php:142 `life_safety.lock_wait_seconds`; :103 `chain_budget_seconds`; :71-80 `classifier`
- FrontDeskPrompt.php:86 `rightNow()` added; :207-213 `rightNow()`; :138 `timeOf()` per earlier text; :219-224 `timeOf()`; :164-167 `promptText()`; :112 `history()` (Does not hit)
- RunFrontDeskAgent.php:42-43 `instructions()` and `history()` built
- TriageReading.php:61-65 the per-check bar loop
- SendFixedReply.php:94-123 `holdingAlreadySent()`; :96-100 newest holding reply found with `reorder('id','desc')`

### agent-file-and-version.md
- FrontDeskPrompt.php:67 agent body added; :180 `foreach ($agent->loads() as $entry)`; :85 customer card; :86 and :207-213 Right now
- config/gate.php:101 `timeout_seconds`; :103 `chain_budget_seconds`
- RunFrontDesk.php:111, :113-117, :119-125, :256 (`handOffToOwner`)

### contact-opt-out.md
- OutboundSender.php:122 `isOptedOut()` refusal (Enforced bullet); :123-125 the date and "Opted out by texting STOP on" sentence (was :122, widened to cover the wording); :363 `sms_opted_out_at => now()` in `recordCarrierOptOut`
- RunFrontDesk.php:86 opted-out early exit; :212 `alertAboutOptedOutBurst`

### intake-answer.md
- RunFrontDesk.php:133 the `recordIntakeAnswers->handle` call

### map/CLAUDE.md and map/VERIFICATION.md
- config/gate.php:41 "approval log" sentence (unchanged, still true; both files)
- VERIFICATION.md entry 1: FrontDeskPrompt.php:112 `history()` (was :106)

## Files Created/Modified

See key-files. Nothing outside `map/` was edited; `git diff --name-only 2bac8cb..HEAD` lists only `map/` files before this SUMMARY.

## Decisions Made

- `map/CLAUDE.md` is left untouched: no catalog line changed, so its header stamp stays at 2026-10-12 @ 04f95e7 as the plan says.
- Where a shorthand would now continue the wrong file, the file is named again instead of reordering the sentence (process card steps 2, 7, 8, 10).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Stale `tests/Pest.php:45` citation in the message card**
- **Found during:** Task 1
- **Issue:** the card said tests swap in `FakeClassifier` at `tests/Pest.php:45`, but line 45 is a config key; the binding is at line 61 and was at line 61 at the old stamp too, so the citation was already wrong before 02-35 (Pest.php is not on the plan's changed-file list)
- **Fix:** re-pointed to `tests/Pest.php:61`
- **Files modified:** map/objects/messages/message-and-verdict.md
- **Commit:** 3ed646b

**2. [Rule 1 - Bug] Pause card Surfaces count contradicted its Shape**
- **Found during:** Task 2
- **Issue:** Shape lists eight pause call sites (four from the safety line, after CR-01), Surfaces still said "seven (the safety line's three included)"
- **Fix:** Surfaces now says eight and four
- **Files modified:** map/objects/conversations/conversation-and-pause.md
- **Commit:** 9cd3238

**3. [Rule 1 - Bug] Ambiguous `:N` shorthands**
- **Found during:** Task 2 and 3
- **Issue:** in answer-inbound step 2, `:84-87` and `:127-140` follow `config/gate.php`, so a reader would take them for gate.php lines (they are SendLifeSafetyLine.php). Steps 7, 8 and 10 opened with a bare `:111`, `:133` and `:156` that only made sense by continuing the previous step
- **Fix:** named the file in each (`SendLifeSafetyLine.php:84-87`, `RunFrontDesk.php:111`, `:133`, `:156`)
- **Files modified:** map/processes/answer-inbound.md
- **Commits:** 9cd3238, acc2c1f

**Total deviations:** 3 auto-fixed (all Rule 1, all map prose). **Impact:** none on scope; every edit stays under `map/`.

### Plan notes (not code deviations)

- Task 3's acceptance command for VERIFICATION.md (`grep '^[-+][^-+]'` over `git log -p`) filters out any diff line that itself begins with "- " (the file's bullets), so it prints nothing even for a changed bullet. I checked with `git diff --word-diff=porcelain` instead: the only change is `FrontDeskPrompt.php:106` to `:112`.
- The tracer gate: re-ran the automated verify, no BAD line, logged "Tracer verified end-to-end, expanding".

## Issues Encountered

None.

## Human checks

None in the plan; nothing deferred to the windows ledger.

## Known Stubs

None (map prose only).

## Threat Flags

None. No code, route, config or schema file was touched.

## Verification

- Whole-map citation check: 185 distinct citations, 0 BAD.
- `grep -rc '^ *```' map/`: 0 in every file.
- `wc -l map/CLAUDE.md`: 45 (under 60).
- `git diff --name-only 2bac8cb..HEAD`: only `map/` files.
- `php artisan test --compact`: 1074 passed, 4449 assertions (the suite was not needed for a map-only change; run as asked in the run notes).

## Next Phase Readiness

This was the last plan of the gap-closure run. Ready for the phase's end-of-phase verification and UAT.

## Self-Check: PASSED

- Commits 3ed646b, 9cd3238, acc2c1f are on HEAD's history.
- All seven edited map files and map/CLAUDE.md exist.
