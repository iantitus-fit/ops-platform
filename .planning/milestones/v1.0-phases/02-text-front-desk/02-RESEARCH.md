# Phase 2: Text Front Desk - Research

**Researched:** 2026-10-11
**Domain:** Laravel 13 + laravel/ai 1.2.0 SMS agent with a code-enforced send-or-draft gate, a Jev (TypeSafe) classifier, Twilio text driver, Filament 5 back office, Postgres CI
**Confidence:** HIGH on Jev, laravel/ai, Twilio signature, Postgres findings (each read or run this session); MEDIUM on Twilio send-API error body and on the design recommendations marked [ASSUMED]

## Summary

The phase is mostly assembly of parts that already exist in the repo or in installed packages, and four findings change how it should be planned. (1) `laravel/ai` 1.2.0, already installed, ships a first-party Jev/TypeSafe classifier (`Classification`, `Choice`, `Boolean`, `TypeSafeProvider`, `FakeClassificationGateway`) and a config entry for it; D-06's plain-HTTP driver is still the right call (the SDK feature is flagged "experimental" and its OpenRouter gateway posts to a different endpoint than Ian's known-good call), but the SDK confirms the wire shape and gives a no-new-package fallback. (2) I ran the existing suite against a throwaway Postgres 16 container: **263 of 281 tests pass; 17 fail and 1 errors**, all from two test-side assumptions (hard-coded `v1`/`v2` version labels, and a 360-character name in a `varchar(255)` column). INFRA-01 is therefore a fix-the-tests task plus a workflow file, not a surprise. (3) Laravel 13.35 has a built-in `#[DebounceFor]` job attribute that is exactly the D-38 burst wait, but it is cache-based and "fails open", so it cannot replace the database claim D-42 asks for; use both. (4) laravel/ai failover only fires on connection errors, 429, 402 and the overloaded status codes; a plain 500, 401 or malformed structured output does **not** fall through, so AGNT-04 needs a small application loop around the SDK, which I verified with the SDK's own fakes.

Jev is settled enough to build against. The `state` field accepts a string, an object or an array, so D-04's two named fields (`customer_message`, `draft_reply`) go in as an object. A Choice answer carries `confidence` (computed from the probability spread); a yes/no (`noul`) answer has no confidence at all, only a probability of yes. D-03's 0.85 therefore needs one stated definition per question type (table below). On OpenRouter's `systemone` route the documented pin is `typesafe/jev-1.13`; `jev-1.13.0` is documented only for TypeSafe's own API.

The Twilio driver needs **no new package**: sending is one form POST through Laravel's HTTP client and the `X-Twilio-Signature` check is a 6-line HMAC-SHA1 compare that I reproduced against Twilio's published test vector. The cartographer map is Ian's own tool in `~/Projects/cartographer`; the smallest version for this phase is a `map/` folder with a catalog, about five object cards and one process card, and it needs Ian's rulings on an inventory table before any card is written.

**Primary recommendation:** Build the pipeline as `RecordInbound -> claim (dispatched_at) -> debounced ProcessInbound -> FrontDeskAgent (laravel/ai structured output, own fallback loop) -> code checks (veto) -> JevClassifier (fail closed) -> send | draft`, keep every decision in code with the verdict stored on the message, add no new Composer package, and make Wave 0 the Postgres test fixes plus a test-suite safety net (stray AI and HTTP calls fail the test).

## User Constraints (from CONTEXT.md)

<user_constraints>
### Locked Decisions

#### The gate: when a reply sends on its own (GATE-01)
- **D-01:** An agent reply sends on its own only when **three checks all pass**: (1) the agent's own label for the reply, from structured output, is in `send_on_own`; (2) the code checks find nothing (dollar amounts, a number next to a price word, discount or promise phrases, any `never_say` phrase); (3) Jev labels it sendable with certainty at or above the send threshold. Any hold, failure or uncertainty makes it a draft. The replying model never sends on its own say-so.
- **D-02:** The code checks are a **hard veto**. Jev is not injection-hardened, so Jev can only hold, never force a send.
- **D-03:** **Fail closed.** If the Jev call fails, times out (short timeout) or returns certainty below the threshold, the reply is held as a draft. The send threshold is decided in code on Jev's certainty, starting at **0.85**.
- **D-04:** **One Jev call per reply.** The state holds the customer's message and the draft reply as two named fields. Questions: one choice question for intent (the list always includes `other`) and yes/no questions for price, promise, discount, complaint and life-safety.
- **D-05:** Jev receives **only message text**, never the customer's name or phone. Pin the model version **`jev-1.13.0`**, never `jev-latest`. Confirm the API shape against docs.typesafe.ai when building the driver (titus-site already calls Jev through OpenRouter's Decisions endpoint over plain HTTP).
- **D-06:** Triage sits behind a `Classifier` interface with a Jev HTTP driver and a fake for tests. The LLM fallback driver is Phase 2.1 or later (needed if TypeSafe can't offer ZDR). Jev is a classifier, not an agent, so calling it over plain HTTP instead of laravel/ai does not conflict with DEC-07.
- **D-07:** Every check's verdict is **stored on the message**: the agent's label, which code rule matched, Jev's answers and certainty. The draft shows the owner a plain-words reason ("Held: asks about price"). This record, together with the owner's approve, edit and discard actions, is the labeled data for tuning later and must be exportable. — **Reversibility:** costly — the stored shape becomes the calibration dataset; changing it later loses or splits history.

#### Action vocabulary and the hard floor
- **D-08:** The action keys are fixed. `send_on_own` accepts `intake_question`, `scheduling`, `faq_answer`. `draft_for_owner` accepts `price`, `promise`, `discount`, `complaint`, `other`. **price, promise, discount and complaint can never go in `send_on_own`**: the validator rejects it with a plain-words error. An unknown key is an error. An action in neither list is drafted.
- **D-09:** `owner_alert` in `rules/sending.md` becomes **structured** (channels, trigger events, after-hours behaviour) and is validated. The example folder changes with it. It holds *what* and *when*, never a person's phone number.

#### Fixed replies and triage files
- **D-10:** New file `rules/replies.md` (type `set_replies`) holds the owner-written **holding reply** and **handoff reply** and the life-safety placeholders, using known placeholders such as `{owner_name}`, `{next_open}`, `{utility_phone}`. The validator rejects a missing reply or an unknown placeholder.
- **D-11:** New file `rules/triage.md` (type `triage`) holds the Jev intent list and yes/no questions, so the questions live in the folder (method rule 7).
- **D-12:** The sync runs **the same code checks** on `rules/replies.md` (dollar amounts, discount or promise phrases, `never_say`) and rejects a reply that fails them. Fixed replies skip the classifier when they send, because code wrote them.
- **D-13:** Times in the holding and handoff replies are **computed in code from `business/hours.md`** (next opening time, closures included), so the promise is always true. Example: "Mike handles pricing himself. I've sent him your question and he'll get back to you when we open at 8 tomorrow."
- **D-14:** The customer gets **at most one holding reply** while a draft is waiting in a conversation.

#### Life-safety line
- **D-15:** Triggers: gas smell; fire or smoke; a carbon monoxide alarm; sparking; water reaching outlets, the electrical panel or plugged-in appliances. Plain flooding (a burst pipe, water on the floor) is **not** life-safety. It's an urgent service request for the 2.1 on-call switch. Code keywords are checked first and Jev's life-safety question second; **when unsure, send.**
- **D-16:** The **core sentence is fixed in code**: get to safety and call 911 from outside (for water: stay out of the water, away from the electrical, and call 911). It never gives repair or shut-off steps. The folder only fills placeholders such as `{utility_phone}`, so no folder edit can weaken it.
- **D-17:** The life-safety line **sends first**, before any other reply, **skips the burst wait**, and sends even in a paused or taken-over conversation (the only thing that does). When it's the first message a customer gets, the safety sentence comes first and the AI disclosure follows in the same text.

#### Owner alert (Phase 2 part)
- **D-18:** Phase 2 alerts by **text only** (email is 2.1). The recipient's phone lives on the **user record** (new `phone` column on `users`), which the owner sets on **Filament's profile page**. — **Reversibility:** one-way — a users migration; 2.1 builds staff users on the same column.
- **D-19:** If `owner_alert` asks for a text and no user has a phone on file, the Business Folder page shows a plain-words warning and the dashboard flags held drafts. An alert never fails silently.
- **D-20:** **Timing:** urgent events (complaint, a request for a person, life-safety, any new message in a paused conversation) alert right away. Price, promise and discount drafts made after hours alert at the next opening time in `hours.md`. Set per business in `owner_alert`.
- **D-21:** The alert text is **built by code from a template**, with no model call: the customer's name (or number), why it was held, their last message quoted and cut short, the draft cut short, and the link. Links inside the quoted customer message are replaced with `[link]`. The dashboard link always goes **last, on its own line**, so the only tappable link is ours.
- **D-22:** Alert links point to the **conversation**, not to a draft, so an older alert still opens the newest draft. A replaced draft sends a new alert only if urgency rises (complaint, a request for a person, life-safety).

#### Asking for a person, takeover and the paused state (GATE-02, GATE-03)
- **D-23:** A customer who asks for a person gets the **handoff reply** (from `rules/replies.md`, with the time computed from hours). The conversation enters the **same paused state as Take over** (one mechanism, one hand-back), and `escalate_to` is alerted right away. In Phase 2, `escalate_to` accepts only `owner`.
- **D-24:** While paused, the agent sends nothing in that conversation (except D-17), and **every new customer message alerts the owner right away**, so nothing goes unanswered because someone forgot to hand back.
- **D-25:** A **fresh reply the owner types pauses the agent** in that conversation, with a banner: "You're handling this. Hand back to the assistant when done." **Approving, or editing and sending, a held draft does not pause it.** Assist mode and the quiet period replace this in 2.1.
- **D-26:** One **Take over** / **Hand back** control per conversation, not per message.

#### Drafts (GATE-04)
- **D-27:** The owner reviews a draft in the dashboard and approves, edits and sends, or discards it (GATE-04 confirmed as written; approving by text reply is 2.1). Sending goes through `SendOwnerReply`.
- **D-28:** A conversation has **at most one live draft**. A new customer message makes the agent write a fresh draft covering the whole thread, and the old draft is marked replaced and kept. Sending a replaced draft is refused with "The customer wrote again, here's the new draft", and the owner's edit is kept so they can paste it.

#### Phone-friendly conversation view
- **D-29:** The alert link opens the **existing Filament conversation view, made phone-friendly**, not a separate page. **Acceptance:** at phone width the held reason, the draft and Send are visible without scrolling; the thread opens at the latest message; customer details collapse. The Phase 1 UAT notes on this screen are fixed here (owner and customer messages look alike; the reply box has no inner padding).
- **D-30:** UAT includes opening an alert link **on a real phone over Wi-Fi**, not only in a narrowed desktop browser.
- **D-31:** Login keeps Filament's "remember me" ticked by default; an expired session returns to the thread after sign-in. Sign out everywhere waits for 2.1.
- **D-32:** The desktop home screen shows drafts waiting (oldest first, with the held reason), paused conversations where the customer is waiting, new leads, and today's conversations.

#### Intake answers (AGNT-03)
- **D-33:** New `intake_answers` table, one row per answer: conversation, service slug, question key, the option matched (or null), the customer's raw words, the source message, and the **`icm_version` id** in force when it was asked. Each answer adds a timeline entry and shows on the card under that conversation. AGNT-03 confirmed as written. — **Reversibility:** one-way — a new table with foreign keys to conversations, messages and icm_versions; Phase 3 booking reads it.
- **D-34:** A correction ("actually it's 8 years") **adds a new row**. The latest per conversation, service and question is current, and the history is kept.

#### Agent file (AGNT-01, AGNT-02, AGNT-04)
- **D-35:** **One front-desk agent per channel.** The validator rejects a second agent file on the same channel. `escalate_to` is `owner` in Phase 2.
- **D-36:** `loads` names profile, services, FAQ, hours, voice and sending rules. The customer card rendered fresh from the database is always added. Files are loaded whole from `ActiveConfig`.
- **D-37:** The reply model sees the whole thread and the customer card, more than Jev sees. **Before a real client goes live**, every provider an agent file names gets the same no-retained-copy (ZDR) check as Jev: read the DPA, test with OpenRouter ZDR enforcement on, and pin the provider.
- **D-38:** **Burst wait:** the agent waits about 20 seconds after the last inbound message and answers them all together (except D-17). The AI disclosure goes at the start of the first reply rather than as its own text.

#### STOP and opt-out
- **D-39:** **Twilio handles STOP, START and HELP** and sends its own confirmation, so the app never sends a second one. TXT-01 uses a **Messaging Service with Advanced Opt-Out on** (`OptOutType` only reaches the webhook that way). Code also matches the standard opt-out words as a backup.
- **D-40:** An opt-out is **recorded on the contact** and blocks **every** outbound text to that number: agent, owner replies, holding and handoff replies, and the life-safety line (Twilio refuses those with error 21610 anyway). The reply box shows "Opted out by texting STOP on <date>". If an opted-out customer texts again, the owner is alerted with the message so they can call. START or UNSTOP clears the opt-out. The fake channel simulates all of this. — **Reversibility:** one-way — a contacts column, and a send block every outbound path depends on.

#### Phase 1 carry-overs
- **D-41 (WR-13):** The agent texts **only a number that has itself texted the business** in that conversation. A form lead goes to the owner and `open-leads.md`, and the agent never texts it first. Record `meta.matched_by` on form messages.
- **D-42 (WR-02):** Add **`dispatched_at` on `messages`**, set when `ProcessInbound` is queued. A retried webhook whose message is recorded but has no `dispatched_at` queues it then. A **scheduled sweep** every few minutes queues any inbound message older than two minutes with no `dispatched_at` (a form post never retries, and a provider can stop retrying). The retry path and the sweep share **one dispatch step that is unique per message**, so a message is never processed twice. — **Reversibility:** one-way — a messages migration; the test `records a retried webhook once and queues nothing the second time` changes meaning.
- **D-43 (empty name):** A name the customer gives the agent from their own number fills an empty name. A web form's name fills it only on a contact the form itself created. For an existing contact matched by phone, the form's name goes on the timeline as a note, since anyone can type any number into the form (WR-13). Never overwrite a stored name.
- **D-44 (IN-03):** Left as is. Gaps in version numbers are harmless.

### Claude's Discretion
- Provider and model for the example folder's front-desk agent, per DEC-07, with a fallback on a **different provider**; tests use laravel/ai fakes.
- The exact burst-wait length (about 20 s), the Jev timeout, the alert's cut-off lengths, and the code-check word lists (starting points, recorded where the owner can see them).
- The schema of `owner_alert`, `set_replies` and `triage` front matter, the column names for the gate's verdict record, and the export format for the labeled data.
- How the paused state is stored (one column or enum on conversations) and how service matching picks the `business/services/*.md` file.
- The CI provider and workflow (no `.github/workflows` exists yet).

### Deferred Ideas (OUT OF SCOPE)
- **Phase 2.1, the owner loop** (to insert in ROADMAP.md): staff users and the approve switch, approving by text reply with a code, email alerts and the "handled" follow-up, re-alert and backup recipient, assist mode and quiet period, the after-hours on-call switch, `escalate_to` naming a staff user, sign out everywhere, the LLM fallback classifier driver. A reply code for a replaced draft is refused with "The customer wrote again".
- **Fixed-price auto-send:** a service with `price_policy: fixed` may send the exact `price_cents` from its file on its own, checked by code. Trigger: the approval log shows owners approving fixed-price drafts unchanged.
- **Texting web-form leads first (speed to lead):** form leads who tick an SMS consent box get one confirmation text ("Reply YES to get texts about your request"), and the agent texts only after YES, which proves both consent and that the number belongs to whoever filled in the form. Trigger: before the first client whose leads come in by web form.
- **Email reply-to-approve:** needs inbound email parsing; not planned.
- **Before a real client (not a phase, a gate):** ZDR checks for Jev (TypeSafe DPA, sales@typesafe.ai, test with OpenRouter ZDR enforcement on) and for every provider an agent file names (D-37).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| AGNT-01 | Agent files validated and compiled | `Loader::LATER_SPRINT` currently lists `agent` and skips it; move it to `TYPED`, add rules in `Validator::rulesFor`, and fix `FolderSync::compile` (its default branch stores one path per type, which would let a second agent overwrite the first). Provider check via `Ai::textProvider($name)` verified to throw for unknown and non-text providers. See "Agent file" and "Pitfalls 3, 4". |
| AGNT-02 | `ProcessInbound` runs the agent with routed files plus the fresh card | `CustomerCard::render()` already renders from the database; `ActiveConfig::file()` returns whole files. Structured-output agent via `agent(...)` or a `FrontDeskAgent` class. See "Pattern 1". |
| AGNT-03 | Intake questions asked, answers stored and visible | `intake_answers` table (D-33); the agent returns answers as structured output and code validates `question_key` and `option` against the service file. Card gains an "Intake answers" section. |
| AGNT-04 | Provider/model per agent file with a fallback list | laravel/ai accepts `['provider' => 'model', ...]` per prompt. Failover covers only connection, 429, 402 and overloaded statuses; add an application loop (verified). See "laravel/ai". |
| GATE-01 | Send-or-draft gate in code | Three-check gate (D-01..D-04). Jev contract, certainty definition and fail-closed driver in "Jev". Existing money-guard and arch tests constrain column names (Pitfall 2). |
| GATE-02 | AI disclosure, `never_say`, `sign_off`, ask for a person | Applied by code after the model returns, never by the model. Open questions OQ-2, OQ-3 on sign-off scope and post-life-safety behaviour. |
| GATE-03 | Takeover and hand back | One paused state on conversations; every send path checks it (and the opt-out) in one place. |
| GATE-04 | Review, edit-and-send, discard drafts | Draft is a `messages` row with `status = draft`; partial unique index gives "one live draft" in SQLite and Postgres (same pattern as `conversations_one_open_per_contact`). |
| TXT-01 | `TwilioTextChannel` behind `TextChannel`, signature verified, unique provider id | No package needed. Signature algorithm reproduced against Twilio's vector; Advanced Opt-Out / `OptOutType`; status callback; 21610. See "Twilio". |
| INFRA-01 | Full Pest suite on Postgres in CI and on SQLite locally | Probe run found 17 failing tests and 1 error, all test-side; workflow YAML below. |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- Files hold how the business runs; the database holds what happened. Code reads business configuration only through `App\Icm\ActiveConfig` (also `.ai/rules/app.md`, enforced by arch tests). Money is integer cents (`.ai/rules/*`, enforced by a schema test).
- Tests first for anything touching money, sending or the sync. Run `php artisan test` before calling a step done; Pest only; do not delete tests or test files without approval.
- Sending authority (`rules/sending.md`) is enforced in code, not only in prompts.
- Ian does not write complex logic unaided: the first time a Laravel concept appears, explain it in plain sentences and add one line to the walk-through log at the end of the current sprint file in `docs/sprints/` (only `sprint-0.md` exists; Phase 2 needs a sprint file or an explicit decision to log in sprint-0's).
- Git under GSD: local commits, one per task, on the phase branch; never push, never merge to main, never rewrite history.
- No secrets in the repo or ICM folders; examples and test data are fictional.
- Boost rules: `vendor/bin/pint --dirty --format agent` after PHP edits; use `php artisan make:*` generators with `--no-interaction`; create factories and seeders with new models; `php artisan test --compact` with the narrowest filter; **do not change dependencies or create new base folders under the app without approval**; only create documentation files if asked.
- Project skills available and relevant: `ai-sdk-development`, `filament-development`, `laravel-best-practices`, `testing-best-practices`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Receive and verify a customer text (Twilio signature, `OptOutType`) | API / Backend (webhook controller + middleware) | — | The signature is the only authentication on a public, CSRF-exempt route (`bootstrap/app.php:15` exempts `webhooks/*`). |
| Record inbound, claim dispatch exactly once | Database / Storage (`messages.dispatched_at` compare-and-set) | API / Backend (`RecordInbound`, sweep) | A database claim is atomic on SQLite and Postgres; cache-based locks and Laravel's debounce are not durable. |
| Burst wait (about 20 s) | API / Backend (queue, `#[DebounceFor]`) | Database (claim) | Debounce is a cache optimisation; the database claim is what prevents double replies. |
| Draft the reply and label it | External model via laravel/ai | API / Backend | The model proposes; it never decides. Its output is untrusted input to the gate. |
| Send-or-draft decision | API / Backend (code checks, then Jev verdict) | — | D-01/D-02: code is a hard veto; Jev can only hold. |
| Jev classification | External API (TypeSafe via OpenRouter or direct) | API / Backend (driver, fail closed) | Message text only (D-05). |
| Fixed replies, life-safety line, AI disclosure, sign-off | API / Backend (code) | Business folder (placeholders only) | Core sentences live in code so no folder edit can weaken them (D-16). |
| Next-opening time | API / Backend (pure function over `ActiveConfig::hours()`) | — | Computed, never written by a model (D-13). |
| Paused state, opt-out block | Database (columns) checked in one outbound chokepoint | API / Backend | Every send path must pass one check (D-24, D-40). |
| Owner alert | API / Backend (code-built text via `TextChannel`) | Scheduler (delayed alerts) | No model call (D-21). |
| Draft review, take over, hand back | Frontend Server (Filament + Livewire) | API / Backend (actions) | Existing `ConversationThread` + `ViewConversation`. |
| Phone number on user, remember-me default | Frontend Server (Filament `EditProfile`, `Login` subclasses) | Database (`users.phone`) | Filament 5 panel customisation. |
| CI on Postgres | CI (GitHub Actions) | — | No `.github/workflows` yet; no git remote is configured, so Ian pushes. |

## Standard Stack

### Core (all already installed; **no new Composer or npm package is needed**)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | Queue, scheduler, `Http` client, `#[DebounceFor]` | Installed `[VERIFIED: composer show --direct]` |
| laravel/ai | 1.2.0 | Structured-output agent, provider list, fakes, optional Classification | Installed; DEC-07 `[VERIFIED: composer show --direct]` |
| filament/filament | 5.10.1 | Back office, `EditProfile`, `Login`, widgets | Installed `[VERIFIED: composer show --direct]` |
| pestphp/pest | 5.3.1 | Tests | Installed `[VERIFIED: composer show --direct]` |
| giggsey/libphonenumber-for-php-lite | 9.0.41 | E.164 (already used by `PhoneNumber`) | Installed `[VERIFIED: composer show --direct]` |

### Supporting (no install)

| Library | Purpose | When to Use |
|---------|---------|-------------|
| `Illuminate\Support\Facades\Http` | Twilio send API, Jev call | Both drivers; `Http::fake()` and `Http::preventStrayRequests()` in tests |
| `Illuminate\Support\Facades\Schedule` (in `routes/console.php`) | Sweep, delayed alerts | WR-02 sweep every 5 minutes; due alerts every minute |
| `Laravel\Ai\Classification` | Jev through the SDK | Only as a fallback implementation behind `Classifier` |
| GitHub Actions: `actions/checkout`, `shivammathur/setup-php`, `actions/cache` | CI | Latest releases read this session: checkout v7.0.1 (2026-07-20), setup-php 2.40.0 (2026-10-08), cache v6.1.0 (2026-06-26) `[VERIFIED: gh api releases/latest]` |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Plain `Http` Jev driver (D-06) | `Classification::of(...)->classify(provider: 'typesafe', model: 'jev-1.13.0')` | Zero code for HTTP and has `FakeClassificationGateway`, but the docs label Classification "experimental", the SDK silently drops an answer with no recognised `type` (`AnswersQuestions.php:120` `default => null`), and its OpenRouter gateway posts to `/alpha/decisions` (`OpenRouterClassificationGateway.php:24`) rather than Ian's working `/api/v1/systemone`. Keep as a second `Classifier` implementation if the HTTP driver proves a burden. |
| Plain `Http` for Twilio | `twilio/sdk` 8.12.2 (MIT, github.com/twilio/twilio-php, 112M total downloads, released 2026-09-28) | The SDK has a tested `RequestValidator` and a send helper, but adds a Composer dependency (needs Ian's approval) for about 30 lines of code. Not recommended. |
| Laravel debounce + database claim | Fixed `->delay(20)` per message plus claim | Without debounce each inbound schedules a job at +20 s and the first one to run answers the whole burst early. Debounce re-arms the wait on every new message. |
| One `owner_alerts` table | Alert columns on `messages` | Columns are fewer files now, but Phase 2.1 adds email, re-alerts and a backup recipient. A table (one row per alert) absorbs those without another migration of `messages` `[ASSUMED]`. |

**Installation:** none. `composer.json` is unchanged by this phase.

**Version verification:** `composer show --direct` run 2026-10-11 gave the versions above. `twilio/sdk` 8.12.2 confirmed with `composer show -a twilio/sdk` and the Packagist JSON API (not installed).

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none to install) | — | — | — | — | — | Phase adds no package |
| twilio/sdk (considered, **not recommended**) | Packagist | 10+ yrs (7.x and 8.x lines) | 112,584,122 total, 3.76M monthly | github.com/twilio/twilio-php | not run | Tagged `[ASSUMED]`: the `package-legitimacy` seam supports only npm, pypi and crates (`gsd-tools package-legitimacy check` printed its usage error for `packagist` and `composer`). If Ian approves it anyway, the planner must put a `checkpoint:human-verify` before `composer require`. |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
                  Twilio / fake webhook / form post
                              |
          POST /webhooks/text/{fake|twilio}   POST /leads
                              |  (twilio: ValidateTwilioSignature, 403 on mismatch)
                              v
                     RecordInbound::handle  (one DB transaction)
          contact match + message row (+ meta.matched_by) + timeline
                              |
        OptOutType / exact STOP|START word? ---yes--> contacts.sms_opted_out_at set/cleared,
                              |no                    message kept, NO reply (Twilio already replied)
                              v
        life-safety keywords? ---yes--> SendLifeSafetyLine (no delay, bypasses pause) + urgent alert
                              |
                              v
        DispatchInbound: UPDATE messages SET dispatched_at=now() WHERE id=? AND dispatched_at IS NULL
                 (affected rows = 1 -> dispatch; 0 -> already dispatched, do nothing)
                              ^                                     |
   scheduler every 5 min: inbound, age > 2 min, dispatched_at null -+--> ProcessInbound (#[DebounceFor(20)], debounceId = conversation)
                                                                         |
                       web-form message? ---yes--> owner only (D-41), mark handled, STOP
                       no active version? ---yes--> log, mark handled, STOP
                       conversation paused? ---yes--> urgent alert to owner (D-24), STOP
                       contact opted out? ---yes--> alert owner with the message (D-40), STOP
                                                                         |
                                       claim all unhandled inbound in the conversation
                                                                         v
        FrontDeskAgent: thread + CustomerCard::render + whole routed files
          provider loop: primary -> fallback[] (any Throwable falls through)
          structured output: reply, label, service_slug, intake_answers[], wants_person, customer_name
                                                                         |
                       wants_person ---> handoff reply (fixed) + pause + urgent alert
                                                                         v
                       GATE: (1) label in send_on_own?   (2) CodeChecks veto   (3) Jev verdict
                          any miss / failure / uncertainty --> draft + holding reply (once) + alert
                          all pass --> disclosure (first agent text) + sign_off, then send
                                                                         v
             OutboundSender (opt-out block, pause check, lock, queued -> channel send -> sent)
                                                                         |
                        Twilio status callback --> messages.status (forward-only)
```

### Recommended Project Structure

New subfolders sit under existing `app/` namespaces; **ask Ian before adding a new top-level namespace** (CLAUDE.md: "don't create new base folders without approval"). If he declines new namespaces, nest everything under `app/Actions/FrontDesk/`.

```
app/
├── Actions/FrontDesk/     # DispatchInbound, RunFrontDesk, DecideSendOrDraft, SendFixedReply, SendLifeSafetyLine, TakeOver, HandBack, ResolveDraft
├── Channels/              # TwilioTextChannel (fill the stub), TwilioSignature, TwilioStatusCallback controller support
├── Icm/                   # agent / set_replies / triage rule sets in Validator; ActiveConfig accessors; BusinessClock (next opening)
├── Jobs/                  # ProcessInbound (debounced), SweepUndispatched, SendDueAlerts
├── Gate/                  # CodeChecks, Verdict, ReplyLabel enum, Classifier interface, JevClassifier, FakeClassifier   (new namespace: needs approval)
├── Ai/Agents/             # FrontDeskAgent + schema   (laravel/ai's own convention is app/Ai/Agents)  (new namespace: needs approval)
├── Filament/Pages/Auth/   # LoginRememberedByDefault, EditProfileWithPhone
├── Filament/Widgets/      # DraftsWaiting, PausedConversations, NewLeads, TodaysConversations
└── Http/Controllers/      # TwilioTextWebhookController, TwilioStatusCallbackController; Http/Middleware/ValidateTwilioSignature
```

### Data model (what the migrations must add) `[ASSUMED]` design; names are Claude's discretion

| Table | Change | Notes |
|-------|--------|-------|
| `messages` | `dispatched_at` (nullable timestamp), `handled_at` (nullable), `kind` (string: customer, agent_reply, holding, handoff, life_safety, owner, draft), `label` (agent's own label), `gate_outcome` (sent/held), `gate_reason` (code string), `gate` (json: rule matched, Jev answers, certainty, served model), `resolution` (approved/edited/discarded/replaced/null), `resolved_by`, `resolved_at`, `original_body` | **Do not name a column with `price`, `cost`, `amount`, `total`, `fee` or `balance`**: `MoneyIsIntegerCentsTest.php:31` fails any such column that is not an integer `*_cents` column (Pitfall 2). Put those words inside the `gate` JSON, never in a column name. |
| `messages` | partial unique index `(conversation_id) WHERE status = 'draft'` | "At most one live draft" (D-28); the same raw `DB::statement` pattern already works on both databases in `2026_10_11_131451_create_conversations_table.php`. |
| `conversations` | `agent_mode` (active/paused), `paused_reason` (taken_over, asked_for_person, owner_replied, life_safety), `paused_at`, `paused_by` | Claude's discretion; one enum column is enough. |
| `contacts` | `sms_opted_out_at` (nullable timestamp) | One-way door (D-40). |
| `users` | `phone` (nullable string, E.164) and add `phone` to `#[Fillable]` in `User.php` (currently `#[Fillable(['name', 'email', 'password'])]`) | Without Fillable the profile form silently drops it. |
| `intake_answers` | conversation_id, service_slug, question_key, option (nullable), raw_text, message_id, `icm_version_id` | D-33. Use a foreign key to `icm_versions` (Pitfall 5 on arch rule). |
| `owner_alerts` (recommended) | message_id/conversation_id, kind, due_at, sent_at, to_user_id, body, provider_message_id, failure | One row per alert; the minute scheduler sends rows that are due and whose draft is still live. |

**Backfill (a data migration, not a code edit):** set `messages.dispatched_at = created_at` and `handled_at = created_at` for every existing row. Without it the new sweep (inbound, older than 2 minutes, no `dispatched_at`) would queue the agent against the seeded Phase 1 conversations (Dana Whitfield and others) and text them.

### Pattern 1: Agent run with an application-level fallback loop `[VERIFIED: run in tinker this session]`

**What:** One prompt per provider entry; any `Throwable`, or a structured answer that fails validation, moves to the next entry.
**Why:** The SDK's own list only fails over on `FailoverableException` (connection, 429, 402, 502/503/504/520/522/524, plus 529 for Anthropic). I ran a plain `RuntimeException('HTTP 500')` from a fake and it propagated; an overloaded exception fell through to the second provider. A 500, a 401 or malformed JSON (`DecodesStructuredOutput` returns `[]`) would otherwise leave the customer unanswered.

```php
// Proposed (design is [ASSUMED]; SDK calls verified in tinker). Source: vendor/laravel/ai/src/Promptable.php prompt(), Provider::formatProviderAndModelList
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Messages\Message;
use function Laravel\Ai\agent;

foreach ($agentFile->providerChain() as $provider => $model) {       // [['anthropic','claude-sonnet-5-5'], ['openai','gpt-6.1-sol']]
    try {
        $response = agent(
            instructions: $instructions,                              // voice + sending rules + services + faq + hours + profile, whole files
            messages: $history,                                       // list<Message>, consecutive same-role turns collapsed by us
            schema: fn (JsonSchema $schema) => [
                'reply' => $schema->string()->required(),
                'label' => $schema->string()->enum(ReplyLabel::values())->required(),
            ],
        )->prompt($latestCustomerText, provider: [$provider => $model], timeout: 40);

        $output = AgentOutput::fromResponse($response);               // throws if label missing or not in the enum
        break;
    } catch (Throwable $e) {
        report($e);                                                    // keep going; record which entry failed
    }
}
```

Use a dedicated `FrontDeskAgent` class (not the anonymous agent) so tests can `FrontDeskAgent::fake(...)` and `assertPrompted` against one stable class name; `Ai::fakeAgent()` is keyed by agent class (`InteractsWithFakeAgents.php`). The SDK's default agent timeout is 60 s (`Promptable.php:548` `return 60;`); pass a shorter explicit `timeout` so a stalled primary does not hold the debounced job.

**Test the fallback with the fake itself:** a closure fake receives `($prompt, $attachments, $provider, $model)`; throw `ProviderOverloadedException::forProvider('anthropic')` for the primary and return an array for the second; the response reports who answered through `$response->meta->provider` / `->meta->model`; `assertPromptedTimes(2)` counts both attempts. Add one test where the primary throws a plain `RuntimeException` and a third where it returns an empty array (malformed output).

Build the history with `new Message('user'|'assistant', $text)` (`Messages/Message.php`); the SDK does **not** merge consecutive same-role turns (no merge logic in `Gateway/Anthropic/Concerns/MapsMessages.php`), so collapse a burst of three customer texts, and owner-plus-agent runs, into one turn each yourself `[ASSUMED: some providers reject consecutive same-role messages]`.

### Pattern 2: The gate as a pure decision object

`DecideSendOrDraft` takes the agent output, the thread, `ActiveConfig` rules and an injected `Classifier`, and returns a `Verdict` (outcome, reason code, plain-words reason, per-check detail). Order: (1) label in `send_on_own` (the hard floor in code also refuses `price`, `promise`, `discount`, `complaint` even if a hand-edited stored rule listed them); (2) `CodeChecks` over the **final text including disclosure and sign-off**; (3) Jev, called **only if (1) and (2) pass** (saves cost, and Jev can never rescue a veto); any exception, timeout, missing question, or low certainty returns held. Every branch writes the verdict to the message (D-07). Test it with a table of (label, text, classifier verdict) rows; no network and no database needed for the pure part.

### Pattern 3: One dispatch step that is unique per message (D-42) `[VERIFIED: framework source read]`

```php
// Proposed. Atomic on SQLite and Postgres; no cache or lock needed.
$claimed = Message::query()
    ->whereKey($messageId)
    ->whereNull('dispatched_at')
    ->update(['dispatched_at' => now()]);

if ($claimed === 1) {
    ProcessInbound::dispatch($messageId);   // carries the id; the job processes the whole conversation burst
}
```

Call it from `RecordInbound` after commit (replacing `ProcessInbound::dispatch($message->id)->afterCommit();` at `RecordInbound.php:63`), from the "already recorded" return path (WR-02 retry), and from the sweep. The sweep is `Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping()` in `routes/console.php` selecting `direction = in AND author = customer AND dispatched_at IS NULL AND created_at < now() - 2 minutes`; it calls the same action per row.

`ProcessInbound` gets `#[DebounceFor(20, maxWait: 90)]` and `public function debounceId(): string { return (string) $this->conversationId; }`. Laravel 13.35 semantics read from source: each new dispatch stores a fresh owner token in the cache (last writer wins) and delays the job by 20 s; when an older job runs and is no longer the owner it is deleted and a `JobDebounced` event fires; `maxWait` forces a run after that many seconds; **if the cache entry is gone the job runs anyway ("Fail-open")** (`CallQueuedHandler.php`, `commandShouldBeDebounced`); a debounced job cannot also implement `ShouldBeUnique` (`PendingDispatch::acquireDebounceLock` throws `LogicException`). So the job's first step must claim the work in the database: inside a transaction, `UPDATE messages SET handled_at = now() WHERE conversation_id = ? AND direction = 'in' AND author = 'customer' AND handled_at IS NULL`, read back the rows it claimed, and stop if none. That makes a duplicate run, a fail-open run and a sweep run all harmless.

### Pattern 4: Twilio signature (no SDK) `[VERIFIED: reproduced Twilio's published test vector]`

```php
// Proposed middleware body. Algorithm verified: URL . (POST params sorted by key, each key immediately followed by its value),
// HMAC-SHA1 keyed with the auth token, raw output, base64; compare with hash_equals.
$url = config('services.twilio.webhook_url');            // the exact URL typed into the Twilio console, not rebuilt from forwarded headers
$params = $request->request->all();                      // form body only; the query string is already inside $url
ksort($params);
$data = $url;
foreach ($params as $key => $value) {
    $data .= $key.(is_array($value) ? implode('', $value) : $value);   // twilio-php sorts and de-duplicates array values
}
$expected = base64_encode(hash_hmac('sha1', $data, config('services.twilio.auth_token'), true));
abort_unless(hash_equals($expected, (string) $request->header('X-Twilio-Signature')), 403);
```

Test vector (computed with `php -r` this session and equal to the vector in Twilio's SDK tests): URL `https://mycompany.com/myapp.php?foo=1&bar=2`, params `CallSid=CA1234567890ABCDE`, `Caller=+14158675309`, `Digits=1234`, `From=+14158675309`, `To=+18005551212`, token `12345` gives `RSOYDt4T1cUTdK1PDd93/VVr8B8=`. The official validator also accepts the URL with and without an explicit default port (443 or 80) `[CITED: github.com/twilio/twilio-php src/Twilio/Security/RequestValidator.php]`; mirror that. Use `$request->getContent()` for any raw body, never `file_get_contents('php://input')` (arch test bans disk functions in `App\Http` and `App\Channels`).

### Anti-Patterns to Avoid

- **Letting the model name the send decision:** the label is one input of three, never the decision (D-01).
- **Calling Jev before the code checks pass:** wasted cost, and it invites the thought that a Jev "sendable" can override a veto.
- **Rebuilding the signature URL from `$request->fullUrl()`:** behind a proxy or tunnel the scheme and port differ and every webhook is rejected; use a configured URL.
- **Sending the opt-out confirmation yourself:** Twilio already replied; a second message is a compliance defect (D-39).
- **Reading `config/gate.php` word lists from the business folder:** the lists are code (hard veto); only the owner-visible *display* of them is a feature.
- **Testing the debounce with `QUEUE_CONNECTION=sync`:** `SyncQueue` runs immediately and ignores the delay; use `Queue::fake()` and call `handle()` directly.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Structured model output | Prompt-and-parse JSON | laravel/ai `HasStructuredOutput` / `agent(schema: ...)` | Native JSON-schema output on Anthropic (`output_config`), OpenAI, OpenRouter and Gemini request builders; SDK decodes and tolerates code fences |
| Burst wait | A custom cache-key timer | `#[DebounceFor(20, maxWait: ...)]` + `debounceId()` | Built into Laravel 13.35; add the database claim on top |
| Scheduler | A cron script | `routes/console.php` `Schedule::job(...)` | Framework; add `schedule:work` to `composer dev` via `DevCommands::artisan('schedule:work', 'schedule')` (the default dev set is server, queue, logs, vite only) |
| HTTP clients for Twilio and Jev | cURL or Guzzle wrappers | `Http::` with `->throw()`, `->timeout()`, `Http::fake()` | Already in the framework, fakes in Pest |
| Phone parsing | Regex | `App\Support\PhoneNumber` (libphonenumber) | Already used; E.164 everywhere |
| Constant-time compare | `==` | `hash_equals` | Timing-safe |
| Profile page and login page | New Filament pages from scratch | Subclass `Filament\Auth\Pages\EditProfile` and `Filament\Auth\Pages\Login` | `->profile(Page::class)` exists (`Panel/Concerns/HasAuth.php:269`); override `form()` and `getRememberFormComponent()` |
| Return to the thread after re-login | Custom redirect | Filament's `redirect()->intended(...)` | Already how `LoginResponse` works (`Auth/Http/Responses/LoginResponse.php`); test it, do not build it |
| Postgres in CI | Docker-compose scripts | A GitHub Actions `services: postgres` container | Standard |

**Key insight:** every risky decision in this phase is a few lines of ordinary PHP (a regex veto, a threshold, an HMAC); the risk is in wiring and in the test net, not in missing libraries.

## Jev (D-03 to D-06): answers to Ian's three questions

Sources read this session: docs.typesafe.ai `api.md`, `primitives/choice.md`, `primitives/noul.md`, `confidence.md`, `concepts/state.md`, `models.md`, `model-jaggedness/jev-1.13.md`, `legal.md`; OpenRouter's Jev guide, its Decisions API reference and its System One guide. `[CITED]` throughout.

### Question 1: request and response shape for a choice question

**Request** (`POST {base}/systemone`, `Authorization: Bearer <key>`, `Content-Type: application/json`). Each entry under `questions` is keyed by a name you choose; the key is not sent to the model `[CITED: docs.typesafe.ai/api]`. A choice question is:

```json
{"type":"choice","instructions":"What does the customer want?","criteria":{"book_visit":"Wants a technician to come out","faq":"Asks a general question","price_question":"Asks what something costs","other":null}}
```

`criteria` is **required** for choice, is a map of option to description (`null` allowed), and holds at most 255 options. The laravel/ai SDK agrees: its `mapQuestion` sends `'type' => 'choice'` with `criteria` (`Gateway/Concerns/AnswersQuestions.php:93`) and requires at least two options (`Classification/Choice.php`). Include `other` in every list (D-04).

**Response** per choice key `[CITED: docs.typesafe.ai/api, primitives/choice]`:

```json
{"type":"choice","choice":"price_question","probabilities":{"book_visit":0.04,"faq":0.02,"price_question":0.9,"other":0.04},"confidence":0.81}
```

A yes/no question is `{"type":"noul","instructions":"...","criteria":{"true":"...","false":"..."}}` (criteria optional; on OpenRouter, if given, it must contain exactly the keys `true` and `false`) and returns `{"type":"noul","noul":0.95}`. Note that Ian's remembered response shape (`{"answers":{"key":{"noul":0.9}}}`) omits `type` and `model`/`usage`; the docs show every answer carries `type`, and the response also carries `model` and `usage`. The driver should read by `type` but tolerate a missing `type` only if the key shape matches the question it asked.

### Question 2: can `jev-1.13.0` be pinned on the route Ian uses?

- **TypeSafe's own API** (`https://api.typesafe.ai/v1/systemone`): yes. "Versioned IDs such as `jev-1.13.0` are accepted by the `model` field whether or not they appear in the list"; `jev-latest` is an alias that "moves when a new release ships" `[CITED: docs.typesafe.ai/models]`. This is also the route laravel/ai's `TypeSafeGateway` uses (`Gateway/TypeSafeGateway.php:22` `'/systemone'`, default base `https://api.typesafe.ai/v1`).
- **OpenRouter's `POST /api/v1/systemone`** (Ian's working route): the documented IDs are `typesafe/jev-1.13` and the alias `~typesafe/jev-latest`; bare IDs such as `jev-1.13` are "mapped onto the `typesafe/` namespace" `[CITED: openrouter.ai/docs/guides/community/typesafe-sdk]`. `jev-1.13.0` is **not mentioned**, and the Decisions API reference "does not say whether a dated ID or a `.0` suffix ... is accepted". The response reports the served snapshot (`typesafe/jev-1.13-20260917` in OpenRouter's example).
- **Safest planning assumption:** configure `JEV_MODEL=typesafe/jev-1.13` for the OpenRouter route (a version family, not an alias, so D-05's "never `jev-latest`" holds), `JEV_MODEL=jev-1.13.0` if the base URL is TypeSafe's own, and **store the response's `model` string on every verdict** so a silent snapshot change is visible in the labeled data. One manual check settles whether `typesafe/jev-1.13.0` works on OpenRouter (a single call with Ian's key); it is not run here and must not gate the plan.

### Question 3: timeouts and errors

- Direct API: errors are "standard HTTP status codes with a JSON body": 401, 422, 429, 529; "retry ... with exponential backoff" for 429 and 529 `[CITED: docs.typesafe.ai/api]`. The Python SDK adds `TypeSafeAPITimeoutError` for a client-side timeout. **No server-side timeout duration is documented.**
- OpenRouter: documented statuses 400, 401, 402, 403, 404, 413, 429, 500, 502, 503, 524, 529, each with an `error` object (`code`, `message`); 524 is "the provider request timed out at the edge network" with no duration given `[CITED: OpenRouter Decisions API reference]`.
- **Driver rule (D-03):** treat any non-2xx, any exception (including `ConnectionException` from `Http::timeout`), any non-JSON body, any missing or mistyped answer, and any `choice` not among the options we sent as **failed -> hold**. Use `Http::connectTimeout(3)->timeout(8)` as a starting point `[ASSUMED]`; no retry on the send path (a retry only delays the owner's draft). Keep Ian's completeness check: every question asked must come back with a numeric value in [0, 1] (noul) or a valid `choice` plus numeric `confidence` (choice).

### Settling D-04: named fields, and what "certainty" means for D-03

- **`state` accepts named fields.** `state` is `string | object | array`; "Use an object for most requests so each part of the state has a descriptive name" `[CITED: docs.typesafe.ai/api, concepts/state]`. OpenRouter's Decisions reference also lists `state` as "a string, object, or array". Ian's call sends a string only because titus-site has one text. Send `{"customer_message":"...","draft_reply":"..."}` and word each question so it names its field in backticks, as the docs show (`"Does `draft_reply` state or imply a price?"`). The docs support structured `instructions` the same way.
- **What "certainty" is.** Jev has two different things, and D-03's single 0.85 must be defined per type:

| Question | Jev returns | Certainty to compare with 0.85 | Sendable when |
|----------|-------------|--------------------------------|---------------|
| intent (choice) | `choice`, `probabilities`, `confidence` = `(p_max - 1/n) / (1 - 1/n)` | the returned `confidence` (0 = even split, 1 = certain; always <= `p_max`) | `confidence >= 0.85` **and** the chosen intent is marked sendable in `triage.md` (`other` never) |
| price, promise, discount, complaint (noul) | only `noul` = P(yes); **no confidence** `[CITED: confidence.md "Noul answers don't carry one"]` | `abs(2p - 1)`, the formula the docs give for a confidence-style number on a noul | `p < 0.5` and `abs(2p - 1) >= 0.85`, i.e. `noul <= 0.075` |
| life_safety (noul) | `noul` | "Lower [the threshold] when missing a true yes is expensive, such as failing to flag a safety issue" `[CITED: primitives/noul]` | send the safety line when `noul >= 0.35` (start; `[ASSUMED]`, "when unsure, send") |

  This is the strictest literal reading of D-03 (`confidence` is never above `p_max`). Docs examples of clear "no" answers include 0.02 and 0.07, so a 0.075 cut-off will hold some clear messages; that costs the owner a tap, never a bad send. **Put the three numbers in `config/gate.php` and record them with each verdict**, then loosen them from the approval log. Ask Ian to confirm this reading (OQ-1); the looser alternative is `p_max >= 0.85` and `noul <= 0.15`.
- **Option order matters:** Jev "leans toward the option that comes first" `[CITED: model-jaggedness/jev-1.13 #8]`. List `other` first so any lean errs toward a hold, and add a test that reorders the options and expects the same `sendable` result for the fixture messages.
- **Adversarial content can move answers** `[CITED: model-jaggedness #6]` and **numbers/arithmetic are unreliable** `[CITED: #2]`: both are the reason D-01/D-02 keep dollar and number detection and every veto in code.
- **Where Jev's life-safety answer can act:** the burst wait comes first for ordinary replies, and Jev is called once per *reply* with the draft in the state (D-04). So Jev's life-safety answer arrives only after the wait; the pre-wait path is keywords only (D-17). Treat Jev's answer as a second net: if it fires at draft time, send the safety line immediately and alert. A second Jev call per inbound would break D-04; do not add one.
- **Jev cannot be asked "is this a request for a person"** without adding a sixth yes/no (D-04 lists five). D-23 needs that detection. Recommended: code keywords ("real person", "talk to someone", "human", "call me") plus the agent's `wants_person` field, plus an optional `asks_for_person` noul in `triage.md`. Confirm with Ian (OQ-4).

### Driver contract (what the planner can task)

`Classifier::classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult` throws `ClassifierFailed` on any problem; `JevClassifier` (Http) and `FakeClassifier` (scripted answers, records calls, can throw) implement it. Config: `services.jev.{base_url, key, model, timeout}` with `base_url` defaulting to `https://openrouter.ai/api/v1`; the TypeSafe direct route works with the same code by changing the base URL, key and model. Jev receives `customer_message` and `draft_reply` only, never name, phone or card (D-05); the burst text is the concatenation of the unhandled customer messages.

**Data handling [CITED: docs.typesafe.ai/legal]:** Jev is not trained on customer data; ZDR is "for enterprise customers. Contact sales@typesafe.ai"; the Data Processing Agreement covers retention otherwise. OpenRouter documents `provider.zdr: true` on the **Decisions** API request; the System One guide does not mention it for `/v1/systemone`, so whether per-request ZDR works on Ian's route is unverified (test it as D-37/Deferred already says).

## laravel/ai 1.2.0 facts the plan relies on

- **Version:** 1.2.0 `[VERIFIED: composer show --direct]`. Provider names are the keys in `config/ai.php` `providers` (anthropic, openai, openrouter, ...). The file already holds `'default_for_classification' => 'typesafe',` (line 22) and a `'typesafe'` provider block (lines 148-151) `[VERIFIED: config/ai.php]`.
- **Provider and model chosen at runtime:** `prompt(..., provider: ['anthropic' => 'claude-sonnet-5-5', 'openai' => 'gpt-6.1-sol'])`; an associative array maps provider to model, a plain list uses each provider's default (`Providers/Provider.php::formatProviderAndModelList`). The SDK's own defaults are `claude-sonnet-5-5` (`AnthropicProvider.php:73`), `gpt-6.1-sol` (`OpenAiProvider.php:109`), and `anthropic/claude-sonnet-5.5` for OpenRouter (`OpenRouterProvider.php:116`). **Recommended example-folder agent:** primary `anthropic` / `claude-sonnet-5-5`, fallback `openai` / `gpt-6.1-sol` (different providers, both the SDK's current defaults). Each needs its key in `.env`; tests never call them.
- **Validating `provider` in the sync:** `Ai::textProvider($name)` needs no key and no network and I ran it: `anthropic`, `openai`, `openrouter` resolve; `typesafe` throws `LogicException` "does not support text generation"; an unknown name throws `InvalidArgumentException` "Instance driver [nope] is not supported." Catch both in `Validator` and write plain words ("agents/front-desk.md: provider 'typesafe' cannot write replies; use one of anthropic, openai, ...").
- **Structured output:** `HasStructuredOutput::schema(JsonSchema $schema): array` or `agent(schema: fn (JsonSchema $schema) => [...])`; the response reads like an array (`$response['label']`, `->toArray()`). `->string()->enum([...])->required()`, `->nullable()`, `->array()->items(...)`, `->object(fn ...)` all exist (`Illuminate\JsonSchema\Types\Type.php`). Malformed model JSON decodes to `[]` (`DecodesStructuredOutput.php`), so validate the label.
- **Failover semantics:** only `FailoverableException` types fall through: `ProviderConnectionException`, `RateLimitedException` (429), `InsufficientCreditsException` (402), `ProviderOverloadedException` (default `[502, 503, 504, 520, 522, 524]` at `HandlesFailoverErrors.php:77`; Anthropic adds 529 at `AnthropicGateway.php:192`). The docs say "Ordinary errors, like a validation or bad request error, will not trigger failover." `[CITED: laravel.com/docs/13.x/ai-sdk#failover]`. A plain 500 or 401 propagates (verified).
- **Fakes and assertions** `[CITED: laravel.com/docs/13.x/ai-sdk#testing]`, behaviour of the closure form `[VERIFIED: tinker]`: `Agent::fake(array|Closure)`, `Agent::fake()->preventStrayPrompts()`, `assertPrompted(string|Closure)`, `assertPromptedTimes(n)`, `assertNotPrompted`, `assertNeverPrompted`; an array element that is an array is returned as structured output; a closure receives `($prompt, $attachments, $provider, $model)`. Classification: `Classification::fake(array|Closure)`, `assertClassified(fn (ClassificationPrompt $p) => ...)`, `assertNothingClassified()`.
- **Unused but present:** the `agent_conversations` migration (`2026_10_10_191255_...`) belongs to the SDK's conversation memory; the thread comes from our `messages` table, so leave those tables alone and do not adopt `RemembersConversations`.
- **Events** worth logging: `AgentFailedOver`, `ProviderFailedOver`, `Classified` (`Events/`).

## Twilio (TXT-01, D-39, D-40)

**No new package.** Sending and webhook validation are plain HTTP; the arch tests that ban disk functions in `App\Http` and `App\Channels` do not get in the way.

| Topic | Finding |
|-------|---------|
| Messaging Service + Advanced Opt-Out | `OptOutType` is included on the inbound webhook "only when the message is sent to a phone number within a Messaging Service that has Advanced Opt-Out enabled and is not configured to drop messages"; the webhook URL may be set on the service or on the number; the number must be in the service's sender pool `[CITED: help.twilio.com articles 31560110671259, 360034798533]`. Advanced Opt-Out is **not on by default** (Console > Messaging > Services > Opt-Out Management). Values: `STOP`, `START`, `HELP`. Not present on outbound status callbacks. |
| What Twilio matches | Whole-body keywords only, case-insensitive: "Stop" opts out, "Stop please" does not. Default opt-out words: STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT (also REVOKE, OPTOUT per Twilio's tutorial); opt-in: START, YES, UNSTOP. With Advanced Opt-Out, HELP is also forwarded to your webhook `[CITED: help.twilio.com 223134027, twilio.com/docs/messaging/tutorials/advanced-opt-out]`. The backup matcher in code must be **whole-body, case-insensitive, trimmed**, and treat YES as re-subscribe **only for a contact currently opted out** (a normal "yes" to an intake question must not flip anything). Product consequence for Phase 3: a customer replying "cancel" to cancel an appointment is opted out by Twilio. |
| Confirmation text | "Twilio has already sent a confirmation message/reply ... we don't recommend sending another Message back" `[CITED: twilio.com/docs/messaging/tutorials/advanced-opt-out]` (D-39). The response to the webhook stays an empty `<Response></Response>`. |
| Error 21610 | "Attempt to send to unsubscribed recipient": the REST send returns **HTTP 400** with error code 21610, the message is not sent and not charged; it clears when the recipient texts START/YES/UNSTOP; opt-out is per Messaging Service `[CITED: twilio.com/docs/api/errors/21610, help.twilio.com 223133627]`. The JSON error body shape (`code`, `message`, `more_info`, `status`) is `[ASSUMED]`; the driver should read `json('code')` defensively. On 21610, set `sms_opted_out_at` if empty (the local record was stale) and surface "Opted out" to the owner. |
| Inbound form fields | `MessageSid` (also `SmsSid`, `SmsMessageSid`), `AccountSid`, `MessagingServiceSid`, `From`, `To`, `Body` (up to 1,600), `NumMedia`, `NumSegments`, geo fields (`FromCity`, ...), media (`MediaUrl{N}`, `MediaContentType{N}`) `[CITED: twilio.com/docs/messaging/guides/webhook-request]`; plus `OptOutType` as above. Twilio "expects ... TwiML in response": reply `<Response></Response>` with `Content-Type: text/xml`, as `FakeTextWebhookController` already does. Parameters "may be added without notice", so validate the signature over all received params, not a fixed list. |
| Status callback | POST form with `MessageSid`/`SmsSid`, `MessageStatus`, `ErrorCode` (when failed/undelivered), `AccountSid`, `From`; `To` and `MessagingServiceSid` are not guaranteed. Callbacks "aren't guaranteed to arrive in the order Twilio sent them"; respond 200 `[CITED: twilio.com/docs/messaging/guides/track-outbound-message-status]`. Map `delivered` -> `MessageStatus::Delivered`, `failed`/`undelivered` -> `Failed` (keep `ErrorCode` in `meta`), ignore the rest, and never move a message backwards (rank queued < sent < delivered/failed). Set `StatusCallback` per message on send; it must have a valid hostname and no underscores. |
| Send API | `POST https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json`, form fields `To`, `MessagingServiceSid` (or `From`), `Body` (<= 1,600), `StatusCallback`; HTTP basic auth with account SID and auth token; response has `sid`, `status` (`accepted` when only a service SID is given), `error_code` `[CITED: twilio.com/docs/messaging/api/message-resource]`. Store `sid` as `provider_message_id`; the existing unique `(provider, provider_message_id)` index then dedupes inbound retries. Using `MessagingServiceSid` is what makes Advanced Opt-Out apply to sends. |
| Signature | See Pattern 4. 403 on mismatch. Keep the configured webhook URL in `config/services.php`; the route sits under the existing `webhooks/*` CSRF exemption (`bootstrap/app.php:15`). |
| Selecting the driver | `AppServiceProvider::register` already binds `TextChannel` by `config('channels.text.driver')` (`'fake'` or `'twilio'`). `EnsureTextDriverIsFake` (`abort_unless(config('channels.text.driver') === 'fake', 404)`) guards only the fake route; add the Twilio routes behind a mirror middleware so exactly one webhook is live per driver. |
| Fake channel | `FakeTextChannel::parseInbound` reads Twilio-shaped fields; extend `InboundText` (currently `provider, providerMessageId, from, to, body, numMedia`) with `?string $optOutType`, let `/dev/chat` and tests send a body of exactly `STOP`/`START`, set `OptOutType` the way Twilio would, and have the fake record "refused" sends to opted-out numbers so tests can assert **nothing was sent**. |

**Testing TXT-01 without a live number:** fixtures of Twilio-shaped payloads (inbound text, STOP with `OptOutType=STOP`, HELP, media, status callbacks in each state, out-of-order callbacks), the signature vector above, `Http::fake()` for the send API including a 400/21610 body, and a test that `TEXT_CHANNEL=twilio` resolves `TwilioTextChannel` and `fake` resolves `FakeTextChannel` with no other change.

## Laravel mechanics new to this codebase

- **Scheduler.** `routes/console.php` currently holds only the `inspire` command. Add `Schedule::job(new SweepUndispatched)->everyFiveMinutes()->withoutOverlapping();` and `Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();`. `withoutOverlapping` uses a cache lock; the database cache supports locks and a `cache_locks` table exists in the base migration. Locally run `php artisan schedule:work`; **`composer dev` does not start it** (the `php artisan dev` set is `server`, `queue` = `queue:listen --tries=1 --timeout=0`, `logs`, `vite`, from `dev:list`). Register `DevCommands::artisan('schedule:work', 'schedule')` in `AppServiceProvider::boot` for the local environment. In production a one-minute cron runs `schedule:run`; deploy is out of scope (DEPL-01), so note it in the sprint file. Test scheduled work by calling the job's `handle()` directly with `Carbon::setTestNow`/`travel`, plus one test that `app(Schedule::class)->events()` contains both jobs.
- **Delayed alerts.** Use the `owner_alerts` table with `due_at` and a minute sweep rather than a delayed queue job: the draft may be handled before opening time (the sweep skips it), the due time can be days away (a weekend), and a restart or rollback cannot lose a pending alert. Urgent alerts are written with `due_at = now()` and sent in the same request/job.
- **Next opening time** is a pure function of `ActiveConfig::hours()` (weekly open/close strings `"08:00"`/`"17:00"`, `closed`, `closures` as `Y-m-d` strings, `booking_lead_minutes`) in `ActiveConfig::timezone()` (e.g. `America/Chicago`). Build it in the business time zone with Carbon (not UTC arithmetic), skip closure dates, and return the weekday word when it is not tomorrow ("at 9 am Saturday"). Test around a DST change and a closure that falls on the next day. When the business is open right now the "next opening" is undefined: see OQ-5.
- **Queue behaviour.** Tests run `QUEUE_CONNECTION=sync` and `CACHE_STORE=array` (`phpunit.xml`), so jobs run inline and `->delay()` and debounce are ignored; prove timing with `Queue::fake()` and call `handle()` directly. Local dev uses the `database` queue on SQLite with three writers (web, `queue:listen`, scheduler): PDO's SQLite default busy timeout is 60 s, but a `DEFERRED` transaction that upgrades to a write can still fail immediately with `SQLITE_BUSY`; if that shows up set `'transaction_mode' => 'IMMEDIATE'` in the sqlite block of `config/database.php` `[ASSUMED]`. CI Postgres tests use sync too; the `database` queue on Postgres claims jobs with row locks `[ASSUMED]`, so no change is needed there.
- **Job failure must not be silent.** `queue:listen --tries=1` means one attempt. If every provider in the chain fails, `ProcessInbound` should record a timeline note, alert the owner (urgent) and mark the burst handled, not throw into `failed_jobs`.
- **Idempotent send.** Store the outbound row as `queued` *before* calling the channel (as `SendOwnerReply` does), set `sent` after; on a retried job, find an existing outbound row for the same inbound burst (`meta.in_reply_to`) and reconcile instead of texting twice.

## INFRA-01: Postgres in CI

### What the existing suite does on Postgres (run this session)

I started `postgres:16-alpine` on a spare port and ran `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=... php artisan test --compact` against the unchanged repo, then removed the container. Result: **281 tests, 263 passed, 17 failed, 1 error** (the SQLite run: 281 passed). Environment variables set on the command line won over the `<env>` entries in `phpunit.xml` (PHPUnit does not override an existing variable unless `force="true"`), so CI can select Postgres with job-level `env:` and no config change.

| Cause | Tests affected | Fix |
|-------|---------------|-----|
| `icm_versions` ids do not restart at 1: SQLite reuses ids after `RefreshDatabase` rolls back; Postgres sequences are not rolled back, so labels read `v21`, `v27`, ... instead of `v1`, `v2` | `Filament\BusinessFolderPageTest` (3), `Icm\ChangelogTest` (6), `Icm\SyncTest` (7), `Records\RecordsRebuildTest` (1) | Build expected labels from the row (`"v{$version->id}"`, or `IcmVersion::label()`), never literal `v1`/`v2`. Keep test names; change assertions. |
| A 360-character name inserted into `contacts.name` (`varchar(255)`); SQLite does not enforce length | `Records\CustomerCardTest` "keeps a very long name from overflowing the filename" (1 error) | Use a name of 250 characters or less that still exceeds the 60-character slug limit, or build the model without saving. Do not widen the column. |

Other Postgres traps for new code and tests, none triggered by the present suite: explicit ids in factories (`Contact::factory()->create(['id' => 1])` does not advance the sequence, so a later auto id can collide); `ORDER BY` with nullable columns sorts NULLs last on Postgres and first on SQLite (the repo already guards one case with `orderByRaw('last_activity_at is null')` in `ContactsTable.php:40`); `LIKE` is case-sensitive on Postgres (match opt-out words in PHP, not SQL); the partial unique index is raw SQL and works on both; integer columns are 32-bit (see `Validator::MAX_CENTS`).

### Workflow (`.github/workflows/tests.yml`) `[ASSUMED]` content; actions and versions read from the GitHub API

```yaml
name: tests
on: { push: { branches: ['**'] }, pull_request: {} }
permissions: { contents: read }
jobs:
  pest:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix: { database: [sqlite, pgsql] }
    services:
      postgres:
        image: postgres:17
        env: { POSTGRES_USER: postgres, POSTGRES_PASSWORD: postgres, POSTGRES_DB: ops_test }
        ports: ['5432:5432']
        options: >-
          --health-cmd "pg_isready -U postgres" --health-interval 5s --health-timeout 5s --health-retries 10
    env:
      DB_CONNECTION: ${{ matrix.database }}
      DB_HOST: 127.0.0.1
      DB_PORT: 5432
      DB_DATABASE: ${{ matrix.database == 'pgsql' && 'ops_test' || ':memory:' }}
      DB_USERNAME: postgres
      DB_PASSWORD: postgres
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', extensions: 'mbstring, intl, dom, fileinfo, zip, pdo_sqlite, pdo_pgsql', coverage: none }
      - run: cp .env.example .env
      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: php artisan key:generate
      - run: php artisan test
```

Notes: no Node build is needed (`resources/views` contains no `@vite`; `layouts/app.blade.php` uses inline CSS). `composer check-platform-reqs` on the dev machine lists `ext-intl`, `ext-dom`, `ext-fileinfo`, `ext-zip` as needed. Pin `shivammathur/setup-php` to `@v2` (its releases are tagged `2.x`); consider pinning actions to commit SHAs. The repo has **no git remote**, so Ian pushes and confirms the first run; before that, reproduce locally with the same `docker run postgres:17` recipe and add a README line. Keep the SQLite job: INFRA-01 says both must pass so they cannot drift.

## Filament 5.10.1 specifics (D-18, D-29, D-31, D-32)

- **Profile with a phone field:** `->profile(EditProfileWithPhone::class)` in `AdminPanelProvider` (`HasAuth.php:269` `profile(?string $page = EditProfile::class, bool $isSimple = true)`); subclass `Filament\Auth\Pages\EditProfile` and override `form(Schema $schema)` (defaults: name, email, password, confirmation, current password) to add a `TextInput::make('phone')` normalised through `App\Support\PhoneNumber`. `fillForm()` uses `attributesToArray()` and the save path updates the model, so `phone` must be in `User`'s `#[Fillable]`.
- **Remember me ticked by default:** the login page builds `Checkbox::make('remember')` in `getRememberFormComponent()` (`Auth/Pages/Login.php:353`); subclass `Login`, override that method with `->default(true)`, and register `->login(RememberedLogin::class)`. The recaller cookie lasts 576,000 minutes (400 days) `[VERIFIED: SessionGuard.php:62 "protected $rememberDuration = 576000;"]`; the session itself stays `SESSION_LIFETIME=120` minutes, which is what makes the "expired session returns to the thread" case real.
- **Return to the thread after sign-in:** nothing to build. `LoginResponse` ends with `redirect()->intended(Filament::getUrl())` and the panel's `Authenticate` middleware stores the intended URL through Laravel's `redirect()->guest()`. Add a feature test that visits the conversation URL signed out, signs in, and lands on it.
- **Phone-friendly conversation view:** `ConversationInfolist` renders a 4-column Customer section then the Livewire `ConversationThread` (inline styles, `wire:poll.5s`). Needs: a "Needs you" block at the top when a live draft exists (held reason, editable draft, Send, Discard), the Customer section `->collapsible()->collapsed()` (methods exist: `CanBeCollapsed.php` `collapsed`, `collapsible`, `persistCollapsed`), owner and agent messages styled differently (UAT note), padding on the reply textarea (UAT note), and the thread scrolled to the latest message on load. The roadmap marks this phase `UI hint: yes`; run `/gsd-ui-phase 2` before planning the UI tasks so "visible without scrolling at phone width" is a measured layout, not an adjective.
- **Take over / Hand back:** one header `Action` on `ViewConversation` (pattern already used for Close and Reopen), plus the banner text in D-25.
- **Dashboard home (D-32):** `AdminPanelProvider` calls `discoverWidgets(...)` and lists `AccountWidget` and `FilamentInfoWidget`; add four `TableWidget`s (`Filament\Widgets\TableWidget` exists) and drop the two stock widgets.
- **Phone UAT (D-30):** alert links are built from `APP_URL`; set it to the machine's LAN address and serve with `php artisan serve --host=0.0.0.0` for the real-phone check, and run `schedule:work` and the queue worker too.

## Cartographer map ("starts in Sprint 1")

`docs/SPEC.md:308` says only "A cartographer map starts in Sprint 1, once there is something to map." It refers to Ian's own instrument at `~/Projects/cartographer` (README, `rules.md`, `reference/card-types.md`, `walk-order.md`, `statuses.md` read this session): it leaves a `map/` folder next to the territory's entry file with a catalog (`map/CLAUDE.md`, under 60 lines), one **object card** per noun, **process cards** only for movements worth one, a change-impact index once there are enough cards, and a sibling `map/VERIFICATION.md` for deviations. Rules that shape the task: fence the territory in one sentence first; **inventory before cards, and Ian rules on the flagged rows before any card is written**; cards cite `path:line` and never copy source; every card carries Hits / Does not hit; five to ten cards is normal, two to four process cards at most; each card has a status (live, leftover, ghost, half-wired) and a verification record with a date.

**Smallest version that fits this phase** `[ASSUMED]`: scope sentence "the nouns a cold reader must understand before changing how an inbound text becomes a reply or a draft"; **5 object cards** (Message and its verdict, Conversation and paused state, Agent file and compiled version, Intake answer, Contact opt-out) and **1 process card** (answer-inbound: record, claim, wait, agent, gate, send or draft), the catalog, and one routing row added to the `Map` table in `CLAUDE.md` (outside the Boost-generated block; do not write the block's tag name anywhere, per the file's own warning). Skip the effects index and `VERIFICATION.md` unless the inventory finds deviations. Do it as the **last task of the phase** (it maps what exists) and make the ruling step an explicit `checkpoint:human-verify`; the cartographer folder is outside this repo, so the task either copies the rules it needs into the prompt or points Claude at that path.

## Verified in-repo values the plan will touch (quoted verbatim)

| Item | Quote | Where |
|------|-------|-------|
| Message statuses | `case Draft = 'draft'; case Queued = 'queued'; case Sent = 'sent'; case Delivered = 'delivered'; case Failed = 'failed'; case Received = 'received';` | `app/Enums/MessageStatus.php:7-12` |
| Authors | `case Customer = 'customer'; case Agent = 'agent'; case Owner = 'owner';` | `app/Enums/MessageAuthor.php:7-9` |
| Activity types | `case TextReceived = 'text_received'; case FormReceived = 'form_received'; case ReplySent = 'reply_sent'; case Note = 'note';` | `app/Enums/ActivityType.php:7-10` |
| Loader types | `public const TYPED = ['business', 'hours', 'lead_sources', 'knowledge', 'service', 'voice', 'sending_rules'];` and `private const LATER_SPRINT = ['agent', 'automation', 'connector', 'walk_test'];` | `app/Icm/Loader.php:23`, `:30` |
| Singleton types | `'business' => 'business/profile.md', 'hours' => 'business/hours.md', 'lead_sources' => 'business/lead-sources.md', 'voice' => 'voice/how-we-talk.md', 'sending_rules' => 'rules/sending.md',` | `app/Icm/Validator.php:22-26` |
| Current sending-rules rules | `'send_on_own' => 'bail\|present\|array', 'draft_for_owner' => 'bail\|present\|array', 'owner_alert' => 'bail\|required',` | `app/Icm/Validator.php:141-145` |
| Compile index default | `default => $compiled['index'][$file->type] = $file->path,` | `app/Icm/FolderSync.php:224` |
| Dispatch today | `ProcessInbound::dispatch($message->id)->afterCommit();` | `app/Actions/RecordInbound.php:63` |
| CSRF exemption | `$middleware->preventRequestForgery(except: ['webhooks/*', 'leads']);` | `bootstrap/app.php:15` |
| Morph map | `'contact' => Contact::class, 'conversation' => Conversation::class, 'message' => Message::class, 'user' => User::class,` | `app/Providers/AppServiceProvider.php:62-65` |
| Channel interface | `name(): string`, `send(string $toE164, string $body): string`, `parseInbound(Request $request): InboundText` | `app/Channels/TextChannel.php:12,17,22` |
| Inbound DTO | `provider, providerMessageId, from, to, body, numMedia` | `app/Channels/InboundText.php:7-14` |
| Reply length | `public const int MAX_LENGTH = 1600;` and `Cache::lock("reply:{$conversation->id}", 30)` | `app/Actions/SendOwnerReply.php:27`, `:48` |
| Money guard | `preg_match('/cents\|price\|cost\|amount\|total\|fee\|balance/i', $column['name'])` | `tests/Feature/Quality/MoneyIsIntegerCentsTest.php:31` |
| Test env | `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` | `phpunit.xml` `<php>` block |
| Card renderer | `[$label, $intro, $body] = match ($activity->type) { ActivityType::TextReceived => ..., ActivityType::FormReceived => ..., ActivityType::ReplySent => ..., ActivityType::Note => [...] };` and `"Reply from owner{$details}"` | `app/Records/CustomerCard.php:152-157`, `:236` |

## Common Pitfalls

### Pitfall 1: The sweep texts old customers
**What goes wrong:** every inbound row from Phase 1 (and the seeded Dana Whitfield conversations) has `dispatched_at = NULL`; the new sweep selects "inbound, older than two minutes, no `dispatched_at`" and runs the agent on all of them.
**How to avoid:** the same migration backfills `dispatched_at` and `handled_at` to `created_at` for all existing rows (a data migration, plus the code change). Add a test that a pre-existing undispatched row older than the migration is not swept, and bound the sweep to a recency window (for example 24 hours) as a second guard.
**Warning signs:** the first `schedule:run` after migrating queues dozens of `ProcessInbound` jobs.

### Pitfall 2: A column name trips the money guard
**What goes wrong:** `MoneyIsIntegerCentsTest.php:31` fails any column whose name contains `cents`, `price`, `cost`, `amount`, `total`, `fee` or `balance` unless it is an integer ending in `_cents`. A boolean `held_for_price` or `price_flag` on `messages` fails the full suite.
**How to avoid:** put gate detail in the `gate` JSON (key names are not checked) and keep column names to neutral words (`gate_outcome`, `gate_reason`, `label`). Name the "which rule" values as data, not columns.

### Pitfall 3: Moving `agent` out of `LATER_SPRINT` breaks three tests that use it as the example
`ChangelogTest.php:72-76`, `SyncTest.php:48-65` and `BusinessFolderPageTest.php:134-138` write `type: agent` files and expect "Not supported yet" notices. Switch those fixtures to `type: automation` (stays in `LATER_SPRINT`); the behaviour they protect is unchanged. Also update `examples/example-hvac/agents/README.md` ("arrive in Sprint 1") and `rules/CONTEXT.md`.

### Pitfall 4: `FolderSync::compile` stores one path per type
`default => $compiled['index'][$file->type] = $file->path` (line 224) means a second `agent` file silently replaces the first in the index while both stay in `files`. Store agents as `index['agent'][<channel>] = path` (as `service` is keyed by slug), make `ActiveConfig::agent(string $channel)` read it, and have the Validator reject a second agent on a channel (D-35). `set_replies` and `triage` are singletons: add them to `Validator::SINGLETONS` (`'set_replies' => 'rules/replies.md'`, `'triage' => 'rules/triage.md'`) and to `Loader::TYPED`. An example folder without them then fails the sync with "no file has type: ...", so add the files to `examples/example-hvac` in the same task as the rule. Zero agent files should be legal (the front desk is simply off; log it and show it on the Business Folder page), but one per channel at most.

### Pitfall 5: Arch tests restrict where `IcmVersion` and disk functions may be used
`tests/Unit/ArchitectureTest.php` allows `App\Models\IcmVersion` only in `App\Icm`, `App\Console\Commands`, `App\Filament\Pages`, `App\Models`, `Database\Factories`, `Database\Seeders` and `Tests`, bans `file_get_contents`, `fopen`, `file`, `glob`, `scandir`, `parse_ini_file` in `App\Http`, `App\Actions`, `App\Livewire`, `App\Filament`, `App\Channels`, `App\Jobs`, `App\Models`, `App\Records`, `App\Listeners`, `App\Events`, `App\Support`, and bans `env()` in `App`. For `intake_answers.icm_version_id` add `ActiveConfig::versionId(): int` instead of typing an `IcmVersion` in actions; read the raw webhook body with `$request->getContent()`; read keys through `config()`.

### Pitfall 6: Validator accepts values nothing routes
Today `owner_alert` only has to be present (`'bail|required'`). When it becomes structured, **reject** what Phase 2 cannot honor: channel `email` ("email alerts arrive later"), `escalate_to` other than `owner` (D-23), agent `channel` other than `sms`. A value the validator admits and persists but no branch handles is the "admitted-but-unrouted" trap the cartographer names. Also validate that fixed texts (`ai_disclosure`, `sign_off`, holding and handoff replies) contain no `never_say` phrase and pass the code checks, in plain words naming the file and the field.

### Pitfall 7: Every existing test that records an inbound now runs the agent
`QUEUE_CONNECTION=sync` runs `ProcessInbound` inline; the existing inbound tests use `Queue::fake()`, but any that do not (DevChat, matching, form leads, seeders) would reach the agent, Jev and the network. Wave 0: in `tests/Pest.php` `beforeEach`, bind `Classifier` to `FakeClassifier`, `FrontDeskAgent::fake()->preventStrayPrompts()`, `Http::preventStrayRequests()`, and make `ProcessInbound` return quietly on `NoActiveVersion`.

### Pitfall 8: `CustomerCard` has an exhaustive `match` and labels every reply "from owner"
Adding `ActivityType` cases (draft held, intake answer, takeover, opt-out, alert sent) without extending the `match` at `CustomerCard.php:152-157` throws `UnhandledMatchError` on render and breaks the card job for every contact that has one. `replyParts` hard-codes "Reply from owner" (`:236`); an agent reply written to the same `ReplySent` type would be labelled as the owner's, and the agent reads this card as its own history (AGNT-02). Key the label on `$message->author`, give agent text a label of its own, and add the "Intake answers" section here. Add `intake_answer` and `owner_alert` to the morph map in `AppServiceProvider::boot` before any activity uses them as a subject, because `Relation::enforceMorphMap` throws for an unlisted model.

### Pitfall 9: Debounce is not the guarantee
See Pattern 3: the debounce token lives in the cache and fails open. Two `ProcessInbound` runs for one conversation must both be safe, which is the database claim's job. Test it by calling `handle()` twice.

### Pitfall 10: Only structured-output validation stands between a bad model and the customer
A model that returns `{}` (decoded from broken JSON), a label outside the enum, a 3,000-character reply or an empty reply must fall through to the next provider, then to "owner alerted, no reply". Length over `SendOwnerReply::MAX_LENGTH` (1,600) becomes a draft, not a truncated send.

### Pitfall 11: The model's FAQ and service files contain dollar amounts
`business/faq.md` says "The diagnostic fee is $89." and `ac-repair.md` has `price_cents: 8900`. A `faq_answer` quoting that fee trips the dollar-amount veto and becomes a draft, which is the intended outcome of D-01; but the UAT and fixtures must expect it ("how much is a diagnostic?" is a draft with reason "asks about price", not a sent FAQ answer). Fixed-price auto-send is deferred.

### Pitfall 12: Auto-responder ping-pong and cost
A customer's phone (an out-of-office bot, a carrier shortcode) can answer every agent text, producing an endless loop that spends a model call and a Jev call per turn. Cap agent replies per conversation per hour (start at 6) and per day; on the cap, pause the conversation and alert the owner `[ASSUMED]`.

### Pitfall 13: Webhook URL mismatch makes every Twilio request a 403
Behind ngrok, a tunnel or a TLS-terminating host, `fullUrl()` differs from what Twilio signed. Sign over the configured URL, also try the variant with and without the default port (as the Twilio SDK does), and log a mismatch **without** the token or body.

### Pitfall 14: "YES" is both an answer and a re-subscribe keyword
Only treat START/UNSTOP/YES as opt-in when the contact is currently opted out; otherwise a normal "yes" to an intake question would be swallowed. Twilio's own matching is whole-body only; copy that, so "stop the noise please" is an ordinary message.

### Pitfall 15: Agent sends and owner sends share one lock
`SendOwnerReply` holds `Cache::lock("reply:{conversation}", 30)` and throws a `DomainException` when it cannot get it. Route agent, fixed-reply and approved-draft sends through one `OutboundSender` that takes the same lock and **waits briefly** for it (the agent job can retry; the owner must not see "another reply is being sent" because the agent happened to be sending). Put the opt-out and paused checks in that same chokepoint so no path skips them; the life-safety line passes the paused check but never the opt-out check (D-17, D-40).

### Pitfall 16: Alert text is an attack surface and a billing line
Replace URLs in the quoted customer text with `[link]` (D-21), and also replace email addresses and long digit runs: phone-number and email auto-linking in the Messages app makes those tappable too, which defeats "the only tappable link is ours" (the dashboard link goes last on its own line). Cut by characters with a margin: an emoji switches the whole text from 160 to 70 characters per segment. Escape in Filament notification titles with `e()` as `ConversationThread` already does.

### Pitfall 17: Ian's test phone is both owner and customer
During live tests the owner's phone texts the business number, so the app sees a "customer" and the agent answers the owner; and an owner who replies STOP to an alert blocks every alert (error 21610 on the owner's number). Alert sends must surface 21610 on the dashboard (D-19), and the plan should say explicitly that in Phase 2 a user's phone is **not** special-cased inbound (the reply-code parser is 2.1). See OQ-7.

### Pitfall 18: `<env>` entries in `phpunit.xml` do not beat real environment variables
Good for CI (job `env:` selects Postgres), bad if a developer's shell has `DB_CONNECTION=pgsql` exported and thinks they are on SQLite. Print the connection in the Wave 0 smoke test.

## Code Examples

### Jev request and completeness check (proposed; shapes from docs.typesafe.ai)

```php
// Proposed driver body. Shapes: docs.typesafe.ai/api (request: model, state, questions; answer: type, choice, probabilities, confidence | noul).
$response = Http::withToken($this->key)->acceptJson()
    ->connectTimeout(3)->timeout(8)
    ->post(rtrim($this->baseUrl, '/').'/systemone', [
        'model' => $this->model,                                    // typesafe/jev-1.13 (OpenRouter) or jev-1.13.0 (TypeSafe)
        'state' => ['customer_message' => $customerMessage, 'draft_reply' => $draftReply],
        'questions' => $questions,                                  // built from rules/triage.md, `other` listed first
    ])->throw();

$answers = $response->json('answers');
foreach ($questions as $key => $question) {
    // every question asked must come back usable, otherwise the call failed (D-03)
    $this->assertUsable($key, $question['type'], $answers[$key] ?? null);   // throws ClassifierFailed
}
```

### Fake the model in a gate test (SDK-verified shapes)

```php
FrontDeskAgent::fake([['reply' => 'A visit starts at $89.', 'label' => 'faq_answer', /* ... */]])->preventStrayPrompts();
$this->app->instance(Classifier::class, new FakeClassifier(sendable: true));   // Jev says yes ...
// ... expect a draft anyway: the dollar-amount veto beats a faq_answer label and a sendable Jev.
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hand-written delayed job + cache key for "wait for more texts" | `#[DebounceFor]` job attribute | Laravel 13.x (present in 13.35.0) | Burst wait is one attribute; still pair with a database claim |
| Call Jev with raw HTTP only | `Laravel\Ai\Classification` + `TypeSafeProvider` | laravel/ai 1.x, documented as experimental | Optional second implementation behind `Classifier` |
| Twilio SDK for signature checks | A short HMAC compare per Twilio's own algorithm | stable | Avoids a dependency |
| `composer dev` with `concurrently` | `php artisan dev` (multiplex) with a command registry | Laravel 13 | Scheduler is not included; register it |

**Deprecated/outdated:** Twilio parameters `SmsSid` and `SmsMessageSid` are deprecated duplicates of `MessageSid`; read `MessageSid`.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `typesafe/jev-1.13.0` may or may not be accepted on OpenRouter; plan on `typesafe/jev-1.13` there | Jev Q2 | Pin string needs one config edit after a live call |
| A2 | Jev timeouts of connect 3 s / total 8 s are adequate | Jev Q3 | Too short holds more drafts; too long delays the draft alert |
| A3 | D-03's 0.85 means `confidence` for choice and `abs(2p-1)` for noul (strictest reading); life-safety fires at noul >= 0.35 | Jev certainty table | Too many holds (safe) or an unwanted loosening; Ian should confirm (OQ-1) |
| A4 | `owner_alerts` table is better than columns on `messages` | Standard Stack, Data model | One extra migration in 2.1 if wrong |
| A5 | Twilio's REST error body has `code`, `message`, `more_info`, `status` | Twilio | Driver reads `code` defensively; low risk |
| A6 | Providers may reject consecutive same-role messages, so collapse them | Pattern 1 | Harmless if wrong |
| A7 | SQLite `IMMEDIATE` transactions may be needed with three local writers; Postgres DB queue uses row locks | Laravel mechanics | Only local dev flakiness |
| A8 | Cap of 6 agent replies per conversation per hour | Pitfall 12 | Number is a starting point |
| A9 | Smallest cartographer map = 5 object cards + 1 process card + catalog + one routing row | Cartographer | Ian may want more or less |
| A10 | New `app/Gate` and `app/Ai` namespaces are acceptable | Project structure | If Ian declines, nest under `app/Actions/FrontDesk/` |
| A11 | `sign_off` is appended to every agent text, disclosure only to the first agent text per contact | OQ-3 | Wording/placement change only |
| A12 | After a life-safety line the conversation is paused and the owner alerted | OQ-2 | Agent could keep chatting after a safety message |
| A13 | Recommended example agent: Anthropic `claude-sonnet-5-5`, fallback OpenAI `gpt-6.1-sol` | laravel/ai | Model names are the SDK's defaults today; a client may choose differently |
| A14 | `twilio/sdk` passes a legitimacy check | Package audit | Not recommended anyway; gate tool does not support Packagist |

## Open Questions

1. **OQ-1: Does Ian accept the strict reading of D-03's 0.85?** (choice `confidence >= 0.85`; noul `abs(2p-1) >= 0.85`, i.e. `noul <= 0.075`.) The looser alternative is `p_max >= 0.85` and `noul <= 0.15`. Recommendation: ship strict, keep both numbers in `config/gate.php`, store them on each verdict, relax from the approval log.
2. **OQ-2: What happens after the life-safety line?** D-17 says it sends first and skips the wait; it does not say whether the agent then keeps replying. Recommendation: pause the conversation (same paused state), alert the owner immediately, and let the owner hand back. A false alarm costs one tap.
3. **OQ-3: Where do `sign_off` and the AI disclosure go?** GATE-02 says the first outbound message discloses the AI and `sign_off` "is applied". Recommendation: disclosure once, at the start of the first *agent* text to a contact (even if the owner texted first); `sign_off` appended by code to every agent text except the life-safety line.
4. **OQ-4: A request for a person.** D-04 lists five yes/no questions; D-23 needs a sixth signal. Recommendation: code keywords + the agent's `wants_person` field + an `asks_for_person` noul in `triage.md`.
5. **OQ-5: What does `{next_open}` say when the business is open right now?** D-13 only covers closed times. Recommendation: render "today" while open, else "at 8 am tomorrow" / "at 9 am Saturday", closures skipped; test around DST and a closure tomorrow.
6. **OQ-6: Do agent texts need a hard daily cap, and who is told at the cap?** Recommendation in Pitfall 12.
7. **OQ-7: Should a user's own phone be exempt from the agent?** Ian's single test phone is both. Recommendation: no exemption in Phase 2; warn in the sprint file; 2.1 adds the reply-code parser that needs the exemption.
8. **OQ-8: Draft row identity.** Either the draft row becomes the sent message (same row, `original_body` kept when edited) or a new owner row is written and the draft is marked approved. Recommendation: same row, so the labeled data is one record per decision.
9. **Sprint file for the walk-through log:** `docs/sprints/` has only `sprint-0.md`; CLAUDE.md wants the log in the current sprint's file. Recommendation: create `docs/sprints/sprint-1.md` in the first plan (only because CLAUDE.md requires the log; Boost's "only create documentation files if asked" yields to the explicit project rule).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | yes | 8.5.0 | — |
| `pdo_pgsql`, `pdo_sqlite` | INFRA-01 | yes | bundled | — |
| Docker | local Postgres run | yes | running (another project's Supabase containers exist on 54322; I used a throwaway `postgres:16-alpine` on 55432 and removed it) | CI-only verification |
| Postgres | INFRA-01 | via Docker | 16 (probe), 17 chosen for CI | — |
| Provider API keys (Anthropic, OpenAI, OpenRouter) | live agent runs, `/dev/chat` | not inspected (`.env` is protected) | — | Fakes in every test |
| `OPENROUTER_API_KEY` / `TYPESAFE_API_KEY` for Jev | live Jev call | not inspected | — | `FakeClassifier`; a missing key makes the driver fail closed (everything is a draft) |
| Twilio account, number, Messaging Service with Advanced Opt-Out | live only | not available (A2P pending) | — | Fake channel and Twilio-shaped fixtures; does not gate the phase |
| `gh` CLI | action versions | yes | — | — |
| git remote | CI run | none configured | — | Ian pushes and confirms the first run |

## Validation Architecture

`workflow.nyquist_validation` is not set in `.planning/config.json` (only `use_worktrees`, `tdd_mode: true`, `test_command`), so this section applies; `tdd_mode` is true, so each behaviour below starts as a failing test.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 on PHPUnit, `laravel/pao` agent output |
| Config file | `phpunit.xml` (SQLite in memory, sync queue, array cache); `tests/Pest.php` (RefreshDatabase, temp copy of `examples/example-hvac` per test) |
| Quick run command | `php artisan test --compact --filter=<Name>` or `vendor/bin/pest tests/Feature/FrontDesk` |
| Full suite command | `php artisan test --compact` (SQLite). Postgres: start `docker run --rm -d -p 55432:5432 -e POSTGRES_PASSWORD=probe -e POSTGRES_DB=ops_test postgres:17`, then `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ops_test DB_USERNAME=postgres DB_PASSWORD=probe php artisan test --compact` |
| Baseline | SQLite: 281 passed (about 8 s). Postgres: 263 passed, 17 failed, 1 error before Wave 0 |

### Phase Requirements to Test Map

| Req / Decision | Behavior | Test Type | Automated Command | File Exists? |
|----------------|----------|-----------|-------------------|--------------|
| AGNT-01, D-35 | Agent file valid compiles; broken one rejected in plain words (unknown provider, non-text provider, missing field, second agent on a channel); `index['agent']` keyed by channel | feature | `php artisan test --filter=AgentFile` | Wave 0 |
| D-08, D-09 | Unknown action key rejected; `price` in `send_on_own` rejected with a plain sentence; structured `owner_alert`; `email` channel rejected | feature | `php artisan test --filter=SendingRulesValidation` | Wave 0 |
| D-10..D-12 | `replies.md` missing reply, unknown placeholder, dollar/discount/promise/`never_say` text rejected | feature | `php artisan test --filter=RepliesValidation` | Wave 0 |
| AGNT-02, D-36, D-41 | Inbound SMS gets a reply in the same conversation using whole routed files plus the fresh card; a web-form message never gets a text | feature | `php artisan test --filter=ProcessInbound` | Wave 0 |
| AGNT-03, D-33, D-34 | Answers stored with `icm_version_id`, timeline entry, card section; correction adds a row; invalid `question_key`/option stored as null option with raw words | feature | `php artisan test --filter=IntakeAnswers` | Wave 0 |
| AGNT-04 | Primary overloaded -> fallback answers; primary plain 500 -> fallback; primary malformed -> fallback; all fail -> owner alerted, no throw | feature | `php artisan test --filter=AgentFallback` | Wave 0 |
| GATE-01, D-01..D-04, D-07 | Table of (label, text, Jev) -> send/draft; scripted model labels a price reply `faq_answer` -> still draft; Jev throws/times out/misses a key/low certainty -> draft; Jev not called when code vetoes; verdict stored | feature + unit | `php artisan test --filter=Gate` | Wave 0 |
| D-02 | Code checks: dollar amounts, number near price word, discount and promise phrases, `never_say`; false-positive cases ("open at 8") | unit | `php artisan test --filter=CodeChecks` | Wave 0 |
| Jev driver | Choice and noul shapes, completeness check, option order stable, `Http::fake` 401/429/500/524/timeout/garbage -> `ClassifierFailed`; no name or phone in the body | feature | `php artisan test --filter=JevClassifier` | Wave 0 |
| GATE-02 | First agent text carries disclosure and sign-off, never a `never_say` phrase; asks for person -> handoff reply + paused + alert | feature | `php artisan test --filter=Disclosure` | Wave 0 |
| D-12..D-14, D-13 | Holding reply once per draft chain; times computed from `hours.md` (closed, closure tomorrow, DST) | feature + unit | `php artisan test --filter=FixedReplies` | Wave 0 |
| D-15..D-17 | Keyword triggers (and non-triggers: plain flooding); sent first, no wait, in a paused conversation; disclosure after the safety sentence on a first message | feature | `php artisan test --filter=LifeSafety` | Wave 0 |
| GATE-03, D-23..D-26 | Take over stops agent sends; every customer text while paused alerts; owner reply pauses, approving a draft does not; hand back resumes | feature | `php artisan test --filter=TakeOver` | Wave 0 |
| GATE-04, D-27, D-28 | Approve, edit-and-send, discard; one live draft (partial index); replaced draft refused with the plain sentence and edit kept | feature (Livewire/Filament) | `php artisan test --filter=DraftReview` | Wave 0 |
| D-18..D-22 | Alert text built by code, `[link]` replacement, dashboard link last on its own line, delayed alert at next opening, no-phone warning on Business Folder page | feature | `php artisan test --filter=OwnerAlert` | Wave 0 |
| D-39, D-40 | `OptOutType=STOP`/exact word records opt-out, no reply; every outbound path blocked (agent, owner, holding, handoff, life-safety); texts again -> owner alerted; START clears | feature | `php artisan test --filter=OptOut` | Wave 0 |
| TXT-01 | Signature vector `RSOYDt4T1cUTdK1PDd93/VVr8B8=`; bad/missing signature 403; payload fixtures parse; send builds the right form and stores `sid`; 21610 handling; status callback ordering; `TEXT_CHANNEL` switch | feature | `php artisan test --filter=Twilio` | Wave 0 |
| D-42 | Retry path and sweep share one claim; 0 rows updated -> no dispatch; backfilled rows not swept; the Phase 1 test `records a retried webhook once and queues nothing the second time` is rewritten to assert the claim | feature | `php artisan test --filter=Dispatch` | exists, to change |
| D-43 | Empty name filled from agent output from own number; form name only on form-created contact; never overwrite | feature | `php artisan test --filter=ContactName` | Wave 0 |
| D-18, D-31 | Profile saves `phone`; login remember box defaults ticked; sign-in returns to the conversation URL | feature (Filament) | `php artisan test --filter=Profile` | Wave 0 |
| D-29, D-32 | Conversation view shows held reason, draft, Send first; customer section collapsed; widgets list drafts oldest first | feature + manual | `php artisan test --filter=ConversationView` | Wave 0 |
| D-07 | Export command emits one row per decision with label, rules, Jev answers, certainty, owner action | feature | `php artisan test --filter=GateExport` | Wave 0 |
| INFRA-01 | Full suite green on SQLite and Postgres; workflow file present | CI + local docker | commands above | Wave 0 |
| D-30 | Alert link opens on a real phone over Wi-Fi | manual | UAT checklist | manual-only (needs a phone) |
| Live model / live Twilio / ZDR checks | Provider key run, A2P number, DPA review | manual | UAT / pre-client gate | manual-only |

### Sampling Rate

- **Per task commit:** the narrowest `--filter` for the files touched, plus `vendor/bin/pint --dirty --format agent`.
- **Per wave merge:** `php artisan test --compact` (SQLite).
- **Phase gate:** full suite green on SQLite **and** on the Postgres recipe above before `/gsd-verify-work`; then the manual items.

### Wave 0 Gaps

- [ ] Fix the 17 failing tests and 1 error on Postgres (Pitfall table in INFRA-01) and convert the three `type: agent` fixtures (Pitfall 3).
- [ ] `tests/Pest.php` safety net: `Http::preventStrayRequests()`, `FrontDeskAgent::fake()->preventStrayPrompts()`, `Classifier` bound to `FakeClassifier` (Pitfall 7).
- [ ] `FakeClassifier`, an extended `FakeTextChannel` (opt-out, refused sends) and Twilio payload fixtures.
- [ ] `tests/Feature/FrontDesk/`, `tests/Feature/Channels/`, `tests/Feature/Icm/AgentFile*` files; **helper functions are global across the suite, so give every helper a file-specific name** (STATE 01-06 lesson; a duplicate aborts the full run).
- [ ] `.github/workflows/tests.yml` and a README line for the Postgres recipe.
- [ ] Factories for any new model (`IntakeAnswer`, `OwnerAlert`) per Boost rules.

## Security Domain

`security_enforcement` is not set in `.planning/config.json`, so it is enabled.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Filament login (existing); remember-me default ticked (400-day recaller cookie), password change via the profile page |
| V3 Session Management | yes | Laravel sessions + `AuthenticateSession` already in the panel; sign out everywhere is 2.1 |
| V4 Access Control | yes | `User::canAccessPanel` returns true for every user (single owner, Phase 1 D-24); acceptable until 2.1's role split; webhooks authenticated by signature, not session |
| V5 Input Validation | yes | Form requests for webhooks; validate every model output (label enum, length, slug exists, question key exists); validator for every new file type |
| V6 Cryptography | yes | HMAC-SHA1 per Twilio's spec with `hash_equals`; no custom crypto; secrets only in `.env` read through `config/services.php` |
| V7 Error Handling and Logging | yes | Do not log message bodies outside local (the fake channel already follows this); never log the signature, auth token or API keys; the verdict record holds customer text, so it is PII to be exported deliberately |
| V8 Data Protection | yes | Jev gets message text only (D-05); LLM providers get the thread and card (D-37, ZDR before a real client) |
| V10 Malicious Code / supply chain | yes | No new Composer or npm package; CI actions pinned (prefer commit SHAs), `permissions: contents: read` |
| V13 API and Web Service | yes | Twilio webhooks and status callback: signature, unique `MessageSid`, JSON/TwiML 200 only |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Forged Twilio webhook | Spoofing | `X-Twilio-Signature` check over the configured URL; 403; the fake route 404s unless the fake driver is on |
| Replayed webhook | Repudiation / Tampering | Unique `(provider, provider_message_id)` already in `messages`; `dispatched_at` claim |
| Prompt injection in a customer text or in the card | Tampering | Model output is untrusted: label + code veto + Jev; card quotes customer words under a label (`CardInjectionTest`); the model has no tools that send |
| Steering Jev with adversarial text | Tampering | Jev can only hold (D-02); numbers, prices, promises, discounts are code checks |
| Alert phishing (customer text with a link in the owner's alert) | Spoofing / EoP | `[link]`/email/digit replacement, our link last on its own line (D-21) |
| Stored XSS via message bodies or names in Filament | Tampering | Blade escaping, `e()` in notification titles (existing practice), no `{!! !!}` for customer text |
| Reply loop / cost exhaustion with another bot | DoS | Per-conversation reply cap, debounce, pause and alert (Pitfall 12) |
| Texting an opted-out number | Compliance | One outbound chokepoint blocks it; Twilio also returns 21610 |
| Secret leakage in logs or the repo | Information disclosure | `.env` only; no keys in folders (CLAUDE.md); nothing printed |

## Sources

### Primary (HIGH confidence)
- Repo files read this session (paths and line ranges quoted above), `vendor/laravel/ai/src/**`, `vendor/laravel/framework/src/Illuminate/{Queue,Bus,Foundation,Auth,Database}/**`, `vendor/filament/filament/src/{Auth,Panel}/**`.
- Runs this session: `composer show --direct`, `composer check-platform-reqs`, Postgres 16 suite run, tinker probes of `Ai::textProvider()` and the failover fake, the Twilio signature computation.
- docs.typesafe.ai: `/api.md`, `/primitives/choice.md`, `/primitives/noul.md`, `/confidence.md`, `/concepts/state.md`, `/models.md`, `/model-jaggedness/jev-1.13.md`, `/legal.md`, `/llms.txt`.
- OpenRouter: Jev guide (openrouter.ai/docs/guides/community/jev), System One guide (.../typesafe-sdk), Decisions API reference (.../alphadecisions/submit-a-decisions-questions-and-answers-request).
- laravel.com/docs/13.x/ai-sdk (structured output, anonymous agents, failover, testing, classification).
- Twilio: twilio.com/docs/usage/webhooks/webhooks-security, /docs/messaging/guides/webhook-request, /docs/messaging/guides/track-outbound-message-status, /docs/messaging/api/message-resource, /docs/api/errors/21610; help.twilio.com articles 31560110671259 (OptOutType), 360034798533 (Advanced Opt-Out), 223134027 (opt-out keywords), 223133627 (21610); twilio-php `RequestValidator.php`.
- `~/Projects/cartographer` (README, identity, rules, reference/*).

### Secondary (MEDIUM confidence)
- Packagist API for `twilio/sdk`; GitHub releases API for the action versions.

### Tertiary (LOW confidence)
- Items tagged `[ASSUMED]` in the Assumptions Log.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH, nothing new to install and every version read from `composer show`.
- Jev: HIGH on shapes and `state`; MEDIUM on the OpenRouter pin (undocumented) and on timeouts (undocumented).
- laravel/ai: HIGH, source read and behaviours run.
- Twilio: HIGH on the signature (reproduced vector) and opt-out behaviour; MEDIUM on the REST error body.
- Architecture and data model: MEDIUM, these are recommendations inside Claude's discretion.
- Pitfalls: HIGH for those found by running or reading code (1-8), MEDIUM for 12-17.

**Research date:** 2026-10-11
**Valid until:** 2026-10-25 for Jev and OpenRouter (the models page warns that rate limits and model names are moving quickly); 30 days for the rest.

