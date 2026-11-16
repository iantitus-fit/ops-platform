# Phase 9: Owner Intelligence - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in 09-CONTEXT.md; this log keeps the alternatives considered.

**Date:** 2026-11-13 to 2026-11-14
**Phase:** 09-owner-intelligence
**Areas discussed:** Minutes: start and stop; Minutes: where shown; today.md and the nightly rebuild; gate:export (GATE-05); Room contract; Carry-ins from 07 and 08. Also asked: BOOK-06/RECS-02 scope; the UI contract (not selected as an area).

Todo cross-check: `2026-10-17-phone-design-pass.md` matched (0.9) and was not folded, by Ian's instruction before the discuss.

---

## Minutes: start and stop

| Option | Description | Selected |
|--------|-------------|----------|
| First inbound message | The contact's earliest inbound row, text or form; BOOK-06's words | ✓ |
| contact created_at | The card's "First contact"; wrong for hand-added contacts | |
| Project's first inbound | Per job, from the project's conversation | |

**Ian's choice:** First inbound message.
**Notes (added at the end of the area):** an inbound that is only a keyword the platform handles (STOP, START, HELP and the rest) never starts the clock; the next real message does, the same way a first STOP opens no project (07 D-23).

| Option | Description | Selected |
|--------|-------------|----------|
| First booking ever | Earliest booked_at, any project; a later cancel doesn't erase it | ✓ |
| First booking still standing | Ignores bookings later cancelled | |
| Per project | One number per job | |

**Ian's choice:** First booking ever, by when it was booked: the earliest booked_at across the contact's visits, whichever project and whatever the visit date; page or by hand both count; a later cancel doesn't erase it; later jobs add no number.

| Option | Description | Selected |
|--------|-------------|----------|
| Worked out on read | Computed from messages and appointments; nothing stored | ✓ |
| Stored at booking | Book writes the minutes once; needs a backfill | |

**Ian's choice:** Worked out on read. The summary computes in one query for all contacts, never one per contact, and a test counts the queries.

| Option | Description | Selected |
|--------|-------------|----------|
| Wall-clock, none if no prior inbound | Plain elapsed time; no number when the booking came first | ✓ |
| Business-hours minutes | Only opening hours count | |
| Both | Wall-clock headline plus business-hours | |

**Ian's choice:** Wall-clock, none if no prior inbound.

---

## Minutes: where shown

| Option | Description | Selected |
|--------|-------------|----------|
| Card line + contact screen | Also reaches the front desk's model (02 D-36) | |
| Card for records only | Two card variants | |
| Contact screen only | Card unchanged | ✓ |

**Ian's choice:** Contact screen only for each contact's number; the customer card stays unchanged, so what the front desk's model reads does not change. The owner's AI gets the business-wide number from a records file.

| Option | Description | Selected |
|--------|-------------|----------|
| Median + count, last 30 days | One headline | ✓ |
| Median by lead source | Headline plus a table | |
| Average + list | Average plus recent bookings | |

**Ian's choice:** Median + count, last 30 days; the same line goes to a records file; the 30 days are counted in the business's time zone.

| Option | Description | Selected |
|--------|-------------|----------|
| Room section; all minutes follow the room | Section and contact line hide with the room off | ✓ |
| Room section; contact line always shows | Split across room and platform | |
| Platform widget | Claimed as a pointer | |

**Ian's choice:** Room section; all minutes follow the room.

| Option | Description | Selected |
|--------|-------------|----------|
| Plain minutes, hours past 90 | "42 minutes", "3 hours 10 minutes", "2 days 4 hours" | ✓ |
| Always minutes | "1,390 minutes" | |
| Always hours and minutes | "0 h 42 min" | |

**Ian's choice:** Option 1 with singular forms and "under a minute" for zero; label "Time to booked" instead of "Minutes to booked", since the value is not always minutes; the summary's median uses the same format.

---

## today.md and the nightly rebuild

| Option | Description | Selected |
|--------|-------------|----------|
| Rebuild is platform; room optional | Rebuild always runs; room owns only its new files | ✓ |
| Room owns all records; never left out | 04 D-09 applies | |
| Room owns rebuild; optional | Rebuild stops with the room off | |

**Ian's choice:** Rebuild is platform; room optional. (Named conflict: 04 D-08 and D-09 against DEC-03 and 08 IN-06.)

| Option | Description | Selected |
|--------|-------------|----------|
| Just after midnight, business zone | Same run turns today.md over | ✓ |
| Fixed quiet hour (3 AM) | Needs a second midnight job | |
| Midnight UTC | Evening for a Central Time business | |

**Ian's choice:** Just after midnight, business zone.

| Option | Description | Selected |
|--------|-------------|----------|
| Also redraw when a visit starts | A sweep keeps the records card minutes-fresh | |
| Nightly is enough | As IN-06's disposition says | ✓ |

**Ian's choice:** Nightly is enough.

| Option | Description | Selected |
|--------|-------------|----------|
| Booked visits + blocks | Matches the schedule screen | |
| All of today's visits with state | Booked, done, cancelled, plus blocks | ✓ |
| Booked visits only | No blocks | |

**Ian's choice:** All of today's visits with their state plus blocks, earliest first, business zone; empty day "Nothing booked today."; acceptance examples recorded as D-13 (the Dana, Marcus, Lee day and six reader questions), turned into tests before any code.

| Option | Description | Selected |
|--------|-------------|----------|
| Time, what, customer card, state | No phone, note or job label | ✓ |
| Add the job label | Plus "Job 2" | |
| Add the phone | Plus the phone | |

**Ian's choice:** Time, what, customer card, state.

| Option | Description | Selected |
|--------|-------------|----------|
| Remove on off; plain line | Delete files when the room is off; a line when the schedule room is off | ✓ |
| Leave the last copy | Files go stale silently | |
| Remove on off; "Nothing booked today." | Hides why | |

**Ian's choice:** Option 1, with the line reading "This business keeps its schedule outside this system."
**Notes (at the end of the area):** today.md's heading names the date it covers ("Friday, November 13"), so a reader can tell when a missed nightly run left it showing yesterday.

---

## gate:export (GATE-05)

| Option | Description | Selected |
|--------|-------------|----------|
| final_text only if sent/delivered; add status | Status on every line | ✓ |
| Leave them out | Drop queued and failed rows | |
| final_text null, no status | WR-09's fix exactly | |

**Ian's choice:** final_text only if sent or delivered; add status.

| Option | Description | Selected |
|--------|-------------|----------|
| The model's own words, from the verdict | gate.inputs.agent_reply on every path | ✓ |
| Composed text before any edit | Needs a stored pre-edit version | |

**Ian's choice:** The model's own words, from the verdict.

| Option | Description | Selected |
|--------|-------------|----------|
| Every text with a stored verdict | Adds holding, handoff, life-safety too | |
| agent_reply + 02.1's six kinds | 02.1 D-02's words | ✓ |
| Everything sent | Booking texts too | |

**Ian's choice:** agent_reply plus 02.1's six kinds. Holding, handoff and life-safety stay out and the docblock's sentence stays: one line is one decision, and a holding reply follows a held draft whose line already carries that verdict. Each line gains kind and whether model words were in. If Phase 10's calibration needs the handoff or life-safety cases, it adds them there.

| Option | Description | Selected |
|--------|-------------|----------|
| answered_by_message_id on the draft | From meta.answered_by | ✓ |
| Both directions | Also on the code_answer line | |
| No link | Match by time | |

**Ian's choice:** answered_by_message_id on the draft.

---

## Room contract

| Option | Description | Selected |
|--------|-------------|----------|
| New time-to-booked.md | Its own records file | ✓ |
| Start this-week.md | SPEC's numbers file | |
| A line in today.md | No second file | |

**Ian's choice:** New time-to-booked.md.

| Option | Description | Selected |
|--------|-------------|----------|
| CONTEXT.md only + example records | Fictional records tracked in the example folder | |
| A new owned settings file | A setting nobody asked for | |

**Ian's choice (free text):** CONTEXT.md only, owns: none, like leads and team. No example files under records/: generated records are never committed (the .gitignore rule, 01 D-23), and un-ignoring them would reopen that. The room's CONTEXT.md shows a short sample of each file it writes, and the acceptance tests render the real files. exampleFolderFileCount() changes only for the new CONTEXT.md.

| Option | Description | Selected |
|--------|-------------|----------|
| connector: none | 999.4 adds the slot | ✓ |
| Declare an mcp slot now | Against the 2026-10-21 rule | |

**Ian's choice:** connector: none.

| Option | Description | Selected |
|--------|-------------|----------|
| The time-to-booked line only | No repeat of the schedule section | ✓ |
| Line + today at a glance | Overlaps the schedule section | |

**Ian's choice:** The line only, with the empty line "No new customers booked in the last 30 days."

---

## Carry-ins from 07 and 08

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, exactly as deferred | New wording, three named test changes, BLUEPRINT line 133 | ✓ |
| Yes, and rewrite stored meta | Plus a migration | |
| Defer again | To Phase 10 or 11 | |

**Ian's choice:** Yes, exactly as deferred (08 IN-13).

| Option | Description | Selected |
|--------|-------------|----------|
| [deferred], trigger: reporting decided | Not in Phase 9's criteria | ✓ |
| Projects records file only | Adds a criterion | |
| Both, here | Two new criteria | |

**Ian's choice:** [deferred] until per-project reporting is decided.

---

## Scope and UI contract (asked at the end)

| Option | Description | Selected |
|--------|-------------|----------|
| Confirmed as discussed | BOOK-06 and RECS-02 as above | ✓ |
| Narrow one | Trim one | |

| Option | Description | Selected |
|--------|-------------|----------|
| Short stock-Filament UI-SPEC | The 07 D-21 pattern | ✓ |
| Full /gsd-ui-phase 9 | A full design contract | |

---

## Claude's Discretion

Accepted by Ian at each area's "Next area":
- The median of an even count is the mean of the two middle values, rounded to the whole minute.
- today.md is redrawn after each 08 D-15 named action commits, queued and locked like writeSummaries(), plus the nightly turnover; a missing folder is reported as records:rebuild does today.
- gate:export: --without-text turns new texts into lengths; fields added, none removed; stays a platform command; tests first.
- The room: key owner_intelligence, tables none, reads folder paths only, the router names its files only while live, off the never-left-out list.

Left to the planner (09-CONTEXT.md, Claude's Discretion): names, the summary line's exact words, the redraw wiring, the exact minute past midnight, the room's CONTEXT.md body, the test layout.

## Deferred Ideas

- A business-wide project view and projects records file (07's Phase 9 line), until per-project reporting is decided.
- Handoff and life-safety cases in gate:export, if Phase 10's calibration needs them.
- The owner intelligence room's connector slot with the MCP server (999.4).
- who-owes-us.md and this-week.md stay with 999.1.
