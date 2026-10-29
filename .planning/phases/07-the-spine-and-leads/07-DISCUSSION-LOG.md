# Phase 7: The Spine and Leads - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md; this log preserves the alternatives considered.

**Date:** 2026-10-29
**Phase:** 07-the-spine-and-leads
**Areas discussed:** Second job and when a project opens, What a project holds, The word and where it shows, The leads room's edges

Before the discuss, Ian corrected two points from the read-in: `RecordInbound` is the shared inbound path, not front desk code, so the default for LEAD-05 is an after-commit hook beside it and editing it is its own decision; and the extra test changes follow from 04 D-16, 06 D-03 and criterion 1, so they are a wording gap in criterion 4, not a conflict with a locked decision.

Todo cross-reference: `2026-10-17-phone-design-pass.md` matched on keywords. Ian chose "Leave for Phase 11".

---

## Second job and when a project opens

### What counts as the new lead that opens a project?

| Option | Description | Selected |
|--------|-------------|----------|
| Only a new contact | A project opens only when the inbound created the contact | ✓ |
| A new contact, or any new conversation | Also when a known contact starts a fresh conversation after a close | |
| A new contact, or any web form | Every form opens one, even for a known contact | |

**User's choice:** Only a new contact, with two conditions.
**Notes:** (a) The trigger is `matched_by` new; a form matching a known contact goes to the second-job question (02 D-43). (b) The open runs after commit beside `RecordInbound`, is retried if it fails, does nothing if the contact already has a project, and never rolls back or delays the inbound.

### How does a second project come about?

| Option | Description | Selected |
|--------|-------------|----------|
| Owner opens it by hand | A "New Job" action on the contact screen; nothing asked or alerted | ✓ |
| Owner is asked on the dashboard | A "New job or same job?" list with two buttons | |
| One project per contact in v2.0 | A second job hangs on the same project | |

**User's choice:** Owner opens it by hand.

### Does a project have a status, and does it ever close?

| Option | Description | Selected |
|--------|-------------|----------|
| Open or closed, owner closes | Two states, closed and reopened by hand | ✓ |
| No status yet | Phase 8 or later adds states | |
| A short fixed stage list | New, booked, done, lost | |

**User's choice:** Open or closed.
**Notes:** More than one open project per contact is allowed; no one-open rule. Two notes deferred to Phase 8: booking opens a project when the contact has none open; a project may close on its own when its work is done, and Phase 8 decides which of two open projects an appointment goes on. Pipeline stages stay not yet decided.

### What does "its conversation" mean?

| Option | Description | Selected |
|--------|-------------|----------|
| The conversation it began in | One nullable link per project | ✓ |
| Every conversation while it is open | Links to each conversation during its life | |
| Owner can re-point it | Option 1 plus re-pointing | |

**User's choice:** The conversation it began in.
**Notes:** The link lives on the projects table; no column on conversations or messages (06 D-04); texts are never sorted into projects.

---

## What a project holds

### What else does a project carry?

| Option | Description | Selected |
|--------|-------------|----------|
| Who or what opened it | How it began | ✓ |
| A short name the owner can edit | Empty by default, falls back to "<word> N" | ✓ |
| The service it is for | A services slug, maybe from intake answers | |
| A service address | Where the work is | |

**User's choice:** Options 1 and 2.
**Notes:** Record who opened and who closed. The name is one line, length-bounded, empty by default, and reaches the front desk only through the card. The service is deferred (no intake answer yet when it opens, slugs go stale, a listener on a front-desk table). The address is deferred to Phase 8 (nothing holds an address today).

### Service history by address

| Option | Description | Selected |
|--------|-------------|----------|
| Person only now; idea moves to Phase 8 | History hangs off the contact; idea re-pointed with the DEC-03 correction | ✓ |
| Settle the rule now, build later | Decide person and address now | |

**User's choice:** Person only now; idea moves to Phase 8.

### Where do the appointments table and link first appear?

| Option | Description | Selected |
|--------|-------------|----------|
| Phase 8, link on the appointment | Projects need no column; ready by shape | ✓ |
| Phase 7, empty table now | Create the table and link now | |

**User's choice:** Phase 8.
**Notes:** Verification records criterion 3 as met by shape, with Phase 8 criterion 2 as the proof; a verifier gap is answered by that pointer, never by building the table in Phase 7. PROJ-01 is not split.

### Does the chokepoint rule apply?

| Option | Description | Selected |
|--------|-------------|----------|
| Not an agent action; one way in anyway | 06 D-09 reasoning, plus an architecture test | ✓ |
| Not an agent action; no extra test | Convention only | |
| Treat it as a chokepoint now | Full chokepoint pattern | |

**User's choice:** Option 1, with every write named: open, close, reopen and rename, each recording who did it; the architecture test allows those four and nothing else.

---

## The word and where it shows

### What may the profile.md field hold?

| Option | Description | Selected |
|--------|-------------|----------|
| A fixed list in code | Each word with its plural in code | ✓ |
| Any word, plural by rule | English plural rules | |
| Any word, owner writes the plural | Two values in one field | |

**User's choice:** A fixed list: Job, Project, Matter, Case, Work order, Engagement.
**Notes:** Matched without regard to case; refusal names the file and field and lists the choices; `docs/SPEC.md` lists the same words, tested (04 D-22 pattern); the example business sets Job.

### How does a project show on the customer card?

| Option | Description | Selected |
|--------|-------------|----------|
| A section plus timeline entries | Section above the timeline, entries for each change | ✓ |
| A section only | No timeline entries | |
| Timeline entries only | No section | |

**User's choice:** Option 1's shape, drawn by the platform, not the leads room.
**Notes:** Platform types like `owner_alerted` (06 D-05); they stay when the leads room is off. Left out when the contact has no project. The name is quoted and neutralized; CardInjectionTest gains that case.

### What does the dashboard show?

| Option | Description | Selected |
|--------|-------------|----------|
| Claim New leads; section lists open ones | Pointer claim plus a room section; WR-09 guard | ✓ |
| Claim New leads and add a column | A project column on New leads | |
| A projects screen of its own | A full screen with filters | |

**User's choice:** Option 1.
**Notes:** FrontDeskBootTest.php:90 changes (named under criterion 4). Row links reachable by keyboard; "opened" shows a date. The section hides when the room is off. A business-wide project view is deferred to Phase 9.

### Does open-leads.md change?

| Option | Description | Selected |
|--------|-------------|----------|
| Unchanged; writes_records: none | Projects reach the owner's AI through cards | ✓ |
| Add a project column to open-leads.md | Changes SummaryFilesTest | |
| A new open-projects.md | Pulls records work from Phase 9 | |

**User's choice:** Unchanged.

### Added by Ian: contact status

**Notes:** Opening, closing, reopening or renaming a project never changes the contact's status; what makes a contact a Customer goes to Phase 8 or 999.1.

---

## The leads room's edges

### When rooms/leads/ is absent, does a new lead open a project?

| Option | Description | Selected |
|--------|-------------|----------|
| No automatic open; room stays optional | The room acts only while in the folder | ✓ |
| Leads can never be left out | The sync requires rooms/leads/ | |
| The open is platform code | Opens regardless of the room | |

**User's choice:** Option 1.
**Notes:** The live check runs when the open runs, not when the inbound arrived; turning the room on later backfills nothing.

### The needs field

| Option | Description | Selected |
|--------|-------------|----------|
| Not added; note closed | No room needs another's records in v2.0 | |
| Not added; carried to Phase 8 | Phase 8 discuss closes it | ✓ |
| Add it now | Build the field with nothing using it | |

**User's choice:** Not added; carried to Phase 8.

### Which room owns the projects table?

| Option | Description | Selected |
|--------|-------------|----------|
| No room; platform-owned | Like contacts and activities | ✓ |
| The leads room | Leads lists projects in its tables | |

**User's choice:** No room; platform-owned.

### Does the leads room point at the web form?

| Option | Description | Selected |
|--------|-------------|----------|
| Points at it, nothing moves | A code pointer under the 04 D-19 wrap rule | |
| Leave it unclaimed | Stays shared and unowned | ✓ |

**User's choice:** Leave it unclaimed.
**Notes:** The form must keep taking leads when the room is off (02 D-41). Marketing's CONTEXT.md sentence is updated in a plan, with its tests.

### Criterion 4 wording

| Option | Description | Selected |
|--------|-------------|----------|
| Reword criterion 4 to name them | ROADMAP lists the allowed kinds | ✓ |
| Keep the criterion; allow them in CONTEXT | A 06 D-14 style amendment | |

**User's choice:** Reword, allowing exactly: card tests showing a project; `exampleFolderFileCount()`; FrontDeskBootTest.php:90; profile.md fixtures given only the new field line; marketing CONTEXT.md text tests changed only to the new sentence. Any other change stops and goes to Ian.

### Added by Ian: the room's reads, and no UI-SPEC

**Notes:** The open-projects section shows the lead source by name for an automatic open, and who opened it for a hand-opened project. Nothing in Phase 7 uses offers. No UI-SPEC: the UI plan-gate returns block false, the screens are stock Filament, styling waits for Phase 11, and the leads section gets the axe check in its boot test.

---

## Claude's Discretion

- Observer, listener or after-commit job for the open; its retry; how "never two" is enforced.
- The profile field's key name, the plurals, the name's length bound, "<word> N" numbering.
- Placeholder wording for the card, timeline entries, actions, room section and refusal.
- The body and `reads` of `rooms/leads/CONTEXT.md`; test layout.

## Deferred Ideas

- Phase 8: booking opens a project when the contact has none open; a project may close on its own; which of two open projects an appointment goes on; the `needs` field; a project's service and service address; service history by address.
- Phase 9: a business-wide project view and records file.
- Phase 8 or 999.1: what makes a contact a Customer.
- Before the first client: an automatic open lost in a crash between commit and dispatch, covered by the same sweep as BLUEPRINT section 6's item.
