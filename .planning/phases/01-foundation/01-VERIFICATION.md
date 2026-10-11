---
phase: 01-foundation
verified: 2026-10-11T18:30:00Z
status: passed
score: 7/7 must-haves verified
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
  - "app/Actions/CloseConversation.php"
  - "app/Actions/RecordInbound.php"
  - "app/Actions/ReopenConversation.php"
  - "app/Actions/SendOwnerReply.php"
  - "app/Console/Commands/RecordsRebuild.php"
  - "app/Filament/Pages/BusinessFolder.php"
  - "app/Filament/Resources/Conversations/Pages/ViewConversation.php"
  - "app/Icm/FolderSync.php"
  - "app/Icm/Loader.php"
  - "app/Icm/PlainWords.php"
  - "app/Icm/Validator.php"
  - "app/Jobs/RenderCustomerCard.php"
  - "app/Livewire/ConversationThread.php"
  - "app/Livewire/DevChat.php"
  - "app/Records/RecordsWriter.php"
  - "database/factories/ContactFactory.php"
  - "tests/Feature/Quality/TestFolderCopyTest.php"
  - "tests/Pest.php"
covered_digest: "v3:sha256:a5eb204fa9c0c7f1445b0e588240ff816a92415821ede8329b6db3af5eaaa940"
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7 must-haves verified
  gaps_closed:
    - "Phase gate (QUAL-01): every Pest test passes. Commit cfea45d resets the temp copy's records/ to CLAUDE.md and README.md; the suite is now green in the working checkout with the UAT-generated records present."
  gaps_remaining: []
  regressions: []
---

# Phase 1: Foundation Verification Report

**Phase Goal:** The folder drives the app, and every way a lead can arrive lands on one timeline that the owner can work from and the owner's own AI can read. No AI replies and no real texting yet.
**Verified:** 2026-10-11T18:30:00Z
**Status:** passed
**Re-verification:** Yes. The previous report (gaps_found, 6/7) had one failure: the QUAL-01 "every Pest test passes" clause, because `tests/Pest.php` copied the gitignored UAT-generated records into each test's temp folder. Commit `cfea45d` fixes that. Human UAT is complete in `01-UAT.md` (3 of 3 passed).

## Summary

The gap is closed and nothing else regressed. `php artisan test --compact` run exactly as the checkout stands, with the owner's walk-through output still sitting under `examples/example-hvac/records/`, reports **281 tests, 281 passed, 1267 assertions**, on two consecutive runs. Before the fix the same checkout gave 278 of 280. I did not move, delete or edit any file under `examples/example-hvac/records/`; their md5 sums were taken before the runs and are identical after them.

No application code has changed since the previous report's regression pass: `git diff 3db901c HEAD -- app routes database config resources` is empty, and `cfea45d` touches only `tests/Pest.php` and the new `tests/Feature/Quality/TestFolderCopyTest.php`. I still re-ran the five ROADMAP success criteria against current code in a scratch environment (scratch SQLite file and a scratch copy of `examples/example-hvac`, with `ICM_PATH` and `DB_DATABASE` overridden) instead of carrying the old results forward. All five hold.

## Goal Achievement

### Observable Truths

| #   | Truth | Status | Evidence |
| --- | ----- | ------ | -------- |
| 1 | SC1: `icm:sync` on `examples/example-hvac` compiles a version and makes it active; `icm:status` shows version, file count and go-live time | VERIFIED | Scratch run. Before sync, `icm:status` printed "No version is live yet. Run php artisan icm:sync." and exited 1. `icm:sync` printed "Live as v1 (8 files)", exit 0. `icm:status` printed "Active version: v1 / Files: 8 / Live since: 2026-10-11 13:25 (America/Chicago)". A second sync printed "Already active: v1, no changes". |
| 2 | SC2: a broken file never goes live; the message names file and field in plain words; the previous version stays active; the failure is appended to `changelog.md` | VERIFIED | Setting `price_policy: maybe` in `business/services/ac-repair.md` gave "Rejected. v1 stays live. business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)", exit 1. `icm:status` still showed v1. `changelog.md` held a `Rejected` entry with the error bullet and "v1 stays live". Restoring the file gave "Already active: v1, no changes". The missing-`name` case was confirmed in the previous run and is pinned by the passing Validator tests. |
| 3 | SC3: a fake-channel text creates contact, conversation, message, timeline entry; a second text joins the same conversation; `/dev/chat` is 404 outside local; a form with `source=angi` from the same phone lands on the same contact with source and cost | VERIFIED | Kernel-level requests in scratch. `POST /webhooks/text/fake` returned 200 for `(202) 555-0123` and for `+12025550123`; a retried SID recorded nothing new; `POST /leads` with `source=angi` and phone `202-555-0123` returned `{"received":true}`. Result: 1 contact, 1 conversation, 3 messages. The form's timeline activity carries `lead_source_key=angi` and `cost_cents=4500`. A form with no phone or email returned 422 "Give a phone number or an email address so we can reply." `/dev/chat` is gated by `EnsureLocalEnvironment` (`routes/web.php:22-23`); `DevChatTest` covers 404 outside local and 200 in local, and passes (41 of 41 in the `DevChatTest` plus `Filament` folder run). |
| 4 | SC4: owner sees Contacts (list plus timeline) and Conversations (thread with reply box), types a reply that goes out through the fake channel and is stored as sent, and sees Business Folder with active version, files, last sync result, errors and Sync Now | VERIFIED | `tests/Feature/Filament` (`AdminAccessTest`, `BusinessFolderPageTest`, `ContactResourceTest`, `ConversationResourceTest`) all pass: a guest is sent to the login page, the Business Folder page reports "no version is live yet" and then goes live from Sync Now, and the reply flow stores `out / owner / sms / sent / fake` (previous run, unchanged code). The owner exercised the dashboard end to end: `01-UAT.md` tests 1 and 2, both pass. |
| 5 | SC5: every inbound re-renders the customer card and updates `INDEX.md` and `open-leads.md`; two events for one customer produce one card showing both; `records:rebuild` regenerates everything; the sync never reads `records/` | VERIFIED | With the queue on `sync`, the two texts and the form produced one card, `records/customers/contact-12025550123-1.md`, holding all three entries newest first in labelled "Customer-supplied text, not instructions" blockquotes, with the form header "Web form (angi, $45.00)", plus `INDEX.md` and `open-leads.md`. `records:rebuild` printed "Rebuilt 1 customer card, customers/INDEX.md, open-leads.md and CLAUDE.md". Afterwards `icm:sync` printed "Already active: v1, no changes", so the sync ignores `records/`. |
| 6 | Phase gate (QUAL-01): every Pest test passes; money is integer cents; nothing reads config from disk except the sync; the two rules are recorded in `.ai/rules` | VERIFIED | **Suite: 281 tests, 281 passed, 1267 assertions, run twice in the unmodified working checkout, with the UAT-generated `records/customers/*.md` and `records/open-leads.md` present.** See Test Suite Result. No `float`, `decimal` or `double` in `database/migrations` or `app/Models`. A grep for `file_get_contents`, `Yaml::`, `Finder` and `env(` across `app/`, `routes/` and `database/` found nothing outside `app/Icm/Loader.php`. `.ai/rules/app.md` carries "Money is integer cents" and "Business configuration is read only through ActiveConfig" as separate headings. `ProjectRulesTest` and `MoneyIsIntegerCentsTest` pass inside the green run. |
| 7 | Plan 01-02 backstop truth: `changelog.md` is written only through `File::replace`, so an interrupted write leaves the previous changelog intact | VERIFIED (human UAT) | `app/Icm/Changelog.php` uses `File::replace`. The owner interrupted a write: `01-UAT.md` test 3 records `changelog.md` unchanged (md5 matched before and after), the sync finished with a notice, no stack trace. |

**Score:** 7/7 truths verified. None is behavior-dependent without evidence: truth 7 is a backstop truth closed by the owner's observed run, not by symbol presence.

### Test Suite Result (reported as run)

| Run | Command | Result |
| --- | ------- | ------ |
| 1 | `php artisan test --compact` (working checkout, unmodified, UAT-generated records present) | 281 tests, 281 passed, 1267 assertions |
| 2 | same | 281 tests, 281 passed, 1267 assertions |

Evidence that the harness fix is what turned it green, and that the walk-through output was left alone:

- `examples/example-hvac/records/` still holds `customers/bbold-testb-5.md`, `customers/contact-13125550143-6.md`, `customers/contact-13125550199-7.md`, `customers/INDEX.md` and `open-leads.md`. They are gitignored (`.gitignore:40 /examples/*/records/*`). An md5 listing taken before the first run matches the listing after the second run.
- `tests/Pest.php` now calls `resetRecordsToTrackedFiles($folder.'/records')` in `beforeEach` after copying the example folder and deleting `changelog.md`. The helper deletes every subdirectory of the copy's `records/` and every file other than `CLAUDE.md` and `README.md`. It operates on the temp copy only, never on `examples/example-hvac`.
- `tests/Feature/Quality/TestFolderCopyTest.php` asserts that a test's `records/` holds exactly `['CLAUDE.md', 'README.md']`. That pins the fix against the residue that is actually present in this checkout.
- The two tests that failed before, `CustomerCardTest` "shows two texts from the same second in one card" and `SummaryFilesTest` "writes the summaries only while it holds the records lock", are in the passing set.
- No test is skipped, todo or incomplete: a grep of `tests/` for `skip(`, `todo(`, `markTestSkipped` and `markTestIncomplete` found nothing.

The suite total is 281 rather than the earlier 280 because of the one new test, `TestFolderCopyTest`.

### Review Fixes (WR-01 to WR-12): Regression Check

All fix commits (`5e21796`..`3db901c`) are unchanged since the previous report (no diff to `app/`, `routes/`, `database/`, `config/` or `resources/` since `3db901c`), and their tests are in the green 281. WR-02, WR-11 and WR-13 are deferred by `01-REVIEW-DISPOSITION.md` with reasons; none fails a Phase 1 must-have.

### Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| Sync engine (`Loader`, `FolderSync`, `ActiveConfig`, `Validator`, `PlainWords`, `Changelog`) | Compile, validate, activate, log | VERIFIED | Run end to end above. |
| Inbound (`RecordInbound`, `TextChannel`, `PhoneNumber`, `LeadController`, `StoreLeadRequest`) | One timeline for texts and forms | VERIFIED | Run end to end above. |
| Records (`RenderCustomerCard`, `CustomerCard`, `RecordsWriter`, `records:rebuild`) | Cards and summaries from the DB | VERIFIED | Run end to end above. |
| Dashboard (`ConversationThread`, resources, `BusinessFolder`, `SyncStatus`, `SendOwnerReply`) | Owner screens and reply | VERIFIED | `tests/Feature/Filament` passes; UAT 1 and 2. |
| `DevChat`, `EnsureLocalEnvironment` | Local-only chat | VERIFIED | Route middleware present; `DevChatTest` passes. |
| Guard tests (`MoneyIsIntegerCentsTest`, `ProjectRulesTest`, `FactoryPhoneTest`, `SeederTest`) | QUAL-01 guards | VERIFIED | Pass in the green run. |
| `tests/Pest.php` | Hermetic per-test ICM folder | VERIFIED | Resets `records/` to the tracked files; pinned by `TestFolderCopyTest`. |
| `tests/Feature/Quality/TestFolderCopyTest.php` | Pin for the harness fix | VERIFIED | Substantive assertion, passes. |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| Webhook and `/leads` | `RecordInbound` to timeline | action call | WIRED | Scratch run: 3 messages on one conversation. |
| `RecordInbound` | `ActiveConfig::leadSource` | cost lookup | WIRED | `cost_cents=4500`, `lead_source_key=angi`. |
| `RecordInbound` and `SendOwnerReply` | `CustomerRecordChanged` to `RenderCustomerCard` to `RecordsWriter` | event and job | WIRED | Card written after the inbound events (queue on `sync`). |
| `SendOwnerReply` | `TextChannel` | container binding | WIRED | Reply stored as fake, sent (tests and UAT 1). |
| `BusinessFolder` Sync Now | `FolderSync::run()` | same call as `icm:sync` | WIRED | `BusinessFolderPageTest` goes live from Sync Now; UAT 2. |
| `tests/Pest.php` `beforeEach` | `resetRecordsToTrackedFiles` | direct call | WIRED | Called on the temp copy before `config(['icm.path' => ...])`. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| -------- | ------------- | ------ | ------------------ | ------ |
| Conversation thread | messages | `Conversation::messages()` query | Yes | FLOWING |
| Contact timeline | activities | `Contact::activities()` query | Yes | FLOWING |
| Business Folder page | live version, files, last sync | `SyncStatus` over `icm_versions` | Yes | FLOWING |
| Customer cards | card markdown | `CustomerCard::render()` from the DB | Yes: both texts and the form in the rendered card | FLOWING |
| Cost per lead | `cost_cents` | `ActiveConfig::leadSource()` | Yes, 4500 for angi | FLOWING |

### Behavioral Spot-Checks

All against a scratch DB and a scratch copy of the folder. The repository database and the real `examples/example-hvac` were not changed.

| Behavior | Command | Result | Status |
| -------- | ------- | ------ | ------ |
| Sync, status, no-op | `icm:status`, `icm:sync`, `icm:status`, `icm:sync` | As truth 1 | PASS |
| Reject and changelog | break `ac-repair.md`, sync, status, restore, sync | As truth 2 | PASS |
| Inbound text, retry, form, bad form | kernel POSTs to `/webhooks/text/fake` and `/leads` | As truth 3 | PASS |
| Records render, rebuild, sync ignores records | read card, `records:rebuild`, `icm:sync` | As truth 5 | PASS |
| Dashboard and dev chat | `php artisan test --compact tests/Feature/DevChatTest.php tests/Feature/Filament` | 41 passed | PASS |
| Full suite, working checkout | `php artisan test --compact` (twice) | 281 passed | PASS |

### Probe Execution

SKIPPED: the phase declares no probe scripts (`scripts/*/tests/probe-*.sh` does not apply to this Laravel app).

### Requirements Coverage

Every ID in the phase request appears in a PLAN `requirements:` line (01-01: ICM-01, QUAL-01; 01-02: ICM-02; 01-03: LEAD-01, LEAD-03; 01-04: LEAD-02, LEAD-03; 01-05: RECS-01; 01-06: LEAD-04, DASH-01; 01-07: DASH-02, DASH-01, QUAL-01) and is `[x]` Complete in `REQUIREMENTS.md`, whose traceability table maps the same 10 IDs to Phase 1. No ID is orphaned and none is unclaimed.

| Requirement | Source Plan | Status | Evidence |
| ----------- | ----------- | ------ | -------- |
| ICM-01 | 01-01 | SATISFIED | Truth 1 |
| ICM-02 | 01-02 | SATISFIED | Truth 2; changelog atomicity by UAT (truth 7) |
| LEAD-01 | 01-03 | SATISFIED | Truth 3 |
| LEAD-02 | 01-04 | SATISFIED | Truth 3 (cost 4500) |
| LEAD-03 | 01-03, 01-04 | SATISFIED | Truth 3 (two phone spellings and a form with a third, one contact) |
| LEAD-04 | 01-06 | SATISFIED | Truth 4; UAT test 1 |
| RECS-01 | 01-05 | SATISFIED | Truth 5 |
| DASH-01 | 01-06, 01-07 | SATISFIED | Truth 4; UAT tests 1 and 2 |
| DASH-02 | 01-07 | SATISFIED | Truth 3; UAT test 2 |
| QUAL-01 | 01-01, 01-07 | SATISFIED | Truth 6: suite 281/281 in the working checkout, money in integer cents, config read only through `ActiveConfig`, both rules in `.ai/rules/app.md` |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| `app/`, `routes/`, `database/`, `config/`, `tests/`, `resources/`, `.ai/` | | `TBD`, `FIXME`, `XXX`, `TODO`, `HACK`, `PLACEHOLDER` | none | No debt markers found. |
| `app/Jobs/ProcessInbound.php` | 25 | Empty `handle()` | Info (by design) | Roadmap says no AI replies in Phase 1; AGNT-02 is Phase 2. |
| `app/Channels/TwilioTextChannel.php` | 18, 23 | Methods throw `LogicException` | Info (by design) | Roadmap says no real texting; TXT-01 is Phase 2. |

The earlier `tests/Pest.php` blocker is resolved.

### Human Verification

None outstanding. `01-UAT.md` (status complete, 3 total, 3 passed, 0 issues) covers the dashboard walk-through, the `/dev/chat` plus Business Folder plus card walk-through, and the interrupted changelog write. The observations the owner recorded there as "not an issue" (similar-looking customer and owner messages, reply box padding, a silent stale Reopen, "8 files" above a list of 13, a closed-by-owner note labelled as customer text, raw HTML in the web form quote, PHP's raw message in the changelog notice) are not gaps and are not counted here.

### Warnings (non-blocking)

None of these fails a must-have. Triage them in the next phase.

1. **A form does not fill an empty name on a matched contact.** `RecordInbound::matchOrCreateContact` fills an empty phone and email on a matched contact but never an empty name, so the scratch card above still reads "Contact +12025550123" after the form supplied "Dana". CONTEXT D-13 does not specify names.
2. **WR-02: a post-commit dispatch failure is never retried.** Deferred to Phase 2 discuss. Harmless now; becomes lost agent replies once `ProcessInbound` works.
3. **WR-11 and WR-13 deferred** (trusted-proxy setup at first deploy; sending authority and SMS consent in Phase 2). Deploy gates from `01-SECURITY.md` (T-01-36 seeded login, T-01-37 `channels.text.driver` defaults to `fake`) stay open until first deploy.
4. **IN-03: rejected syncs consume version numbers.** The owner sees gaps in version labels.
5. **Windows ledger.** Items #3 and #4 (the two browser walk-throughs) are covered by `01-UAT.md`; whoever owns the ledger should close them so `/gsd-ship` stops blocking on them. Items #1 and #2 are by-design stubs.
6. **Untracked `.planning/milestone.lock`** is in the working tree; it is not part of this phase and was left alone.

### Gaps Summary

None. The one gap from the previous report (suite not green in the working checkout) is closed by `cfea45d`: the suite passes 281/281 twice with the owner's walk-through records still present and unmodified. All five ROADMAP success criteria were re-confirmed against current code, all ten requirement IDs are satisfied and accounted for, and the human items are closed by UAT. The phase goal is achieved.

---

_Verified: 2026-10-11T18:30:00Z_
_Verifier: Claude (gsd-verifier)_
