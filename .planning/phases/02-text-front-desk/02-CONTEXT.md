# Phase 2: Text Front Desk - Context

**Gathered:** 2026-10-11
**Status:** Ready for planning

<domain>
## Phase Boundary

A customer who texts gets a reply from an AI front desk built from an `agents/*.md` file, and nothing risky is sent without a person (SPEC Sprint 1). Delivers: agent files validated and compiled by the sync; `ProcessInbound` running the agent with routed files from `ActiveConfig` plus the fresh customer card; intake questions asked and answers stored; a send-or-draft gate enforced in code with a code-first layer and a Jev classifier behind an interface; the AI disclosure, `never_say` and `sign_off`; fixed owner-written replies (holding, handoff) and a code-fixed life-safety line; a text alert to the owner; drafts reviewed in the dashboard; Take over and hand back; STOP/opt-out handling; the Twilio driver behind `TextChannel`; the suite on Postgres in CI.

Requirements: AGNT-01, AGNT-02, AGNT-03, AGNT-04, GATE-01, GATE-02, GATE-03, GATE-04, TXT-01, INFRA-01.

**Scope split (decided this discussion):** the owner-input additions in `docs/phase-2-owner-input.md` are divided between this phase and a new **Phase 2.1, the owner loop**, which has to be added to ROADMAP.md (with new requirement IDs) before 2.1 is planned. Phase 2.1 holds: staff users with phone, email, alert choices and a "can approve drafts" switch, invited by the owner; owner-only user management and Sync Now (the first role split); sign out everywhere and remove user; approving by text reply with a short code (K7); email alerts; the "handled" follow-up naming who acted and how; re-alerts and a backup recipient for a draft nobody acts on; assist mode and the per-business quiet period; the after-hours on-call switch; `escalate_to` naming a staff user; the LLM fallback classifier driver.

Not in this phase: anything in the 2.1 list above, booking and reminders (Phase 3), production deploy (DEPL-01), texting web-form leads first (deferred, see below).

</domain>

<decisions>
## Implementation Decisions

### The gate: when a reply sends on its own (GATE-01)
- **D-01:** An agent reply sends on its own only when **three checks all pass**: (1) the agent's own label for the reply, from structured output, is in `send_on_own`; (2) the code checks find nothing (dollar amounts, a number next to a price word, discount or promise phrases, any `never_say` phrase); (3) Jev labels it sendable with certainty at or above the send threshold. Any hold, failure or uncertainty makes it a draft. The replying model never sends on its own say-so.
- **D-02:** The code checks are a **hard veto**. Jev is not injection-hardened, so Jev can only hold, never force a send.
- **D-03:** **Fail closed.** If the Jev call fails, times out (short timeout) or returns certainty below the threshold, the reply is held as a draft. The send threshold is decided in code on Jev's certainty, starting at **0.85**.
- **D-04:** **One Jev call per reply.** The state holds the customer's message and the draft reply as two named fields. Questions: one choice question for intent (the list always includes `other`) and yes/no questions for price, promise, discount, complaint and life-safety.
- **D-05:** Jev receives **only message text**, never the customer's name or phone. Pin the model version **`jev-1.13.0`**, never `jev-latest`. Confirm the API shape against docs.typesafe.ai when building the driver (titus-site already calls Jev through OpenRouter's Decisions endpoint over plain HTTP).
- **D-06:** Triage sits behind a `Classifier` interface with a Jev HTTP driver and a fake for tests. The LLM fallback driver is Phase 2.1 or later (needed if TypeSafe can't offer ZDR). Jev is a classifier, not an agent, so calling it over plain HTTP instead of laravel/ai does not conflict with DEC-07.
- **D-07:** Every check's verdict is **stored on the message**: the agent's label, which code rule matched, Jev's answers and certainty. The draft shows the owner a plain-words reason ("Held: asks about price"). This record, together with the owner's approve, edit and discard actions, is the labeled data for tuning later and must be exportable. — **Reversibility:** costly — the stored shape becomes the calibration dataset; changing it later loses or splits history.

### Action vocabulary and the hard floor
- **D-08:** The action keys are fixed. `send_on_own` accepts `intake_question`, `scheduling`, `faq_answer`. `draft_for_owner` accepts `price`, `promise`, `discount`, `complaint`, `other`. **price, promise, discount and complaint can never go in `send_on_own`**: the validator rejects it with a plain-words error. An unknown key is an error. An action in neither list is drafted.
- **D-09:** `owner_alert` in `rules/sending.md` becomes **structured** (channels, trigger events, after-hours behaviour) and is validated. The example folder changes with it. It holds *what* and *when*, never a person's phone number.

### Fixed replies and triage files
- **D-10:** New file `rules/replies.md` (type `set_replies`) holds the owner-written **holding reply** and **handoff reply** and the life-safety placeholders, using known placeholders such as `{owner_name}`, `{next_open}`, `{utility_phone}`. The validator rejects a missing reply or an unknown placeholder.
- **D-11:** New file `rules/triage.md` (type `triage`) holds the Jev intent list and yes/no questions, so the questions live in the folder (method rule 7).
- **D-12:** The sync runs **the same code checks** on `rules/replies.md` (dollar amounts, discount or promise phrases, `never_say`) and rejects a reply that fails them. Fixed replies skip the classifier when they send, because code wrote them.
- **D-13:** Times in the holding and handoff replies are **computed in code from `business/hours.md`** (next opening time, closures included), so the promise is always true. Example: "Mike handles pricing himself. I've sent him your question and he'll get back to you when we open at 8 tomorrow."
- **D-14:** The customer gets **at most one holding reply** while a draft is waiting in a conversation.

### Life-safety line
- **D-15:** Triggers: gas smell; fire or smoke; a carbon monoxide alarm; sparking; water reaching outlets, the electrical panel or plugged-in appliances. Plain flooding (a burst pipe, water on the floor) is **not** life-safety. It's an urgent service request for the 2.1 on-call switch. Code keywords are checked first and Jev's life-safety question second; **when unsure, send.**
- **D-16:** The **core sentence is fixed in code**: get to safety and call 911 from outside (for water: stay out of the water, away from the electrical, and call 911). It never gives repair or shut-off steps. The folder only fills placeholders such as `{utility_phone}`, so no folder edit can weaken it.
- **D-17:** The life-safety line **sends first**, before any other reply, **skips the burst wait**, and sends even in a paused or taken-over conversation (the only thing that does). When it's the first message a customer gets, the safety sentence comes first and the AI disclosure follows in the same text.

### Owner alert (Phase 2 part)
- **D-18:** Phase 2 alerts by **text only** (email is 2.1). The recipient's phone lives on the **user record** (new `phone` column on `users`), which the owner sets on **Filament's profile page**. — **Reversibility:** one-way — a users migration; 2.1 builds staff users on the same column.
- **D-19:** If `owner_alert` asks for a text and no user has a phone on file, the Business Folder page shows a plain-words warning and the dashboard flags held drafts. An alert never fails silently.
- **D-20:** **Timing:** urgent events (complaint, a request for a person, life-safety, any new message in a paused conversation) alert right away. Price, promise and discount drafts made after hours alert at the next opening time in `hours.md`. Set per business in `owner_alert`.
- **D-21:** The alert text is **built by code from a template**, with no model call: the customer's name (or number), why it was held, their last message quoted and cut short, the draft cut short, and the link. Links inside the quoted customer message are replaced with `[link]`. The dashboard link always goes **last, on its own line**, so the only tappable link is ours.
- **D-22:** Alert links point to the **conversation**, not to a draft, so an older alert still opens the newest draft. A replaced draft sends a new alert only if urgency rises (complaint, a request for a person, life-safety).

### Asking for a person, takeover and the paused state (GATE-02, GATE-03)
- **D-23:** A customer who asks for a person gets the **handoff reply** (from `rules/replies.md`, with the time computed from hours). The conversation enters the **same paused state as Take over** (one mechanism, one hand-back), and `escalate_to` is alerted right away. In Phase 2, `escalate_to` accepts only `owner`.
- **D-24:** While paused, the agent sends nothing in that conversation (except D-17), and **every new customer message alerts the owner right away**, so nothing goes unanswered because someone forgot to hand back.
- **D-25:** A **fresh reply the owner types pauses the agent** in that conversation, with a banner: "You're handling this. Hand back to the assistant when done." **Approving, or editing and sending, a held draft does not pause it.** Assist mode and the quiet period replace this in 2.1.
- **D-26:** One **Take over** / **Hand back** control per conversation, not per message.

### Drafts (GATE-04)
- **D-27:** The owner reviews a draft in the dashboard and approves, edits and sends, or discards it (GATE-04 confirmed as written; approving by text reply is 2.1). Sending goes through `SendOwnerReply`.
- **D-28:** A conversation has **at most one live draft**. A new customer message makes the agent write a fresh draft covering the whole thread, and the old draft is marked replaced and kept. Sending a replaced draft is refused with "The customer wrote again, here's the new draft", and the owner's edit is kept so they can paste it.

### Phone-friendly conversation view
- **D-29:** The alert link opens the **existing Filament conversation view, made phone-friendly**, not a separate page. **Acceptance:** at phone width the held reason, the draft and Send are visible without scrolling; the thread opens at the latest message; customer details collapse. The Phase 1 UAT notes on this screen are fixed here (owner and customer messages look alike; the reply box has no inner padding).
- **D-30:** UAT includes opening an alert link **on a real phone over Wi-Fi**, not only in a narrowed desktop browser.
- **D-31:** Login keeps Filament's "remember me" ticked by default; an expired session returns to the thread after sign-in. Sign out everywhere waits for 2.1.
- **D-32:** The desktop home screen shows drafts waiting (oldest first, with the held reason), paused conversations where the customer is waiting, new leads, and today's conversations.

### Intake answers (AGNT-03)
- **D-33:** New `intake_answers` table, one row per answer: conversation, service slug, question key, the option matched (or null), the customer's raw words, the source message, and the **`icm_version` id** in force when it was asked. Each answer adds a timeline entry and shows on the card under that conversation. AGNT-03 confirmed as written. — **Reversibility:** one-way — a new table with foreign keys to conversations, messages and icm_versions; Phase 3 booking reads it.
- **D-34:** A correction ("actually it's 8 years") **adds a new row**. The latest per conversation, service and question is current, and the history is kept.

### Agent file (AGNT-01, AGNT-02, AGNT-04)
- **D-35:** **One front-desk agent per channel.** The validator rejects a second agent file on the same channel. `escalate_to` is `owner` in Phase 2.
- **D-36:** `loads` names profile, services, FAQ, hours, voice and sending rules. The customer card rendered fresh from the database is always added. Files are loaded whole from `ActiveConfig`.
- **D-37:** The reply model sees the whole thread and the customer card, more than Jev sees. **Before a real client goes live**, every provider an agent file names gets the same no-retained-copy (ZDR) check as Jev: read the DPA, test with OpenRouter ZDR enforcement on, and pin the provider.
- **D-38:** **Burst wait:** the agent waits about 20 seconds after the last inbound message and answers them all together (except D-17). The AI disclosure goes at the start of the first reply rather than as its own text.

### STOP and opt-out
- **D-39:** **Twilio handles STOP, START and HELP** and sends its own confirmation, so the app never sends a second one. TXT-01 uses a **Messaging Service with Advanced Opt-Out on** (`OptOutType` only reaches the webhook that way). Code also matches the standard opt-out words as a backup.
- **D-40:** An opt-out is **recorded on the contact** and blocks **every** outbound text to that number: agent, owner replies, holding and handoff replies, and the life-safety line (Twilio refuses those with error 21610 anyway). The reply box shows "Opted out by texting STOP on <date>". If an opted-out customer texts again, the owner is alerted with the message so they can call. START or UNSTOP clears the opt-out. The fake channel simulates all of this. — **Reversibility:** one-way — a contacts column, and a send block every outbound path depends on.

### Phase 1 carry-overs
- **D-41 (WR-13):** The agent texts **only a number that has itself texted the business** in that conversation. A form lead goes to the owner and `open-leads.md`, and the agent never texts it first. Record `meta.matched_by` on form messages.
- **D-42 (WR-02):** Add **`dispatched_at` on `messages`**, set when `ProcessInbound` is queued. A retried webhook whose message is recorded but has no `dispatched_at` queues it then. A **scheduled sweep** every few minutes queues any inbound message older than two minutes with no `dispatched_at` (a form post never retries, and a provider can stop retrying). The retry path and the sweep share **one dispatch step that is unique per message**, so a message is never processed twice. — **Reversibility:** one-way — a messages migration; the test `records a retried webhook once and queues nothing the second time` changes meaning.
- **D-43 (empty name):** A name the customer gives the agent from their own number fills an empty name. A web form's name fills it only on a contact the form itself created. For an existing contact matched by phone, the form's name goes on the timeline as a note, since anyone can type any number into the form (WR-13). Never overwrite a stored name.
- **D-44 (IN-03):** Left as is. Gaps in version numbers are harmless.

### Planning-time decisions (Ian, 2026-10-11, answering the open questions in 02-RESEARCH.md)
- **D-45 (OQ-1, threshold):** The **strict reading** of D-03's 0.85. Intent (Jev choice question) sends only at `confidence >= 0.85`; a yes/no gate check (price, promise, discount, complaint) counts as a clear "no" only at `P(yes) <= 0.075`. Anything else holds. The values live in `config/gate.php` and are stored on every verdict, so they can be loosened later from the approval log.
- **D-46 (OQ-1, life-safety):** The life-safety yes/no is a **trigger, not a gate check**, and runs the other way (D-15: when unsure, send). It has its own value in `config/gate.php`, starting at 0.075: if `P(yes)` is above it, the safety line sends (it does not just hold the draft). Its score is stored on the verdict like the others.
- **D-47 (OQ-4, asking for a person):** Detected by any of three signals: (1) code keywords limited to **unambiguous phrases** such as "real person", "talk to someone", "speak to a person" (no bare "human" or "call me": "call me when you're on the way" is scheduling); (2) a `wants_person` field in the agent's structured output; (3) a **sixth yes/no, `asks_for_person`**, in `rules/triage.md` (this extends D-04's five). `asks_for_person` triggers the handoff on a **clear yes** only: its own value in `config/gate.php`, starting at `P(yes) >= 0.85`. In the unsure band it holds the draft like any other unsure check, so the owner decides and the conversation is **not** paused. Its score is stored on the verdict.
- **D-48 (folders):** Two new namespaces are approved: `app/Ai` (laravel/ai agents, its own convention) and **`app/SendRule`** for the send-or-draft rule (code checks, classifier, verdict). Not `app/Gate`: Gate is Laravel's authorization facade and a Laravel reader would expect policies there. Both rows were added to the Map table in CLAUDE.md at planning time.
- **D-49 (OQ-2):** After the life-safety line the conversation enters the paused state (D-23) and the owner is alerted at once.
- **D-50 (OQ-3, disclosure and sign-off):** The AI disclosure goes at the start of the **first automated text in each conversation** (not once per contact), including a holding or handoff reply when one goes first; when the first text is the life-safety line, the safety sentence comes first and the disclosure follows (D-17). `sign_off` goes on every agent text except the life-safety line.
- **D-51 (OQ-5, `{next_open}`):** Says "today" only if **at least an hour remains before closing**, computed in the business's time zone; inside that last hour it uses the next opening ("at 8 am tomorrow", "at 9 am Saturday", closures skipped), so the promise stays true (D-13). The hour is a config value.
- **D-52 (OQ-6, reply cap):** Agent replies are capped per conversation, starting at 6 per hour, plus a daily cap; at the cap the conversation pauses and the owner is alerted (stops bot ping-pong). The cap **never blocks the life-safety line**; only STOP does (D-17, D-40).
- **D-53 (OQ-7):** A user's own phone is not exempt from the agent in Phase 2. The test caveat (in the sprint file) must name the case where the owner replies to an alert text and the agent answers them as a customer; the caveat carries into Phase 2.1 (added to its scope in ROADMAP.md).
- **D-54 (OQ-8):** An approved draft **becomes the sent message**: the same row, with the original text kept when the owner edits it, so the labeled data is one record per decision.
- **D-55 (walk-through log):** Create `docs/sprints/sprint-1.md`; Phase 2's walk-through log lines go there (CLAUDE.md rule).

### Claude's Discretion
- Provider and model for the example folder's front-desk agent, per DEC-07, with a fallback on a **different provider**; tests use laravel/ai fakes.
- The exact burst-wait length (about 20 s), the Jev timeout, the alert's cut-off lengths, and the code-check word lists (starting points, recorded where the owner can see them).
- The schema of `owner_alert`, `set_replies` and `triage` front matter, the column names for the gate's verdict record, and the export format for the labeled data.
- How the paused state is stored (one column or enum on conversations) and how service matching picks the `business/services/*.md` file.
- The CI provider and workflow (no `.github/workflows` exists yet).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Owner direction for this phase
- `docs/phase-2-owner-input.md`: Ian's answers and the recommendations this discussion settled. Where it and this CONTEXT.md differ, this file wins (for example the life-safety list in D-15 and the 2.1 split).
- `.planning/phases/02-text-front-desk/02-DISCUSSION-LOG.md`: the questions and answers behind each decision (human reference).

### Plan of record
- `.planning/ROADMAP.md` § Phase 2: goal, success criteria, planning notes. Phase 2.1 still has to be inserted.
- `.planning/REQUIREMENTS.md` § Text Front Desk (Phase 2): AGNT-01 to AGNT-04, GATE-01 to GATE-04, TXT-01, INFRA-01.
- `.planning/PROJECT.md` § Constraints and Key Decisions: DEC-01 to DEC-14, especially DEC-05 (fake channel), DEC-06 (Postgres in CI), DEC-07 (laravel/ai, model per agent file), DEC-10 (ActiveConfig only), DEC-14 (no lock-in).

### Product spec
- `docs/SPEC.md` § Principles 5 and 6 (AI disclosure, sending authority per action), § "File types (the front matter contract)" (the `agent` and `sending_rules` rows), § "The bridge" (records and the fresh card for agents), § "Modules and build order" (Sprint 1).

### Phase 1 carry-overs
- `.planning/phases/01-foundation/01-REVIEW.md`: WR-02 and WR-13 (problem statements and suggested fixes).
- `.planning/phases/01-foundation/01-REVIEW-DISPOSITION.md`: disposition table; update WR-02 and WR-13 when fixed.
- `.planning/phases/01-foundation/01-CONTEXT.md`: D-06 (compiled version holds bodies), D-16 (one open conversation), D-17 (reply box), D-24 (panel access, which 2.1 changes).
- `.planning/phases/01-foundation/01-UAT.md`: the conversation-view notes fixed by D-29.

### External
- https://docs.typesafe.ai: Jev API (confirm the request and response shape, the certainty field, and model pinning when building the driver).
- Twilio Messaging Services, Advanced Opt-Out, and webhook signature validation (TXT-01, D-39).
- The classifier method note `Titus HQ/AI Practice/METHOD-using-a-classifier-in-a-build.md` lives outside the repo. The rules it contributes (code first, questions in a file, fail toward hold, owner decisions as labels) are already captured in D-01 to D-11.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Jobs/ProcessInbound.php`: empty `handle()`, already queued from every inbound path. The agent runs here.
- `app/Actions/SendOwnerReply.php`: the one outbound path (stored queued, set sent when the channel returns an id, failure kept with error). Approved drafts, agent sends and fixed replies should all go through it or a sibling that shares its rules. The opt-out block (D-40) belongs on this path.
- `app/Actions/RecordInbound.php`: the single inbound entry point. WR-02 (`dispatched_at`) and WR-13 (`matched_by`) change it.
- `app/Channels/TextChannel.php` + `FakeTextChannel.php` + the `TwilioTextChannel.php` stub (throws "built in Phase 2"). TXT-01 fills the stub.
- `app/Icm/ActiveConfig.php`: accessors `business()`, `hours()`, `voice()`, `sendingRules()`, `services()`, `service()`, `file()`. New accessors are needed for agents, `set_replies` and `triage`.
- `app/Icm/Validator.php` + `PlainWords.php`: add the `agent`, `set_replies` and `triage` rule sets. `agent` currently goes through the `LaterSprint` skip path (`IcmFileKind::LaterSprint`).
- Enums: `MessageStatus` already has `Draft`; `MessageAuthor` has `Agent` and `Owner`; `ActivityType` will need new cases (draft held, intake answer, takeover, opt-out, alert sent).
- `app/Livewire/ConversationThread.php` and the Filament `ViewConversation` page: the screen D-29 makes phone-friendly.

### Established Patterns
- Config is read only through `ActiveConfig`, and nothing reads the folder except the sync (Boost rule in `.ai/rules`). Money is integer cents (also a rule).
- Pest helpers are global across the suite, so give each helper a file-specific name (01-06 lesson).
- Tests point `ICM_PATH` at a temporary copy of the example folder.
- A rejected request answers JSON 422 via `failedValidation()` (01-03, 01-04).

### Integration Points
- **Scheduler is new** (`routes/console.php` has only `inspire`): the WR-02 sweep and the "alert at next opening" delayed alerts introduce it. This is a Laravel concept to walk through.
- **CI is new**: no `.github/workflows`. INFRA-01 adds Postgres CI.
- `users` table gets `phone`; Filament panel gets `->profile()` and remember-me (`app/Providers/Filament/AdminPanelProvider.php`).
- New tables and columns: `intake_answers`, `messages.dispatched_at`, gate verdict fields on messages, a conversation paused state, a contact opt-out timestamp.
- `examples/example-hvac`: add `agents/front-desk.md`, `rules/replies.md`, `rules/triage.md`, a structured `owner_alert`; update `agents/README.md` (it still says agents arrive "in Sprint 1").
- The walk-through log: CLAUDE.md asks for one line per new Laravel concept in the current sprint file in `docs/sprints/`. Only `sprint-0.md` exists, so Phase 2 needs a sprint file (for example `docs/sprints/sprint-1.md`) or a decision to keep logging in sprint-0's log.
- The SPEC says a cartographer map starts in this sprint (ROADMAP planning note).

</code_context>

<specifics>
## Specific Ideas

- The scene that anchors the phase: an HVAC owner at 7:15 am in the truck; three texts overnight; the agent answered two and held one because the customer asked "how much for a new unit?"
- Two taps from alert to sent: tap the link, tap Send (or edit, then Send).
- The holding reply example: "Mike handles pricing himself. I've sent him your question and he'll get back to you when we open at 8 tomorrow."
- The held reason reads like "Held: asks about price".
- Life-safety: a false alarm costs nothing, a missed one can cost a life.
- Market references (owner-input doc): Jobber Receptionist (live watch and take over), My AI Front Desk (text and email with summary, backup manager), DealerAI (explicit Take Over and Pass Back), Chatfuel (pause on agent reply, resume on timeout), Signpost (owners live on their phones; handing control to the technology is their biggest fear).

</specifics>

<deferred>
## Deferred Ideas

- **Phase 2.1, the owner loop** (to insert in ROADMAP.md): staff users and the approve switch, approving by text reply with a code, email alerts and the "handled" follow-up, re-alert and backup recipient, assist mode and quiet period, the after-hours on-call switch, `escalate_to` naming a staff user, sign out everywhere, the LLM fallback classifier driver. A reply code for a replaced draft is refused with "The customer wrote again".
- **Fixed-price auto-send:** a service with `price_policy: fixed` may send the exact `price_cents` from its file on its own, checked by code. Trigger: the approval log shows owners approving fixed-price drafts unchanged.
- **Texting web-form leads first (speed to lead):** form leads who tick an SMS consent box get one confirmation text ("Reply YES to get texts about your request"), and the agent texts only after YES, which proves both consent and that the number belongs to whoever filled in the form. Trigger: before the first client whose leads come in by web form.
- **Email reply-to-approve:** needs inbound email parsing; not planned.
- **Before a real client (not a phase, a gate):** ZDR checks for Jev (TypeSafe DPA, sales@typesafe.ai, test with OpenRouter ZDR enforcement on) and for every provider an agent file names (D-37).

</deferred>

---

*Phase: 02-text-front-desk*
*Context gathered: 2026-10-11*
