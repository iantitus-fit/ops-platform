# Phase 8: Schedule and Booking - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md; this log preserves the alternatives considered.

**Date:** 2026-11-01 to 2026-11-02
**Phase:** 08-schedule-and-booking
**Areas discussed:** The booking link, Page and availability, Appointment states and owner actions, Appointment and project, Confirmation and changes, Reminders, Booked alert, Room shape and outside owner, Card/screen/status/address, Accessibility walk

Before the discuss, Ian checked the read-in against the files and gave three notes: BOOK-06 is Phase 9, not Phase 8; 02 D-20 as built sends a reason not under `right_away` at once while the business is open and holds it to the next opening only after hours (`RaiseOwnerAlert::dueAt()`); and two wording fixes (PROJECT.md Frontend "Phase 3's" to "Phase 8's"; the ROADMAP Phase 7 note on the appointment table marked settled by 07 D-09) fold into the context commit. He asked that every reminder design be named as an amendment to 02.1 D-49, every link option say whether it rides on the existing `scheduling` right, and every alert-key option say whether it leaves 06 D-08 unchanged.

Ian added two areas to the eight offered: Confirmation and changes, and Owner actions and appointment states.

Todo cross-reference: `2026-10-17-phone-design-pass.md` matched on keywords. Ian chose "Leave it with Phase 11".

---

## The booking link

### Who decides the booking link goes out?

| Option | Description | Selected |
|--------|-------------|----------|
| Code adds it to a scheduling reply | Rides on the existing `scheduling` right; combined text, an existing D-49 kind | ✓ |
| Code sends it after intake | Fixed reply; needs a new right in `rules/sending.md` | |
| Both | Scheduling reply plus a code send after intake | |
| Owner sends it by hand only | No agent right; does not meet BOOK-02 alone | |

**User's choice:** Code adds it to a scheduling reply.
**Notes:** Code leaves the link off while the contact has an upcoming booked appointment.

### Which service does the link book?

| Option | Description | Selected |
|--------|-------------|----------|
| The intake's service, else no link | The page never asks which service | ✓ |
| Intake's service, else customer picks | Page offers book_online services when none is known | |
| Customer always picks on the page | Link carries no service | |

**User's choice:** First option, tightened.
**Notes:** The reply's own `service_slug` first; else the conversation's intake service only when exactly one; else no link (never guess, like the price rule). The page re-checks the service at open and at tap; a missing service or `book_online: false` shows no times and a plain line.

### Where does the owner-written link line live?

| Option | Description | Selected |
|--------|-------------|----------|
| A booking file the room owns | New file type in `rooms/schedule_and_booking/` | ✓ |
| `rules/replies.md` | Beside the other fixed lines | |
| `business/hours.md` | Beside `booking_lead_minutes` | |

**User's choice:** A booking file the room owns.
**Notes:** `booking_lead_minutes` stays in `hours.md`. A live room with no booking file sends no link; the section names the file (04 D-21).

### Link only, or also times in the thread?

| Option | Description | Selected |
|--------|-------------|----------|
| Link only; thread times deferred | Reopens nothing | ✓ |
| Code lists a few times beside the link | Reopens "No new front-desk depth" | |
| Book by text reply | Agent action; new D-49 kind; reopens no-new-depth | |

**User's choice:** Link only.
**Notes:** Thread times go to a BLUEPRINT open item after v2.0, with a named chokepoint and a rules file when they come.

### Does the example agent file get a line offering to book after intake?

| Option | Description | Selected |
|--------|-------------|----------|
| Add the line | Folder wording; plans name forced test changes; UAT Dev chat check | |
| Add it, and code checks the label | Same line, no code fallback | ✓ |
| Leave the agent file alone | Link only when the customer asks | |

**User's choice:** Option 2 with option 1's terms.
**Notes:** Ian raised this follow-up. Claude's reading, which Ian accepted: folder wording is the business's own and reopens nothing.

---

## Page and availability

### Start times or arrival windows?

| Option | Description | Selected |
|--------|-------------|----------|
| Start times on a step | Step in the booking file | ✓ |
| Arrival windows | Windows in the booking file | |
| The booking file chooses | Both built | |

**User's choice:** Start times on a step (given in text after the question was declined by mistake).
**Notes:** Appointments store start and end; overlap compares spans. Arrival windows deferred next to crews.

### Capacity, and must a job fit inside open hours?

| Option | Description | Selected |
|--------|-------------|----------|
| One at a time, ends by closing | One van | ✓ |
| A count in the booking file, ends by closing | A number, no crews | |
| One at a time, may run past closing | Only the start inside hours | |

**User's choice:** One at a time, ends by closing.
**Notes:** Written as a count check with capacity fixed at 1; re-checked under a lock at tap on both databases. Capacity above one deferred.

### How far ahead, and the layout?

| Option | Description | Selected |
|--------|-------------|----------|
| A set number of days, by day | Days in the booking file; grouped by day | ✓ |
| First N open times only | Fewest taps | |
| A day picker, then that day's times | Two steps | |

**User's choice:** A set number of days, by day.
**Notes:** Phone layout to `/gsd-ui-phase 8`, held to criterion 6. An empty range shows one plain line.

### What is the link?

| Option | Description | Selected |
|--------|-------------|----------|
| Stored random token | Row per link; revocable | ✓ |
| Laravel temporary signed URL | Nothing stored | |
| Signed URL plus a stored row | Both | |

**User's choice:** Stored random token.
**Notes:** Hash only stored. Row records the carrying message, which later gives the booked alert its subject. BLUEPRINT "On my way" idea to assume this mechanism.

### Link life and what the page shows

| Option | Description | Selected |
|--------|-------------|----------|
| Expiry in days; minimal page | Business and service only | ✓ |
| Same, but greet by first name | Name shown | |
| Single use, no expiry | Works until used | |

**User's choice:** Expiry in days; minimal page.

### Follow-up (Ian)

**Notes:** Drive buffer in the booking file (example 30 minutes), added to the end of every span in the overlap check; a job still only has to end by closing. Times in the business zone, named once; `throttle:leads` precedent; fall-back and spring-forward tests.

---

## Appointment states and owner actions

### Which states?

| Option | Description | Selected |
|--------|-------------|----------|
| Booked, cancelled, done | Done by the owner | ✓ |
| Booked, cancelled, done, no-show | No-show now | |
| Booked and cancelled only | Done waits | |

**User's choice:** Booked, cancelled, done.
**Notes:** Done only by the owner and only after the start; does not close the project. No-show deferred to owner intelligence.

### Which owner actions? (multi-select)

| Option | Description | Selected |
|--------|-------------|----------|
| Cancel | | ✓ |
| Mark done | | ✓ |
| Book by hand | | ✓ |
| Move | | |

**User's choice:** Cancel, Mark done, Book by hand.
**Notes:** Book by hand may go outside hours or inside the lead time, never past, never overlapping; finds or opens the project like a page booking. Move is cancel and book again; a Move action deferred.

### How are appointment writes guarded?

| Option | Description | Selected |
|--------|-------------|----------|
| Named actions, one architecture test | Not an agent action | ✓ |
| Same, and the link counts as an approval | Tap as approval | |

**User's choice:** Option 1.
**Notes:** Ian caught that the option said "four" but listed three. The page booking records its link row as provenance, not approval. Reminder state in its own table if not on appointments.

### Where does the owner act?

| Option | Description | Selected |
|--------|-------------|----------|
| Contact screen; schedule screen links to it | One set of actions | ✓ |
| Both screens | | |
| Schedule screen only | | |

**User's choice:** Contact screen.
**Notes:** Ian added Block time on the schedule screen, and adding a contact by hand (reversing the Phase 1 `canCreate()` rule, his call), with texts off until the customer agrees.

### A hand-added contact with texts off texts the business

| Option | Description | Selected |
|--------|-------------|----------|
| Their own text turns texts on | Same footing as every texter | ✓ |
| Stays off until the owner turns it on | No 911 line by text | |
| Life-safety line only, else off | | |

**User's choice:** Their own text turns texts on, except a STOP text (07 D-23 test). Attorney-list line widened to every contact.

### Follow-up (Ian)

**Notes:** Cancel, Mark done and Block time confirm in a dialog (T-07-26); Remove block is its own action; cancel final; done not undone (undo deferred to 999.1); block appearance to the UI step.

---

## Appointment and project

### Booking for a contact with no open project

| Option | Description | Selected |
|--------|-------------|----------|
| Open a new project | Never reopen a closed one | ✓ |
| Reopen the newest closed one if recent | | |

**User's choice:** Open a new project. A hand-added contact with no conversation gets a null conversation link (07 D-06).

### Two or more open projects

| Option | Description | Selected |
|--------|-------------|----------|
| Link's conversation's project, else only one, else new | Never guess | ✓ |
| Link's conversation's project, else newest open | | |
| Fix the project when the link is made | | |

**User's choice:** Option 1, tightened so no step guesses.

### Does a project close on its own?

| Option | Description | Selected |
|--------|-------------|----------|
| No; the owner closes by hand | 07 D-05 stands | |
| Close when all appointments are done | Reopens 07 D-05 | |
| Offer to close on Mark done | Owner decides | ✓ |

**User's choice:** Offer to close on Mark done, unchecked by default.

### Follow-up (Ian)

**Notes:** CloseProject refuses while a visit is still ahead; nothing is booked onto a closed project.

---

## Confirmation and changes

### Confirmation text after a page booking?

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, an owner-written line | Amends 02.1 D-49 | ✓ |
| No text; the page is the confirmation | | |
| Only when the owner books by hand | | |

**User's choice:** Yes. Ian's explicit call to amend 02.1 D-49 with one kind shared with reminders.

### What the new kind requires

| Option | Description | Selected |
|--------|-------------|----------|
| The appointment, code words, nothing unread | Waits over an unread text | ✓ |
| The appointment and code words only | | |
| Treat it as a fixed reply | Old reading | |

**User's choice:** Option 1. The wait ends when the visit is past or cancelled, then the send drops. Any confirmation or reminder that does not go out writes a timeline note and raises no alert.

### Where the 02 D-41 check sits outside a front-desk run

| Option | Description | Selected |
|--------|-------------|----------|
| In OutboundSender, every Automated send | Moves to the chokepoint | ✓ |
| In OutboundSender, the new kind only | | |
| In each caller | | |

**User's choice:** Option 1. The switch is offered on any contact who never texted. ProcessInbound keeps the per-conversation rule.

### Customer change or cancel?

| Option | Description | Selected |
|--------|-------------|----------|
| No self-service; text the business | | ✓ |
| Cancel on the link page | | |
| Cancel and rebook on the page | | |

**User's choice:** No self-service, plus a change-request alert for a booked contact's scheduling text. Self-service cancel deferred with its cancel-before window and late-cancel policy.

### When the owner acts, is the customer texted?

| Option | Description | Selected |
|--------|-------------|----------|
| Book by hand confirms; cancel does not | | ✓ |
| Both confirm | | |
| Neither | | |

**User's choice:** Option 1. The cancel dialog says the customer is not texted.

---

## Reminders

### How many and when?

| Option | Description | Selected |
|--------|-------------|----------|
| A list of lead times in the booking file | | ✓ |
| One, the day before at a set time | | |
| Day before and morning of, fixed | | |

**User's choice:** A list of lead times.

### Send hours

| Option | Description | Selected |
|--------|-------------|----------|
| Window in the booking file; move earlier | | ✓ |
| Window; move later if still before the visit | | |
| Window; skip outside it | | |

**User's choice:** Move earlier.
**Notes:** Ian corrected Claude's example: a 24-hour reminder for an 8 AM visit goes on time; the moving case is a 2-hour reminder due at 6 AM. Both window ends count as inside; coinciding reminders send once.

### Which conversation?

| Option | Description | Selected |
|--------|-------------|----------|
| Open one, else start one | Second way to start a conversation | ✓ |
| Open one, else skip with a note | | |
| Reopen the booking's conversation | | |

**User's choice:** Open one, else start one. Paused keeps 02 D-24; a started conversation opens no project and changes no status.

### Exactly once

| Option | Description | Selected |
|--------|-------------|----------|
| Never twice; a lost one stays lost | | |
| Never missing; a duplicate is possible | Owner-alert rule | ✓ |
| Retry only if the carrier never saw it | | |

**User's choice:** The owner-alert rule (06 D-06, D-07). A released row re-checks every condition.

### Confirmation in the same table?

| Option | Description | Selected |
|--------|-------------|----------|
| Same table and claim; next area | | ✓ |
| Same table and claim; more questions | | |
| Inline send, no retry | | |

**User's choice:** Same table. A page booking's confirmation goes at once at any hour; a booking by hand's inside the window, else at the next opening.

---

## Booked alert

### Booked alert key

| Option | Description | Selected |
|--------|-------------|----------|
| The link's message; one booking per link | 06 D-08 unchanged | ✓ |
| Add the appointment to the key | Widens 06 D-08 | |
| Link's message, links reusable after cancel | Fails 02.1 IN-11 | |

**User's choice:** Option 1. Only a page booking alerts. The change-request alert keys on its inbound text; 06 D-08 stands for both.

### Change-request alert under the one-alert rule

| Option | Description | Selected |
|--------|-------------|----------|
| Lowest: only when nothing else alerts | | |
| Above check-in and draft waiting | | ✓ |
| Always, alongside any other | Reopens the rule | |

**User's choice:** Above check-in and draft waiting ("Sure, Wednesday works" reasoning).

### Booked alert timing in the example

| Option | Description | Selected |
|--------|-------------|----------|
| Not listed | | ✓ |
| Listed under right_away | | |

**User's choice:** Not listed. The page's lead time counts from the later of now and the next opening.

---

## Room shape and outside owner

| Question | Selected |
|----------|----------|
| Connector slot: None (CONTEXT.md says so) / A slot with a fake driver | None |
| A connector owns schedule: The sync refuses the pair / Room live, booking off | The sync refuses the pair (room stays optional) |
| Room off with visits ahead: Sync refuses / Off with a warning / Reminders continue | Sync refuses, and names `book_online: false` as the way to stop new bookings |
| `needs` field: Not added / Add it now, unused | Not added |

**Notes:** With the room live and no booking file, nothing customer-facing goes out; booking by hand and Block time still work.

---

## Card, screen, status, address

| Question | Selected |
|----------|----------|
| Card: Next visit line + under each project / Next visit line + timeline / Timeline only | Next visit line + under each project; UAT Dev chat check |
| Schedule screen: List page by day + room section / Week grid / Section only | List page by day + room section; calendar grid deferred to Phase 11 |
| Customer status: Leave to 999.1 / On booking / On Mark done | Leave to 999.1 |
| Service and address: Service on appointment, no address / Owner-typed address / Service on project | Service on appointment; free-text intake question for an address; owner visit note |

**Notes:** The owner adds or edits the visit note on any booked appointment through its own named action; a page booking carries no note.

---

## Accessibility walk

| Question | Selected |
|----------|----------|
| Walks: Page live now, owner screens' VoiceOver to Phase 11 / Everything live at Phase 8 UAT | Page live now; owner screens' VoiceOver to Phase 11 |

**Notes:** Axe and Tab reach at desktop and 375 in CI; zoom and tap targets in the live walk; 07 IN-08, IN-09, IN-11 land here; 08-UI-SPEC.md covers the page in full and the owner screens in short; privacy notice and accessibility statement get a place with placeholder wording.

---

## Before writing

Claude raised a conflict between two of Ian's answers (area 3: texts off "refuses every text"; area 5: owner replies keep today's rules) and a gap (a hand-added contact's starting status).

| Question | Selected |
|----------|----------|
| What the switch refuses: Automated only / Every text / Every text for hand-added only | Automated only; the switch is named for what the platform sends on its own; the reply box informs and does not block |
| Hand-added status: Lead, like an inbound / Lead, kept out of New leads | Lead, like an inbound |

Ian also confirmed two items: the change-request reason joins the one-alert order inside `RunFrontDesk` (a front-desk code change), and REQUIREMENTS BOOK-04's derived note points at the 08 decisions.

---

## Claude's Discretion

- Names of the booking file, its keys, the new reasons, the `ProjectActor` value, the message kind, actions, tables and columns.
- PlainWords wording for every customer and owner line, dialog and sync refusal.
- How "never two" and the tap-time re-check are enforced on both databases; token generation and hashing; rate limit numbers; sweep cadence.
- Test layout; the room's `CONTEXT.md` body and `reads` list.

## Deferred Ideas

- Times offered or booked in the text thread (after v2.0).
- Arrival windows, capacity above one, crews.
- No-show state.
- Move action.
- Self-service cancel with a cancel-before window and late-cancel policy; the fee waits for 999.1 and the attorney list.
- Undo for done (999.1).
- Calendar grid with month and day views (Phase 11).
- Structured service address and history by address.
