---
status: diagnosed
trigger: "Gap G-02-2.2: the front-desk agent does not know the current date and time. Dana's draft said her visit is 'tomorrow at 9' because she wrote 'tomorrow at 9' on Oct 11; that visit was this morning (Oct 12). FrontDeskPrompt gives the model no current date or time, and the thread messages carry no timestamps."
created: 2026-10-12T00:00:00Z
updated: 2026-10-12T00:00:00Z
goal: find_root_cause_only
audit_acknowledged:
  milestone: v1.0
  at: 2026-10-20
  status: diagnosed
---

## Current Focus

hypothesis: CONFIRMED. Nothing in what the model is sent states "now". FrontDeskPrompt::instructions() builds the instruction text from static rules, the agent file, the loaded business files and the customer card, and never reads the clock; FrontDeskPrompt::history() turns each thread message into a bare body with no time. A relative date in the thread ("Tomorrow at 9 works", written Oct 11) can therefore only be echoed, not resolved. The model has no clock of its own.
test: ran FrontDeskPrompt::instructions() and history() for the UAT conversation (contact 1, conversation 1, burst = message 13) through a read-only tinker probe and looked at exactly what the model is given
expecting: n/a, diagnosis complete
next_action: hand root cause and fix surface to plan-phase --gaps

bug_class: Bohrbug (deterministic: the prompt is built from the same inputs every run). SBFL skipped: no failing test exists for this behaviour. Knowledge base: .planning/debug/knowledge-base.md does not exist; no prior match.

reasoning_checkpoint (diagnose-only, filled for the record):
  hypothesis: "the reply model cannot resolve 'tomorrow' because the prompt carries no current date/time (FrontDeskPrompt.php:69-92) and no per-turn time (FrontDeskPrompt.php:106-146)"
  confirming_evidence:
    - "probe output: the only dates in the instructions are the two hours.md closure dates and the customer card; no line says what day or time it is now"
    - "probe output: history() returns four bare turns, 'Tomorrow at 9 works. It is a Carrier unit, about eight years old.' with no time"
    - "the same reply pair shows the code side getting 'tomorrow' right (holding reply 15: 'when we open at 8 am tomorrow', from BusinessClock reading now() in the business zone) while the model's draft 14 echoed 'tomorrow at 9' verbatim"
  falsification_test: "give the model a 'Right now' line plus a time on each history turn and replay Dana's burst; if it still says 'tomorrow at 9' the cause is something else (not run: it needs a live model call)"
  fix_rationale: "supplying now and per-turn times removes the information gap itself; a prompt rule alone ('be careful with relative dates') would not"
  blind_spots: "no live model call was made, so the claim that a dated prompt yields a correct draft is by reasoning, not by experiment; the model may still restate a visit time it was told never to promise (that is G-02-2.4's territory)"
  candidate_causes:
    - "code: prompt builder never reads the clock (confirmed)"
    - "environment: the app clock is UTC (config/app.php:68), so a naive now() would give the wrong weekday after 7 pm Chicago; not a cause of this run (21:30 UTC is 4:30 pm CDT, same date) but a trap for the fix"
    - "data: Dana's seed thread dated Oct 11 and the UAT on Oct 12; a legitimate multi-day thread, not bad data"
  and_gate: "mostly no. The missing 'now' is necessary and sufficient to produce the wrong draft. The missing per-turn times are a contributing weakness, partly masked because the customer card already has business-zone timestamps (see Evidence); with 'now' alone the model would have to match turn text to card entries by hand."

## Symptoms

expected: The front-desk agent knows the current date and time, so relative dates in the thread ("tomorrow at 9") are read correctly.
actual: Dana's draft said her visit is "tomorrow at 9" because she wrote "tomorrow at 9" on Oct 11; that visit was this morning (Oct 12).
errors: none (the model answered confidently with a wrong relative date)
reproduction: Test 2 in .planning/phases/02-text-front-desk/02-UAT.md (Dana's conversation in database/database.sqlite, read-only).
started: found in Phase 2 UAT, 2026-10-12. Always broken: the prompt has never carried a date.

## Eliminated

- hypothesis: the date is available to the model through the business files the agent loads (profile.md carries the time zone)
  evidence: the example agent loads business/profile.md (examples/example-hvac/agents/front-desk.md), so the JSON front matter shows "timezone": "America/Chicago", but that names a zone, not a moment. hours.md adds closure dates (2026-11-26, 2026-12-25) which are not "today". Probe: no line of the 10,603-character instructions says what day or time it is.
  timestamp: 2026-10-12

- hypothesis: the SDK or the provider injects the date
  evidence: app/Ai/Agents/FrontDeskAgent.php returns the instructions string verbatim and declares no tools or middleware; vendor/laravel/ai adds only cache_control when the #[CacheInstructions] attribute is present, and the app does not use it. No clock text is added.
  timestamp: 2026-10-12

## Evidence

- timestamp: 2026-10-12
  checked: app/Ai/FrontDeskPrompt.php (whole file), app/Ai/RunFrontDeskAgent.php, app/Ai/Agents/FrontDeskAgent.php
  found: instructions() (lines 69-92) joins RULES, the agent body, each loaded file and the customer card. history() (lines 106-146) emits role + textOf($message), and textOf() (lines 192-203) returns the bare body. No call to now(), Carbon or any message timestamp anywhere in the prompt path. RunFrontDeskAgent builds instructions and history once per run, before the provider loop (lines 42-44), so every fallback provider sees the same prompt.
  implication: the prompt has no source of "now" and no per-message time. The gap is in the prompt builder, not in the agent class or the SDK.

- timestamp: 2026-10-12
  checked: read-only probe of the live database (php artisan tinker, instructions() + history() for contact 1 / conversation 1 / burst = message 13)
  found: history() returns exactly: user "My AC is blowing warm air"; assistant "Sorry to hear that. Can we send someone tomorrow morning?"; user "Tomorrow at 9 works. It is a Carrier unit, about eight years old."; assistant "(No reply was sent.)". The prompt is "How much for a new unit?". Draft 14 then says "your AC repair visit is still on our list for tomorrow at 9". The live messages: 1/2/3 on Oct 11 (12:31, 13:31, 14:31 UTC = 7:31, 8:31, 9:31 am CDT), burst message 13 on Oct 12 21:30 UTC = 4:30 pm CDT.
  implication: reproduces the UAT report. The model echoed Dana's Oct 11 "tomorrow at 9" because nothing told it the thread's "tomorrow" was Oct 12, already past by 4:30 pm.

- timestamp: 2026-10-12
  checked: the customer card inside the instructions (app/Records/CustomerCard.php:56-95, 143-146), rendered from the live database
  found: the card is NOT timestamp-free. Its Timeline lists every activity with a business-zone time ("### 2026-10-11 09:31 · Text from customer", "### 2026-10-12 16:30 · Text from customer", "Last activity: ...", format 'Y-m-d H:i' via displayTimezone()). So the only dated material the model had was a newest-first timeline inside the card, which RULES tells it to treat as information; the conversation turns themselves (history) are bare.
  implication: the user's note that "thread messages carry no timestamps" is true of history() turns. The model could in principle infer "now" from the newest card entry and map the turn text to a card entry, but nothing tells it to, and it did not. The fix should state "now" explicitly and put the time on the turns; the card stays as is. Use the same format family and the same zone accessor so the two agree.

- timestamp: 2026-10-12
  checked: how the code side handles "tomorrow" for the same exchange (app/Icm/BusinessClock.php:57-73, 124-131) and holding reply 15
  found: holding reply 15 (sent 21:30:37 UTC) says "when we open at 8 am tomorrow", computed from now() converted to the business zone. It is correct (Oct 13). Draft 14, written by the model in the same run, is wrong.
  implication: the code already computes relative days correctly from the clock in the business zone; only the model lacks the clock. BusinessClock::dayWords() is the precedent for how the app phrases day words.

- timestamp: 2026-10-12
  checked: business time zone accessors (app/Icm/ActiveConfig.php:45-61) and validation (app/Icm/Validator.php:159, app/Icm/FolderSync.php:169-171, app/Icm/PlainWords.php:33)
  found: ActiveConfig::timezone() reads business()['timezone'] (profile.md front matter; example-hvac has "timezone: America/Chicago") and throws NoActiveVersion when nothing is live. ActiveConfig::displayTimezone() returns the same value but falls back to config('app.timezone') instead of throwing. The Validator requires the field and checks it with the Laravel rule 'timezone:all', and a bad zone blocks the version from going live, so any active version has a valid IANA zone. config/app.php:68 sets the app timezone to 'UTC', so now() without a conversion would be UTC.
  implication: the exact accessor is ActiveConfig::displayTimezone() (the one CustomerCard, DevChat and ConversationThread already use), injected already in FrontDeskPrompt as $this->activeConfig. Nothing new to validate. Always convert from UTC: after 7 pm Chicago the UTC date is already tomorrow.

- timestamp: 2026-10-12
  checked: where promptText() flows (app/Actions/RunFrontDesk.php:105 AsksForPerson::inText($this->prompt->promptText($burst)); :138 decideSendOrDraft->decide($run, promptText($burst), $composed); app/SendRule/JevClassifier.php:26-47)
  found: promptText() is not only what the model reads; it is also the customer_message handed to the ask-for-a-person check and to the Jev classifier.
  implication: the date and any timestamps must NOT be added to promptText(). Prefixing the burst would put system text into the classifier input and shift its scores (G-02-2.1 is already about that noise). The existing test 'joins a burst into one prompt in arrival order' (FrontDeskPromptTest.php:163-172) asserts the exact string and guards this. Put "now" in instructions() and the times in history() only. The burst itself is seconds old, so "now" covers it.

- timestamp: 2026-10-12
  checked: prompt caching / static-prefix ordering (app/Ai/Agents/FrontDeskAgent.php, grep CacheInstructions in app and config, vendor/laravel/ai/src/Gateway/Anthropic/Concerns/BuildsTextRequests.php:85-100, config/ai.php:34-40)
  found: the app does not opt in to #[CacheInstructions] or #[CacheToolDefinitions], so no explicit caching happens today. The only 'cache' key in config/ai.php is for embeddings. If CacheInstructions were added, the Anthropic gateway stamps cache_control on the last block of the single instructions string, so the whole string must be byte-identical for a hit; the customer card (per contact, per run) at the end already defeats that.
  implication: ordering does not matter today. For future-proofing and for the OpenAI fallback's automatic prefix caching (it keys on the longest identical prefix), put the changing "Right now" section LAST, after the customer card, so everything above stays a stable prefix. Resolve it to the minute, not the second.

- timestamp: 2026-10-12
  checked: .planning/debug/outbound-sorts-by-draft-time.md (G-02-1.2) against FrontDeskPrompt::history() and the real rows
  found: messages has no sent_at; threads order by created_at then id (app/Models/Conversation.php:73). Message 14 (the approved draft the UAT is about) has created_at 21:30:36 but was sent 21:33:55 (resolved_at); the card's reply_sent activity for it is correctly stamped 21:33:55 (OutboundSender.php:273 uses the real send time as occurred_at). history() shows sent drafts (status sent) among the old turns by created_at.
  implication: a per-turn time based on created_at would be wrong for every owner-approved draft (off by however long it waited). The turns must show the shared "when this happened" value from G-02-1.2 (sent_at, falling back to created_at). G-02-2.2 therefore depends on G-02-1.2's Message accessor, or must ship together with it.

- timestamp: 2026-10-12
  checked: the `break` in history() (FrontDeskPrompt.php:112-114) once ordering moves to send time
  found: history() stops at the first burst message. G-02-1.2's note already flags that a draft sent after the burst began would sort after the burst and be dropped. With timestamps on the turns, any out-of-order or dropped turn becomes visible to the model, which makes this decision matter more.
  implication: decide in the same plan whether history should skip burst messages (continue) or keep stopping at the first one; both need to use the same "when" key as the ordering.

- timestamp: 2026-10-12
  checked: existing tests that would change, and how time is frozen
  found: (1) Exact-string history assertions that break when a time prefix is added: tests/Feature/FrontDesk/FrontDeskPromptTest.php 'shows the thread as alternating user and assistant turns, joining same-role texts' (91-106), 'leaves drafts, failed sends and texts not yet sent out of the history' (108-124), 'starts with a user turn when the business texted first' (126-138; the owner text gets the prefix, the placeholder does not), 'keeps the turns alternating when an earlier customer text got no reply' (140-153); and tests/Feature/FrontDesk/DraftReplacementTest.php 'leaves a withdrawn draft out of the history the next run reads' (about lines 121-135). Placeholder turns ('(No reply was sent.)', '(The business texted first.)') need no prefix. (2) Instruction assertions use toContain (FrontDeskPromptTest.php 49-89, IntakeAnswersTest.php 223-229), so a new section does not break them. (3) FrontDeskAgent::assertPrompted(...) checks use str_contains on $prompt->prompt (FrontDeskAgentTest.php:63, OptOutTest.php:305), unaffected because promptText() is unchanged. (4) Test clock tools in use: $this->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago')) (HoldingReplyTest, HandoffTest, OwnerAlertTimingTest and others), $this->freezeTime() (SyncTest, FrontDeskFailureTest), Carbon::setTestNow(...) (CustomerCardTest.php:49, OwnerReplyTest.php:237), $this->travel(5)->minutes(), and explicit 'created_at' => $x in factory create() (GateExportTest, ReplyCapTest). The example folder's zone is America/Chicago and the app zone is UTC (phpunit.xml sets no TZ).
  implication: tests must freeze or set times before creating factory messages, because Message::factory() stamps created_at with the real now(). To prove the zone conversion, freeze at an evening time where Chicago and UTC are on different calendar days (for example 2026-10-12 20:30 America/Chicago = 2026-10-13 01:30 UTC) and assert the Chicago weekday/date appears.

- timestamp: 2026-10-12
  checked: Carbon mutability on model dates (grep Date::use / CarbonImmutable in app/Providers: none; CustomerCard.php:145 and RecordsWriter.php:290 use ->clone()->setTimezone())
  found: model date attributes are mutable Carbon. The Blade views call $message->created_at->setTimezone($timezone) without clone (conversation-thread.blade.php:52, dev-chat.blade.php:10).
  implication: the new accessor/format in history() must clone before setTimezone, or it will change the time zone of the model's cached attribute for any later reader of the same instance.

## Resolution

root_cause: FrontDeskPrompt never tells the model what moment it is and never labels the conversation turns with when they were sent. instructions() (app/Ai/FrontDeskPrompt.php:69-92) is built from static rules, the agent file, the loaded business files and the customer card, with no call to the clock; history() (lines 106-146) and textOf() (lines 192-203) pass each message as a bare body. A reply model has no clock of its own, so the thread's "Tomorrow at 9 works" (sent Oct 11, 9:31 am Chicago) was echoed as "tomorrow at 9" on Oct 12 at 4:30 pm. The code paths that compute relative days (BusinessClock, holding reply 15 "8 am tomorrow") already work from now() in the business zone; only the model lacks it. Contributing: the per-turn times are absent too, though the customer card's timeline partly carries them (business-zone 'Y-m-d H:i'), which is why "now" is necessary and per-turn times are the second half of the fix.
fix: (not applied, diagnose-only)
verification: n/a
files_changed: []

### Notes for the planner (non-binding)

1. "Right now" in instructions(), last section, after the customer card. Build it from now() converted with $this->activeConfig->displayTimezone() (already available as FrontDeskPrompt::$activeConfig; same accessor as CustomerCard so card times and "now" share a zone). Include weekday, date, time to the minute, and the zone name, for example "It is Monday, October 12, 2026, 4:55 pm in America/Chicago, the business's time zone." Use clone/CarbonImmutable; do not rely on the UTC default. Nothing to validate: Validator.php:159 already requires a real IANA zone before a version can go live.
2. RULES (FrontDeskPrompt::RULES const, one or two lines): earlier texts start with the time they were sent in brackets; "today", "tomorrow", "this morning" in an earlier text mean the day that text was sent, not the day now; never write a bracketed time in a reply. The last clause guards against the model copying the prefix into the reply it writes (assistant turns carry prefixes too). A test that RULES contains the clause is cheap. Nothing in AgentReply::fromStructured() rejects a reply that starts with a bracketed time, so the rule is the only guard unless the plan adds a check.
3. history(): prefix each included message's text with its time in the business zone, using the one Message accessor from G-02-1.2 ("when this happened": sent_at, falling back to created_at; inbound customer texts use created_at, which is the arrival time). Format with weekday + date + time, for example "[Sun Oct 11, 9:31 am] ...". Joined same-role texts keep one prefix per text. Placeholders ("(No reply was sent.)", "(The business texted first.)") stay unprefixed. Do not touch promptText(): it also feeds AsksForPerson::inText and the Jev classifier (RunFrontDesk.php:105, :138).
4. Sequencing: do G-02-1.2's accessor (and migration/backfill) first or in the same plan; otherwise an owner-approved draft like message 14 shows its draft-creation time. The `break` decision (FrontDeskPrompt.php:112-114) belongs to the same plan.
5. Prompt caching: not enabled (no #[CacheInstructions] anywhere), so no ordering constraint today. Keep the changing section last and minute-resolution anyway.
6. Tests to add (Pest, tests/Feature/FrontDesk/FrontDeskPromptTest.php): instructions contain the weekday/date/time in the business zone when the clock is frozen at an evening where Chicago and UTC are on different days; the "Right now" section is the last section (after the customer card); history turns carry the right times, including an approved draft sent later than it was written (travel between creation and approval); promptText unchanged (existing test). Tests to update: the five exact-string history tests listed in Evidence (freeze the clock or set 'created_at' before creating messages, and add the expected prefix).
7. Files NOT to touch: app/Icm/ActiveConfig.php (accessor exists), app/Icm/Validator.php (zone already validated), examples/example-hvac/rules/triage.md (the user's uncommitted edit, belongs to G-02-2.1), RunFrontDeskAgent.php (builds prompt once per run; no change needed).
8. Out of scope but adjacent: draft 14 also restated a visit time ("still on our list for tomorrow at 9") that no business file supports and that RULES forbids promising; that is G-02-2.4. Giving the model the date will make it say "this morning" or ask, but it will not by itself stop it restating a visit.
