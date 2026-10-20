---
phase: 01-foundation
plan: 06
subsystem: dashboard
tags: [filament, livewire, owner-reply, conversations, contacts, panel-access, pest]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: "Plan 01-03: contacts, conversations, messages, activities, TextChannel and FakeTextChannel, CustomerRecordChanged"
  - phase: 01-foundation
    provides: "Plan 01-05: customer cards that label a ReplySent activity as the owner's reply, and ActiveConfig::displayTimezone()"
provides:
  - "SendOwnerReply: the owner's reply goes out through TextChannel and is stored sent only after the channel returns an id; failures are stored failed with the error"
  - "CloseConversation and ReopenConversation (Reopen refuses when the contact already has an open conversation)"
  - "ConversationThread Livewire component (thread, reply box, 5 second poll) embedded in the Filament conversation view"
  - "Filament Conversations resource (list, view, Close and Reopen header actions) and Contacts resource (list, view with timeline)"
  - "Contact::timeline() and User implements FilamentUser (panel opens outside local)"
affects: [01-07 business folder page and dev chat, phase-02 takeover and draft review, phase-02 Twilio driver]

# Actuals (#2632)
actuals:
  tokens: 14569
  tasks: 3
  commits: 7

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Action classes refuse with ValidationException keyed body; channel failures and blocked reopens are DomainException caught by the caller and shown as a danger notification"
    - "One sentence source for the reply box: SendOwnerReply::whyCannotReply() feeds both the refusal and the replacement text in the view"
    - "Message stored queued, set sent only after TextChannel::send returns; a failure keeps the failed message and a timeline note"
    - "Filament resources keep only index and view pages; canCreate() is false"
    - "Pest helpers are global functions: give each test-file helper a name no other test file uses"

key-files:
  created:
    - app/Actions/SendOwnerReply.php
    - app/Actions/CloseConversation.php
    - app/Actions/ReopenConversation.php
    - app/Livewire/ConversationThread.php
    - resources/views/livewire/conversation-thread.blade.php
    - app/Filament/Resources/Conversations/ConversationResource.php
    - app/Filament/Resources/Conversations/Pages/ListConversations.php
    - app/Filament/Resources/Conversations/Pages/ViewConversation.php
    - app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
    - app/Filament/Resources/Conversations/Tables/ConversationsTable.php
    - app/Filament/Resources/Contacts/ContactResource.php
    - app/Filament/Resources/Contacts/Pages/ListContacts.php
    - app/Filament/Resources/Contacts/Pages/ViewContact.php
    - app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
    - app/Filament/Resources/Contacts/Tables/ContactsTable.php
    - tests/Feature/Inbound/OwnerReplyTest.php
    - tests/Feature/Filament/ConversationResourceTest.php
    - tests/Feature/Filament/ContactResourceTest.php
    - tests/Feature/Filament/AdminAccessTest.php
  modified:
    - app/Models/User.php
    - app/Models/Contact.php
    - docs/sprints/sprint-0.md

key-decisions:
  - "Reply refusals check the conversation state first (closed, then no phone) and the body second (empty, then over 1,600 characters), so the owner is told the reason that applies to the whole thread before one about their words"
  - "A failed send leaves the owner's text in the reply box for another try, and does not touch the contact's last activity or the conversation's last message time; the failure is a note on the timeline"
  - "ContactResource titles an unnamed lead by phone, then email, so the view page is never blank"
  - "The contacts default sort puts rows with no last activity after the others on any database, because Postgres sorts NULLs first on descending order"

patterns-established:
  - "Embed a Livewire component in a Filament infolist with Filament Schemas Livewire::make(Class::class, fn (Model $record) => [...])"
  - "Test Filament pages with Livewire::test() and Filament's table and action helpers; no pest-plugin-livewire needed"

requirements-completed: [LEAD-04, DASH-01]

# Metrics
duration: 8min
completed: 2026-10-11
plan_head_before: 389e42bf9c159311aaec64904ac988321391a180
plan_head_after: 8537d6a3ee3f446e363cbe68ad568a9cfcbf29e2
commits: 7
status: complete

coverage:
  - id: D1
    description: "LEAD-04: a reply typed in the conversation view goes out through the fake text channel and is stored as outbound, owner, sms, sent, provider fake with a fake- id, with a reply_sent activity, and the customer record change is announced"
    requirement: LEAD-04
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php#it sends the owner reply through the text channel and stores it as sent"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php#it announces that the customer record changed when the owner replies"
        status: pass
    human_judgment: false
  - id: D2
    description: "LEAD-04 edge cases: empty and whitespace-only replies refused, 1,601 characters refused and 1,600 accepted (counted in characters, not bytes), emoji and accents stored and sent unchanged"
    requirement: LEAD-04
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php (refuses an empty or whitespace-only reply; refuses a reply over 1,600 characters and sends one of exactly 1,600; stores and sends emoji and accents unchanged)"
        status: pass
    human_judgment: false
  - id: D3
    description: "A reply the text channel fails to send is stored failed with the error in meta, a timeline note is added, the owner sees a danger notification, and no message is ever stored as sent"
    requirement: LEAD-04
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php#it stores a reply the text channel could not send as failed and never as sent"
        status: pass
    human_judgment: false
  - id: D4
    description: "D-17: a contact with no phone gets a disabled reply box and the email sentence, and sending is refused with nothing stored; a closed conversation shows the closed sentence and refuses the reply, even when it was closed after the page loaded"
    requirement: LEAD-04
    verification:
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php (shows a disabled reply box and the email when the contact has no phone; says so when the contact has neither a phone nor an email; replaces the reply box on a closed conversation and refuses the reply; refuses a reply when the conversation was closed after the page loaded)"
        status: pass
    human_judgment: false
  - id: D5
    description: "D-27: Close and Reopen header actions; Reopen is hidden and refuses with the named sentence when the contact already has an open conversation, so a contact never has two open conversations through the dashboard"
    requirement: DASH-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationResourceTest.php (closes an open conversation and notes it on the timeline; reopens a closed conversation when the contact has no other open one; hides the reopen action when the contact already has another open conversation; names a contact without a name by phone when reopening is refused; announces that the customer record changed when a conversation is closed or reopened)"
        status: pass
    human_judgment: false
  - id: D6
    description: "DASH-01 Conversations: list shows customer, phone, status and last message, newest first; view page shows the customer and the thread with a reply box, loading over HTTP for a signed-in user"
    requirement: DASH-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ConversationResourceTest.php#it lists conversations with the most recent message first"
        status: pass
      - kind: integration
        ref: "tests/Feature/Inbound/OwnerReplyTest.php#it loads the conversation view for a signed-in owner and shows the thread"
        status: pass
    human_judgment: false
  - id: D7
    description: "DASH-01 Contacts: list with name, phone, source, cost per lead in dollars, status and last activity, ordered by last activity then id descending, searchable by phone, with an empty state; view page with details, first-touch source and the full timeline newest first with each touch's source and cost"
    requirement: DASH-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ContactResourceTest.php (7 tests)"
        status: pass
    human_judgment: false
  - id: D8
    description: "D-24: a signed-in user gets 200 on /admin, /admin/contacts, /admin/conversations and the view pages outside the local environment; a guest is redirected to the login page"
    requirement: DASH-01
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/AdminAccessTest.php (2 tests)"
        status: pass
    human_judgment: false
  - id: D9
    description: "Customer-written text is shown escaped everywhere in the dashboard (no raw Blade echo in the thread view; timeline text shown as plain text)"
    verification:
      - kind: integration
        ref: "tests/Feature/Filament/ContactResourceTest.php#it shows customer-written text as plain text, never as HTML"
        status: pass
      - kind: other
        ref: "! grep -q '{!!' resources/views/livewire/conversation-thread.blade.php"
        status: pass
    human_judgment: false
  - id: D10
    description: "The dashboard looks right and the whole flow works in a browser: sign in, browse contacts and timelines, send a reply, close and reopen"
    verification: []
    human_judgment: true
    rationale: "Layout and styling of the thread and reply box use inline styles and Filament components with no browser check; the plan's human-check was not run by the executor"

duration: 8min
completed: 2026-10-11
---

# Phase 1 Plan 06: Dashboard Conversations and Contacts Summary

**Filament Conversations (list, thread with a reply box that texts through TextChannel, Close and Reopen) and Contacts (list plus newest-first timeline), with panel access for the owner outside local and a reply that is never stored as sent unless the channel accepted it.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-10-11T13:49:05Z
- **Completed:** 2026-10-11T13:57:15Z (task work; summary and state updates follow)
- **Tasks:** 3 (1 tracer, 2 TDD)
- **Files modified:** 22 (19 created, 3 modified)

## Accomplishments

- The owner opens a conversation at `/admin/conversations/{id}`, reads the thread (oldest first, same-second messages in arrival order, author labelled Customer, Owner or Agent) and sends a reply. `SendOwnerReply` stores it queued, calls `TextChannel::send`, and only then marks it sent with the provider id, adds a `reply_sent` activity, updates the conversation and contact times and announces `CustomerRecordChanged`. The customer card from plan 01-05 picks the reply up through that activity.
- Every refusal is a sentence under the box and stores nothing: empty, over 1,600 characters (counted with `mb_strlen`, so 1,600 accented characters pass), closed thread, and a contact with no phone (the email is offered, D-17). The reply box is replaced by that sentence with a disabled form.
- A channel failure leaves the message failed with the error in `meta`, a `Reply not sent: ...` note on the timeline, and a red notification; the owner's words stay in the box.
- Close and Reopen (D-27): Close asks for confirmation; Reopen only shows, and only succeeds, when the contact has no other open conversation, so the one-open-conversation rule holds from the dashboard too.
- Contacts list (name, phone, source badge, cost per lead as dollars from integer cents, status, last activity) is searchable by phone and ordered by last activity then id. The contact view shows details and a `RepeatableEntry` timeline with each touch's source and cost.
- `User implements FilamentUser`: a signed-in user reaches the panel outside `local`; HTTP tests prove 200 for signed-in users and a redirect to login for guests.

## Task Commits

1. **Task 1: tracer, the owner replies from the conversation view**
   - RED `f3a19e7` (test), GREEN `4a7fafd` (feat)
2. **Task 2: reply rules, failed sends, close and reopen**
   - RED `30def68` (test), GREEN `4efbb5f` (feat)
3. **Task 3: contacts list and timeline view, panel access**
   - RED `1b337d3` (test), GREEN `1a2e6e6` (feat)
4. **Fix after full-suite run:** `8537d6a` (fix)

**Plan metadata:** the docs commit that follows this file (summary, state, roadmap, requirements, windows ledger).

No REFACTOR commits: nothing needed cleanup after GREEN.

## TDD notes

- RED evidence was validated with `gsd_run check tdd-red-evidence` (JUnit from `pest --log-junit`) and returned `RED_EVIDENCE_OK` for the named target of each task: Livewire component `App\Livewire\ConversationThread` not found (Task 1); no body error for a 1,601 character reply, and no `close` action on the view page (Task 2); `Contact::timeline()` undefined and the Contacts pages missing (Task 3).
- Semantic assessment: each target executed and failed on the planned behaviour, not on a syntax, import or fixture fault.
- Some tests were characterization tests that passed at RED because earlier tasks already provided the behaviour: the emoji and accents test and the list-order test (Task 2), and the guest-redirect test (Task 3). The AdminAccess signed-in test failed at RED because the Contacts page did not exist, not because of a 403: D-24's `FilamentUser` change had already shipped in the Task 1 tracer, where the conversation view page needed it.
- Task 2's failed-send test was changed after its RED commit (see Deviation 2); it was changed in the GREEN commit.

## Files Created/Modified

See `key-files` above. `docs/sprints/sprint-0.md` gained 6 Walk-through log lines (Filament resources and pages, tables and infolists, header actions with confirmation, Livewire components embedded in a Filament schema, throwing refusals from an action, `FilamentUser` and panel access, testing Filament with `Livewire::test()`).

## Decisions Made

- State-first refusal order (closed, no phone), then body checks (empty, too long).
- A failed send keeps the owner's text in the box and does not move last-activity times.
- `ContactResource::getRecordTitle()` falls back to phone, then email, then `(no name)`.
- The contacts default sort is written so rows with no last activity sort last on Postgres as well as SQLite.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test helper name collided with another test file**
- **Found during:** final verification (whole suite)
- **Issue:** `timelineEntry()` in `ContactResourceTest.php` was already declared as a global Pest helper in `CustomerCardTest.php`. Running either folder alone passed; the whole suite aborted with "Cannot redeclare function".
- **Fix:** renamed the helper `contactTimelineEntry()`.
- **Files modified:** `tests/Feature/Filament/ContactResourceTest.php`
- **Verification:** `php artisan test --compact` passes in full (215 tests).
- **Committed in:** `8537d6a`

**2. [Test choice] The failed-send test uses a failing stub channel, not the Twilio driver**
- **Found during:** Task 2
- **Issue:** The plan's behaviour used `config(['channels.text.driver' => 'twilio'])` because the Twilio stub's `send()` throws. That stub is replaced in Phase 2 (TXT-01), at which point the test would stop failing for the intended reason.
- **Fix:** the test binds a small `FakeTextChannel` subclass whose `send()` throws `RuntimeException('Carrier rejected the number')` and asserts the exact notification title, the stored `meta`, the timeline note and the dispatched event.
- **Files modified:** `tests/Feature/Inbound/OwnerReplyTest.php`
- **Committed in:** `4efbb5f`

---

**Total deviations:** 1 auto-fixed (1 bug), 1 test-design choice
**Impact on plan:** None on behaviour. The plan's truths are all covered.

## Issues Encountered

- Filament notifications are consumed by Filament's own Livewire component, so reading the session directly in a test finds nothing. `assertNotified(Notification::make()->title(...)->danger())` is the working check.
- Filament's compiled stylesheet has no `whitespace-pre-line` utility, so the thread body uses an inline `white-space: pre-line` alongside the class named in the plan.

## Authentication Gates

None.

## Known Stubs

None. (`.planning/WINDOWS.md` gained one `unrun-verify` entry for the human-check below.)

## Threat Flags

None beyond the plan's threat model. T-01-27 (XSS) is covered by the escaped Blade echo, the grep gate, and the escaped-timeline test. T-01-28 (reply status) is covered by the failed-send test. T-01-29 (Livewire state) is covered by the model-bound property plus a refresh of the conversation inside `SendOwnerReply`, tested with a conversation closed after the page loaded. T-01-30 is covered by the Reopen refusal tests; the unique-index fallback inside `ReopenConversation` (a concurrent open) is not exercised by a test.

## User Setup Required

None - no external service configuration required.

## Verification

- `php artisan test --compact tests/Feature/Filament tests/Feature/Inbound/OwnerReplyTest.php`: 28 passed.
- `php artisan test --compact tests/Feature/Icm tests/Feature/Inbound tests/Feature/Records tests/Feature/Quality tests/Unit/ArchitectureTest.php`: 198 passed, no regression in earlier plans.
- `php artisan test --compact` (whole suite): 215 passed, 900 assertions.
- `vendor/bin/pint --dirty --format agent`: passed.
- Acceptance criteria for all three tasks re-run and passing (User has `implements FilamentUser`, only List and View pages exist, no pest-plugin-livewire in `composer.json`, no raw echo in the thread view, `divideBy: 100` and `last_activity_at` in `ContactsTable`, walk-through lines added).
- **Not run:** Task 3's `<human-check>` (sign in at `/admin` in a browser with `composer run dev`, check navigation, the contact timeline, a reply, and Close then Reopen). Under the default `end-of-phase` mode it is left for phase UAT. Pending since the layout of the thread and reply box has only been rendered in tests.
- Per CLAUDE.md, Ian should run the complete suite once: `php artisan test --compact`.

## Next Phase Readiness

- Plan 01-07 can reuse `Conversation::messages()` ordering and the `livewire.conversation-thread` message markup for `/dev/chat`, and add the Business Folder page beside these resources.
- Phase 2 takeover and draft review build on `SendOwnerReply` and the `Draft` message status.

---
*Phase: 01-foundation*
*Completed: 2026-10-11*

## Self-Check: PASSED

- All 19 created files exist on disk; the 3 modified files exist.
- Commits found on the branch: `f3a19e7`, `4a7fafd`, `30def68`, `4efbb5f`, `1b337d3`, `1a2e6e6`, `8537d6a`.
- Measured commit count for the plan: 7 (`389e42b..8537d6a`), plus the docs commit.
- Evaluation scope for plan 01-06 resolved from the plan's own commits.
