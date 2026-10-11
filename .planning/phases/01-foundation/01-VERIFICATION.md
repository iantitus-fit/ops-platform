---
phase: 01-foundation
verified: 2026-10-11T15:50:42Z
status: human_needed
score: 6/7 must-haves verified
covered_files:
  - ".planning/phases/01-foundation/01-01-PLAN.md"
  - ".planning/phases/01-foundation/01-01-SUMMARY.md"
  - ".planning/phases/01-foundation/01-02-PLAN.md"
  - ".planning/phases/01-foundation/01-02-SUMMARY.md"
  - ".planning/phases/01-foundation/01-03-PLAN.md"
  - ".planning/phases/01-foundation/01-03-SUMMARY.md"
  - ".planning/phases/01-foundation/01-04-PLAN.md"
  - ".planning/phases/01-foundation/01-04-SUMMARY.md"
  - ".planning/phases/01-foundation/01-05-PLAN.md"
  - ".planning/phases/01-foundation/01-05-SUMMARY.md"
  - ".planning/phases/01-foundation/01-06-PLAN.md"
  - ".planning/phases/01-foundation/01-06-SUMMARY.md"
  - ".planning/phases/01-foundation/01-07-PLAN.md"
  - ".planning/phases/01-foundation/01-07-SUMMARY.md"
  - "app/Actions/RecordInbound.php"
  - "app/Actions/SendOwnerReply.php"
  - "app/Filament/Pages/BusinessFolder.php"
  - "app/Icm/FolderSync.php"
  - "app/Icm/Loader.php"
  - "app/Livewire/ConversationThread.php"
  - "app/Livewire/DevChat.php"
  - "app/Records/RecordsWriter.php"
covered_digest: "v3:sha256:4e7a5ea0ad7037f60ccf2e2888e7f264f37fc1e5874a5e073ceebf389fa53b86"
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Sign in to /admin in a browser, open Conversations and Contacts, read a thread, type and send a reply, close and reopen a conversation (windows ledger #3, plan 01-06 Task 3)"
    expected: "Thread and timeline read clearly, the reply appears in the thread as sent, Close and Reopen change the status, and the notifications read as plain sentences"
    why_human: "Visual layout, form feel and notification wording are not provable by grep or Livewire tests. The executor did not run this walk-through."
  - test: "In the local environment open /dev/chat, text as a customer, watch the thread poll, then open Business Folder and click Sync Now, then open a rendered card under examples/example-hvac/records/ (windows ledger #4, plan 01-07 Task 3)"
    expected: "The text shows on the thread within a poll, Sync Now shows a notification and the page updates, and the card shows the text. Note from the ledger: Dana shares the /dev/chat default number, so the seeded list shows four contacts, not five."
    why_human: "Polling refresh, notification display and page layout need a real browser. The executor did not run this walk-through."
  - test: "Interrupt a changelog write (for example make the folder read-only mid-write, or kill the process) and confirm changelog.md keeps its previous content"
    expected: "changelog.md is intact, not truncated"
    why_human: "Plan 01-02 truth 'changelog.md is written only through File::replace' is tagged verification: backstop. The code uses File::replace (app/Icm/Changelog.php:29) and no test or observed run exercises an interrupted write, so this verifier abstains (insufficient_spec) rather than infer it from presence."
---

# Phase 1: Foundation Verification Report

**Phase Goal:** The folder drives the app, and every way a lead can arrive lands on one timeline that the owner can work from and the owner's own AI can read. No AI replies and no real texting yet.
**Verified:** 2026-10-11T15:50:42Z
**Status:** human_needed
**Re-verification:** No, initial verification

## Summary

The phase goal is achieved in the codebase. All five ROADMAP success criteria and the QUAL-01 phase gate hold, and I confirmed each by running the code in an isolated scratch environment (scratch SQLite file, scratch copy of `examples/example-hvac`, `ICM_PATH` and `DB_DATABASE` overridden), not by reading SUMMARY.md. No truth failed, no artifact is a stub or orphaned, and no key link is broken. The status is `human_needed` for three reasons: the two browser walk-throughs the executors skipped (windows ledger #3 and #4), and one non-inferable truth (atomic changelog write) with no behavioral evidence. There is no BLOCKER. There are 6 WARNINGs, listed below.

The full Pest suite was run once: 264 tests, 264 passed, 1207 assertions. Tests run on in-memory SQLite with a temp copy of the ICM folder, so the run touched nothing real.

## Goal Achievement

### Observable Truths

| #   | Truth | Status | Evidence |
| --- | ----- | ------ | -------- |
| 1 | SC1: `icm:sync` on `examples/example-hvac` compiles a version and makes it active; `icm:status` shows version, file count and go-live time | VERIFIED | Ran on a scratch copy. Before sync, `icm:status` printed "No version is live yet. Run php artisan icm:sync." and exited 1. `icm:sync` printed "Live as v1 (8 files)", exit 0. `icm:status` printed "Active version: v1 / Files: 8 / Live since: 2026-10-11 10:47 (America/Chicago)". A second sync printed "Already active: v1, no changes". |
| 2 | SC2: a broken file never goes live; the message names file and field in plain words; the previous version stays active; the failure is appended to `changelog.md` | VERIFIED | Set `price_policy: maybe`: output "business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)", exit 1, `icm:status` still v1. Deleted `name`: "business/services/ac-repair.md: name is missing, and it is required", exit 1, v1 still live. `changelog.md` held a `Rejected` entry for each with the error bullet and "v1 stays live". Restoring the file synced clean. |
| 3 | SC3: a fake-channel text creates contact, conversation, message, timeline entry; a second text joins the same conversation; `/dev/chat` is 404 outside local; a form with `source=angi` from the same phone lands on the same contact with source and cost | VERIFIED | Posted to the real routes through the HTTP kernel. `POST /webhooks/text/fake` returned 200 `text/xml`. Texts from `(202) 555-0123` and `+12025550123` gave 1 contact (`+12025550123`), 1 conversation. A retried SID recorded nothing new. `POST /leads` with `source=angi` and the same phone returned `{"received":true}`, joined the same contact, and wrote a `form_received` activity with `lead_source_key=angi` and `cost_cents=4500` (read from `lead-sources.md`). `/dev/chat` returned 200 with `APP_ENV=local` and 404 with `production` and `staging`. Unreadable `From` returned 422. A form with no phone or email returned 422 with the plan's sentence. |
| 4 | SC4: owner sees Contacts (list plus timeline) and Conversations (thread with reply box), types a reply that goes out through the fake channel and is stored as sent, and sees Business Folder with active version, files, last sync result, errors and Sync Now | VERIFIED (UI feel needs human, see below) | Authenticated render under `APP_ENV=production`: `/admin`, `/admin/contacts`, `/admin/contacts/1`, `/admin/conversations`, `/admin/conversations/1`, `/admin/business-folder` all 200; a guest on `/admin` got a 302 to `/admin/login`. The Contact page held the texts and the form message, the Conversation page held the thread and a Reply box, and the Business Folder page held "Sync Now" and "Live since". `SendOwnerReply` stored `out / owner / sms / sent / fake / fake-<uuid>`, `FakeTextChannel::sent()` held the send to `+12025550123`, and the reply appeared in the customer card. An empty reply was refused with "Type a message first." and nothing was stored. `BusinessFolder::runSync()` calls the same `FolderSync::run()` as the command. |
| 5 | SC5: every inbound re-renders the customer card and updates `INDEX.md` and `open-leads.md`; two events for one customer produce one card showing both; `records:rebuild` regenerates everything; the sync never reads `records/` | VERIFIED | With `QUEUE_CONNECTION=sync`, the inbound events produced `records/customers/contact-12025550123-1.md` holding both texts and the form (newest first, customer text in labeled blockquotes), plus `INDEX.md` and `open-leads.md`. After I deleted all generated cards, `records:rebuild` printed "Rebuilt 2 customer cards, customers/INDEX.md, open-leads.md and CLAUDE.md" and the files came back. A garbage `records/customers/zz.md` and the rendered cards left `icm:sync` at "Already active: v1, no changes". The unique-job contract (one queued render for two events) is pinned by `RenderCustomerCardJobTest`, which passes. |
| 6 | Phase gate (QUAL-01): every Pest test passes; money is integer cents; nothing reads config from disk except the sync; the two rules are recorded in `.ai/rules` | VERIFIED | Suite: 264/264 passed. Money columns are `unsignedInteger` `acquisition_cost_cents` and `cost_cents`; no float, decimal or double in migrations or models; `MoneyIsIntegerCentsTest` guards it. A grep found no `file_get_contents`, `Yaml::` or `Finder` in `app/` outside `app/Icm/Loader.php`, and no `env()` in `app/`, `routes/`, `database/`. `File::` use is limited to `Changelog` and `RecordsWriter` (writes plus listing filenames to prune orphan cards), matching the plan. `.ai/rules/app.md` holds both rules under separate headings and `index.md` lists all four globs. |
| 7 | Plan 01-02 backstop truth: `changelog.md` is written only through `File::replace`, so an interrupted write leaves the previous changelog intact | UNCERTAIN (insufficient_spec) | `app/Icm/Changelog.php:29` calls `File::replace`, but no test or observed run exercises an interrupted write. A non-inferable truth cannot pass on presence. Routed to human verification. |

**Score:** 6/7 truths verified (the seventh is an abstained non-inferable truth, not a failure). Plan-level truths in plans 01-01 to 01-07 are subsumed by the rows above; the ones that are state or invariant claims were each checked as described below.

### Behavior-Dependent Checks Beyond the Suite

| Invariant | How I exercised it | Result |
| --------- | ------------------ | ------ |
| Activation is one transaction (old row superseded and new row active together) | Added a SQLite trigger on the scratch DB that aborts any insert of an `active` row, edited `CLAUDE.md`, ran `icm:sync` | Insert failed; row 1 stayed `active`. Dropping the trigger and re-syncing superseded it correctly. |
| Sync lock | `SyncTest` holds `Cache::lock('icm:sync')` and asserts the refusal message, exit 1, zero rows | Test exists and passes |
| A rejected candidate is never readable through `ActiveConfig` | Rejected runs above left `icm:status` on v1; `ChangelogTest` pins it | Verified |
| Fake webhook refuses when the driver is not fake | `TEXT_CHANNEL=fake` gave 200; `TEXT_CHANNEL=twilio` gave 404 and recorded nothing | Verified |
| Unknown source never guesses a cost | Form with `source=Mystery` was recorded with `lead_source_key=mystery`, `acquisition_cost_cents=null`, and a note activity | Verified |
| Generated records stay out of git | `git check-ignore` matched `examples/*/changelog.md` and `records/customers/*`; `records/README.md` and `records/CLAUDE.md` are not ignored | Verified |

### Required Artifacts

`verify.artifacts` reported 6 of the 7 plans clean. The flagged items were false negatives, checked by hand:

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| `database/migrations/*_create_icm_versions_table.php` (and conversations, messages) | Migrations with `fingerprint`, partial unique index, provider id unique index | VERIFIED | The tool does not expand globs. Files exist (`2026_10_11_125152_...`, `..._131451_...`, `..._131452_...`); `fingerprint`, `conversations_one_open_per_contact` and `unique(['provider','provider_message_id'])` are present. |
| `tests/Feature/Inbound/FakeTextWebhookTest.php` | Contains `webhooks/text/fake` | VERIFIED | The tests call `route('webhooks.text.fake')`, so the literal path is not in the file. The behavior is covered (15 tests). |
| All other plan artifacts (Loader, FolderSync, ActiveConfig, Validator, PlainWords, Changelog, RecordInbound, TextChannel, PhoneNumber, LeadController, StoreLeadRequest, RenderCustomerCard, CustomerCard, RecordsWriter, SendOwnerReply, ConversationThread, resources, User, DevChat, EnsureLocalEnvironment, BusinessFolder, SyncStatus, guard tests) | Present, substantive, wired | VERIFIED | `verify.artifacts` passed them; sampled files read in full and are real implementations. |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| All plans (01 to 07) | | `verify.key-links` | WIRED | `all_verified: true` for all seven plans. Behavior confirmed end to end by the runs above (webhook to `RecordInbound` to card; form to `ActiveConfig::leadSource`; reply to `TextChannel`; Sync Now to `FolderSync`). |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| -------- | ------------- | ------ | ------------------ | ------ |
| Conversation thread | messages | `Conversation::messages()` query | Yes, showed the posted texts | FLOWING |
| Contact timeline | activities | `Contact::activities()` query | Yes, showed text and form entries | FLOWING |
| Business Folder page | live version, files, last sync | `SyncStatus` and `ActiveConfig` over `icm_versions` | Yes, showed "Live since" after a real sync | FLOWING |
| Customer cards | card markdown | `CustomerCard::render()` from the database | Yes, the file held both texts, the form and the owner reply | FLOWING |
| Cost per lead | `cost_cents` | `ActiveConfig::leadSource()` over the compiled version | Yes, 4500 for angi | FLOWING |

### Behavioral Spot-Checks

All run against a scratch DB and scratch folder; the repository's real database, `examples/example-hvac/changelog.md` and `records/` were not touched.

| Behavior | Command | Result | Status |
| -------- | ------- | ------ | ------ |
| Sync, status, no-op | `php artisan icm:sync` / `icm:status` (scratch env) | As in truth 1 | PASS |
| Reject and changelog | break `ac-repair.md` two ways, sync | As in truth 2 | PASS |
| Inbound text and form | kernel-level POSTs to `/webhooks/text/fake` and `/leads` | As in truth 3 | PASS |
| Records rebuild | `php artisan records:rebuild` | 2 cards, INDEX, open-leads, CLAUDE.md regenerated | PASS |
| Dashboard pages | authenticated kernel GETs under `production` | All 200; guest gets 302 | PASS |
| Owner reply | `SendOwnerReply::handle()` | Stored as sent, card updated | PASS |
| Full suite | `php artisan test --compact` | 264 passed, 1207 assertions | PASS |

### Probe Execution

SKIPPED: the phase declares no probe scripts (`scripts/*/tests/probe-*.sh` does not apply to this Laravel app).

### Requirements Coverage

Every ID in the phase request appears in at least one PLAN's `requirements:` and is `[x]` Complete in REQUIREMENTS.md. No orphaned Phase 1 requirement.

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ----------- | ----------- | ------ | -------- |
| ICM-01 | 01-01 | `icm:sync` and `icm:status` | SATISFIED | Truth 1 |
| ICM-02 | 01-02 | Broken folder rejected, named in plain words, logged | SATISFIED | Truth 2 |
| LEAD-01 | 01-03 | Fake-channel text creates contact, conversation, message, timeline entry; second text joins | SATISFIED | Truth 3 |
| LEAD-02 | 01-04 | `POST /leads` with `source=angi`, cost from `lead-sources.md` | SATISFIED | Truth 3 (cost 4500) |
| LEAD-03 | 01-03, 01-04 | One contact per phone, E.164 via libphonenumber | SATISFIED | Truth 3 (`(202) 555-0123` and `+12025550123` and form `202-555-0123` gave one contact `+12025550123`) |
| LEAD-04 | 01-06 | Owner reply goes out through the fake channel and is stored as sent | SATISFIED | Truth 4 |
| RECS-01 | 01-05 | Cards, INDEX, open-leads, rebuild, sync ignores `records/` | SATISFIED | Truth 5 |
| DASH-01 | 01-06, 01-07 | Contacts, Conversations, Business Folder with Sync Now | SATISFIED (UI feel pending human walk-through) | Truth 4 |
| DASH-02 | 01-07 | `/dev/chat`, 404 outside local | SATISFIED (browser polling pending human walk-through) | Truth 3; `DevChatTest` has 11 tests |
| QUAL-01 | 01-01, 01-07 | Suite green, integer cents, config only via `ActiveConfig`, two Boost rules | SATISFIED | Truth 6 |

Coverage bookkeeping: REQUIREMENTS.md lists exactly these 10 IDs for Phase 1; ROADMAP lists the same 10. No ID is missing from the plans and none is unclaimed.

### Decision Coverage

All trackable CONTEXT.md decisions are honored by shipped artifacts (27 of 27, `check.decision-coverage-verify`, non-blocking).

### Test Quality Audit

| Test area | Linked Req | Active | Skipped | Circular | Assertion Level | Verdict |
| --------- | ---------- | ------ | ------- | -------- | --------------- | ------- |
| `tests/Feature/Icm/*` (6 files) | ICM-01, ICM-02 | 46 | 0 | none found | Value and behavioral | OK |
| `tests/Feature/Inbound/*` (5 files) | LEAD-01 to LEAD-04 | 62 | 0 | none found | Value and behavioral | OK |
| `tests/Feature/Records/*` (5 files) | RECS-01 | 34 | 0 | none found | Value, plus job-contract reflection | OK (job uniqueness is contract-checked with `Queue::fake`, not concurrent) |
| `tests/Feature/Filament/*`, `DevChatTest` | DASH-01, DASH-02 | 37 | 0 | none found | Behavioral | OK |
| `tests/Feature/Quality/*`, `tests/Unit/ArchitectureTest.php` | QUAL-01 | 18 + arch rules | 0 | none found | Value | OK |

**Disabled tests on requirements:** 0 (no `skip(`, `todo(`, `markTestSkipped` or `markTestIncomplete` in `tests/`). **Circular patterns detected:** 0. **Insufficient assertions:** 0.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| (all of `app/`, `routes/`, `database/`, `config/`, `tests/`, `resources/`, `.ai/`) | | `TBD` / `FIXME` / `XXX` / `TODO` / `HACK` / `PLACEHOLDER` | none | No debt markers found |
| `app/Jobs/ProcessInbound.php` | 25 | Empty `handle()` | Info (by design) | The roadmap says "no AI replies" in Phase 1. Docblock names AGNT-02. Windows ledger #1. |
| `app/Channels/TwilioTextChannel.php` | 18, 23 | Methods throw `LogicException` | Info (by design) | The roadmap says "no real texting"; TXT-01 is Phase 2. Windows ledger #2. |

### Human Verification Required

#### 1. Dashboard walk-through (windows ledger #3, plan 01-06 Task 3)

**Test:** Sign in to `/admin` in a browser. Open Conversations and Contacts, read a thread, send a reply, close and reopen a conversation.
**Expected:** The thread and timeline read clearly, the reply shows as sent, Close and Reopen change the status, and notifications are plain sentences.
**Why human:** Layout, form feel and wording cannot be proven by grep or Livewire tests. The executor did not run this.

#### 2. /dev/chat, Business Folder and cards walk-through (windows ledger #4, plan 01-07 Task 3)

**Test:** With `APP_ENV=local`, open `/dev/chat`, text as a customer, watch the thread poll, then click Sync Now on the Business Folder page and open a rendered card.
**Expected:** The text appears within a poll, Sync Now shows a notification and the page updates, and the card shows the text. The seeded list shows four contacts (Dana shares the `/dev/chat` default number).
**Why human:** Polling refresh, notification display and page layout need a real browser. The executor did not run this.

#### 3. Interrupted changelog write (abstained truth 7)

**Test:** Interrupt a changelog write and re-read `changelog.md`.
**Expected:** The previous content is intact.
**Why human:** A `verification: backstop` truth; presence of `File::replace` is not evidence of the interruption behavior.

### Warnings (non-blocking)

None of these fail a must-have truth. Each is an open risk the next phase should triage. 01-REVIEW.md (0 critical, 13 warnings, 6 info, all `open`) and 01-SECURITY.md already track most of them; the first item below is new.

1. **A form does not fill an empty name on a matched contact.** Observed: a text created a nameless contact, then `POST /leads` with `name=Dana Whitfield` matched it by phone. The contact stayed `name=null`, so the card is titled "Contact +12025550123", INDEX shows "(no name)", and the name is only inside the quoted form text. `RecordInbound::matchOrCreateContact` (`app/Actions/RecordInbound.php:165-181`) fills an empty phone and email but not an empty name. CONTEXT D-13 does not specify names, so this is a gap in intent, not a plan violation. Cheap fix: fill an empty name the same way email is filled.
2. **Contact-creation race can drop a form lead (review WR-01).** `contacts.phone_e164` is unique; two simultaneous first contacts from one number make the second `Contact::create` throw, and the catch at `RecordInbound.php:50` rethrows for a form (no provider id), so the visitor gets a 500. A text self-heals when the provider retries. This touches the prohibition "a lead with a usable phone or email must never be discarded". It is a narrow race that tests do not cover, so I classify it as a WARNING, not a failed truth.
3. **A post-commit dispatch failure is never retried (review WR-02).** The dedupe branch at `RecordInbound.php:42-46` treats "recorded" as "dispatched". Harmless in Phase 1 (`records:rebuild` repairs cards), but it becomes lost agent replies once `ProcessInbound` does work in Phase 2.
4. **`Notification::title()` renders customer-derived text as sanitized HTML (review WR-06)** in `ViewConversation.php:38` and `ConversationThread.php:33`. Escape with `e()`.
5. **Unbounded `cost_cents_per_lead` and `price_cents` (review WR-08)** pass validation but overflow Postgres `integer`, breaking lead intake on a deployed database. SQLite hides it. Add a `max` rule before first deploy.
6. **Rejected syncs consume version numbers (review IN-03).** Observed in the atomicity probe: v1 live, two rejections, next activation was v4. The owner sees gaps in version labels.

Deploy gates carried from 01-SECURITY.md (non-blocking for this phase, must close before first deploy and tracked as DEPL-01 and Phase 2 TXT-01): T-01-36 (seeded `test@example.com` login with panel access) and T-01-37 (`channels.text.driver` defaults to `fake`, which keeps the unauthenticated webhook live).

The windows ledger lists four open items (two by-design stubs, two unrun browser walk-throughs). The two walk-throughs are items 1 and 2 above. `/gsd-ship` blocks while the ledger has open items.

### Gaps Summary

No gaps. Nothing the phase promised is missing, stubbed or unwired. The phase is `human_needed`, not `passed`, because the two browser walk-throughs and the interrupted-write check have not been run by anyone. Resolve them (or waive them) and Phase 1 can close.

---

_Verified: 2026-10-11T15:50:42Z_
_Verifier: Claude (gsd-verifier)_
