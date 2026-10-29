# Phase 6: Front Desk and Team Rooms - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-10-27
**Phase:** 06-front-desk-and-team-rooms
**Areas discussed:** Front desk room contents, Team room contents, Alert claimed send unknown, Failed-burst report, Carry-ins (added by Ian)

---

## Area selection

Ian selected all four offered areas and added four carry-ins as free text:

> Carry-ins: (1) RoomOwnershipTest lines 77, 160 and 185 reach their branch only through rules/triage.md; once the front desk room handles triage they collide with criterion 2 and 04 D-16, and line 185 has no unit case for "previous version stays live". How to keep or move them. (2) Whether SendOwnerAlert, the only owner-alert send path, becomes a chokepoint under the chokepoint rule as part of ALRT-01. (3) Splitting owner_alert out of rules/sending.md: settle it or mark it out of scope. (4) IN-11 (failed alert row every 5 minutes with no owner phone): in Phase 6 or not; it touches "nothing from backlog 999.5".

Todo cross-reference: `2026-10-17-phone-design-pass.md` matched (score 0.9).

| Option | Description | Selected |
|--------|-------------|----------|
| Leave it for Phase 11 | Layout work; the wraps change no screen | ✓ |
| Fold it in | Phase 6 takes on the phone layout; breaks "no behavior change" | |

---

## Front desk room contents

**Text slot** (04 D-25 left Phase 6 to decide)

| Option | Description | Selected |
|--------|-------------|----------|
| Slot only, no file | Register a 'text' slot pointing at TextChannel; .env keeps picking the driver; connector file with Twilio/CONN-01 | ✓ |
| Add connectors/text.md | Example connector file, owns none, reads none; for show only | |

**Agent files**

| Option | Description | Selected |
|--------|-------------|----------|
| Front desk handles 'agent' now | One agent today; the phase adding a second splits the type | ✓ |
| Per-room agent ownership now | room: field in agent files; changes format and Validator | |

**Widgets**

| Option | Description | Selected |
|--------|-------------|----------|
| Split by who acts | Front desk: Today's conversations, Paused. Team: Drafts waiting. New leads unclaimed for Phase 7 | ✓ |
| Front desk claims all four | Team has no section in Phase 6 | |
| Front desk three, team Drafts | Front desk also claims New leads; leads room takes it over in Phase 7 | |

---

## Team room contents

**Who acted**

| Option | Description | Selected |
|--------|-------------|----------|
| Team owns owner_alerts and reads resolved_by | One owner per table; no column moves | ✓ (pinned, free text) |
| You decide | Planner sets the table split | |

**User's choice (verbatim):** "Option 1, pinned: each table has one owner, listed in the Room's tables (code only; nothing reads it at run time). Front desk lists messages and conversations, team lists owner_alerts. No column moves. Team's front-matter reads stays folder paths only (rules/sending.md for owner_alert, rules/replies.md for owner_name, per 04 D-12); "reads who resolved each draft" is a plain sentence in the body of rooms/team/CONTEXT.md, never a front-matter entry, because reads takes folder paths and a table name there would be refused. Texts stay the platform's for connectors (04 D-23)."

**Rooms check** (asked with the next area)

| Option | Description | Selected |
|--------|-------------|----------|
| Rooms are done | Move on | |
| More on rooms | Come back to the rooms | |

**User's choice (verbatim):** "One more, then rooms are done: neither wrap registers timeline entry types or model nicknames. Today's activity types (owner_alerted, note, draft_held and the rest) stay platform types drawn by CustomerCard exactly as now, and today's nicknames stay in AppServiceProvider's morph map. Registering them as room entries would change how the customer card prints them (room entries print as customer-supplied unless marked trusted, 04 WR-04), and card tests stay unchanged until Phase 7."

---

## Alert claimed, send unknown

Prior decision surfaced: 02.1 IN-11 close-out, "never a missing alert for a duplicate".

**Stale claim**

| Option | Description | Selected |
|--------|-------------|----------|
| Retry after a wait | Release after the wait; SendDueAlerts re-sends; only duplicate is crash after carrier acceptance | ✓ |
| End as failed, show it | Never re-text; may miss a life-safety alert | |
| Split by urgency | Retry urgent, end others as failed | |

**Wait**

| Option | Description | Selected |
|--------|-------------|----------|
| You decide | Planner picks, above the channel's timeout | |
| 2 minutes | ~3 minutes late at most | ✓ (with free text) |
| 5 minutes | Matches the undispatched sweep | |

**User's choice (verbatim):** "2 minutes, measured from the claim, and the claim is taken immediately before TextChannel::send, never when the alert is raised. The wait has to stay well above the text channel's 10-second request timeout (TwilioTextChannel line 59) so a slow send that is still running is never released. Worst case, an urgent alert lost to a crash goes out about 3 minutes late (2-minute wait plus the every-minute SendDueAlerts sweep)."

**Business-wide alerts**

| Option | Description | Selected |
|--------|-------------|----------|
| Send claim only, no key | SafetyCheckStatus's claim keeps deciding | ✓ |
| Key them too | Moves the caller's rule into the alert table | |

---

## Failed-burst report

Finding surfaced: a run that dies before claiming leaves its text unhandled with no alert; SweepUndispatched re-queues only never-dispatched texts.

**Report**

| Option | Description | Selected |
|--------|-------------|----------|
| One alert, same words | Newest text in the claimed burst; only which texts count changes | ✓ |
| One alert, with a count | "3 texts"; wording and tests change | |
| One per claimed text | Several texts for one failure | |

**Once**

| Option | Description | Selected |
|--------|-------------|----------|
| Once per claim token | Token is the report's de-dup key; whichever path first | ✓ |
| Only fix which texts | Two-notes race stays open | |

**No claim**

| Option | Description | Selected |
|--------|-------------|----------|
| Nothing; open item | Outside criterion 5; BLUEPRINT open item | ✓ (with trigger) |
| Report the job's own text | New behavior beyond ALRT-02 | |

**User's choice (verbatim):** "Option 1, with a trigger: nothing in Phase 6. BLUEPRINT open item under customer communication, decided before the first client goes live: a front-desk run that fails before claiming any text leaves that text unanswered and unreported until the customer texts again (ProcessInbound has $tries = 1 and SweepUndispatched only re-queues texts never dispatched). Today's failed() only reported this case by accident, on an older answered text (the WR-22 wrong-text report), and never for a conversation's first text."

**Token**

| Option | Description | Selected |
|--------|-------------|----------|
| Researcher decides | Any source the failed() copy can find, written by the claiming update | ✓ (with conditions) |
| Carried in the job | Chosen at dispatch and serialized | |

**User's choice (verbatim):** "Researcher decides, with these conditions: (1) the token is stored on every claimed row by the same conditional update that claims it; (2) the failed() copy can find it; (3) it is unique per run, so failed() never matches rows an earlier or later run claimed; (4) it behaves the same on the sync queue the tests use (phpunit.xml QUEUE_CONNECTION=sync) and the database queue production uses, and at least one test serializes the job and calls failed() on a fresh unserialized copy, so a sync-only pass cannot hide a production miss. Proven on SQLite and Postgres."

---

## Carry-ins

**Owner tests** (RoomOwnershipTest :77, :160, :185)

| Option | Description | Selected |
|--------|-------------|----------|
| Prep plan before the wrap | Re-point where reachable, else 05 D-23 unit-first; line 185 gets its own case | ✓ (as explicit amendment) |
| Unit tests only, in the prep plan | All three to unit cases; feature-level proof lost | |

**User's choice (verbatim):** "Option 1, recorded as an explicit amendment: Ian approves, like 05 D-23, a second allowed test change for the front desk room beside the example folder's file count, for these three tests only (RoomOwnershipTest lines 77, 160 and 185). It amends 04 D-16 and ROOM-06's "the one allowed test change" for these three only. The prep plan lands before the front desk wrap and passes on today's code; the wrap then changes only the file count, and every other test both wraps touch stays unchanged. Line 185 gets its own "previous version stays live" unit case, written and passing before its feature test goes. The context commit updates ROOM-06 in REQUIREMENTS.md to name these three tests."

**Chokepoint**

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, the claim is its check | Named chokepoint; checks-passed record; only-path test | |
| No, not an agent action | Rule does not apply; record it | ✓ (plus one guard) |

**User's choice (verbatim):** "Option 2, plus one guard from option 1: owner alerts are code, not an agent action (BLUEPRINT Team: "Model role. None; alert routing is code"), so the chokepoint rule does not apply and no checks-passed record is added. Record this so no later plan reopens it; the rule applies the day a model decides or writes an owner alert. As part of ALRT-01, a test proves SendOwnerAlert is the only code that texts an owner alert (TextChannel used only by OutboundSender and SendOwnerAlert), because exactly-once depends on every alert going through its claim."

Verified in discuss: `TextChannel::send()` is called only at `OutboundSender.php:274` and `SendOwnerAlert.php:50`.

**owner_alert split**

| Option | Description | Selected |
|--------|-------------|----------|
| Out of scope, stays deferred | Stays under 04 D-13 | ✓ (with trigger) |
| Settle the shape now, build later | Decide target file now | |

**User's choice (verbatim):** "Option 1: stays deferred under 04 D-13; team keeps reading owner_alert from rules/sending.md. Trigger: decided in the 999.5 Owner Loop discuss, when alert settings grow past owner_alert (staff approvers, email alerts, re-alerts and a backup recipient). BLUEPRINT records the deferral with that trigger."

**IN-11**

| Option | Description | Selected |
|--------|-------------|----------|
| Not in Phase 6 | Onboarding fix; decided before the first client goes live | ✓ |
| In Phase 6, under ALRT-01 | Changes SafetyCheckStatus retries | |

---

## Close

| Option | Description | Selected |
|--------|-------------|----------|
| I'm ready for context | Write CONTEXT and log; build order per criterion 1 | ✓ (with commit-step changes) |
| Explore more gray areas | Phase split; migration of existing duplicate rows | |

Ian's commit-step instructions: record free-text answers as written with Affects lines; list the locked decision sets; add three canonical refs; amend ROOM-06 only in REQUIREMENTS.md; add BLUEPRINT lines a to n; stop before committing and show the decision list and `git diff --stat`.

## Claude's Discretion

- Never-left-out reason clauses; CONTEXT.md bodies and reads lists.
- Send claim and de-dup key schema (NULLs are distinct in unique indexes on both databases).
- How the unique-index migration treats existing duplicate dev rows.
- Boot test needs for rooms claiming non-RoomSection widgets.
- Placeholder wording.

## Deferred Ideas

- Failed-run alert saying how many texts went unanswered (wording pass).
- A run that fails before claiming any text (before the first client goes live).
- Splitting owner_alert out of rules/sending.md (999.5 Owner Loop discuss).
- IN-11 (before the first client goes live).
- A per-room agent type (phase of the second agent).
- A text-channel connector file (Twilio go-live with CONN-01).
