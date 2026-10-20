# Roadmap: Ops Platform

## Milestones

- ✅ **v1.0 Front Desk** — Phases 1, 2, 02.1 (shipped 2026-10-20)

## Phases

<details>
<summary>✅ v1.0 Front Desk (Phases 1, 2, 02.1) — SHIPPED 2026-10-20</summary>

- [x] Phase 1: Foundation (7/7 plans) — completed 2026-10-11
- [x] Phase 2: Text Front Desk (41/41 plans) — completed 2026-10-13
- [x] Phase 02.1: Faster Answers (INSERTED) (28/28 plans) — completed 2026-10-18

Full details: [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

## Backlog

Parked, not scheduled. SPEC: "Sprint 3 onward is pulled by a real client or a job conversation, one at a time." Promote with `/gsd-review-backlog` when a client pulls one. Backlog requirement IDs are v2 in REQUIREMENTS.md and are not part of v1 coverage.

### Phase 999.1: Quote, Invoice, Paid (BACKLOG)

**Goal:** Legacy's money rules ported test-first, Stripe payment links by text, and a customer approval page. SPEC Sprint 3.
**Requirements:** MONY-01, MONY-02, MONY-03, MONY-04, MONY-05, MONY-06, MONY-07, MONY-08 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.2: Voice (BACKLOG)

**Goal:** A vendor voice agent calling engine tools, with calls and transcripts on the timeline and "they stopped texting, so call". Voice vendor is chosen with the first client that needs phones. SPEC Sprint 4.
**Requirements:** VOIC-01, VOIC-02, VOIC-03 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.3: Automations (BACKLOG)

**Goal:** Sequences from `automations/*.md`: lost-lead check-in at 7 to 10 days with a referral ask, review asks, reactivation. SPEC Sprint 5.
**Requirements:** AUTO-01, AUTO-02, AUTO-03, AUTO-04, AUTO-05 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.4: Settings by Conversation and Connectors (BACKLOG)

**Goal:** An admin agent edits the folder by diff for the owner to approve; QuickBooks, Google Calendar, parsed lead emails (Angi, Thumbtack), Zapier webhooks, and an MCP server for the owner's AI. SPEC Sprint 6.
**Requirements:** SETT-01, SETT-02, SETT-03, CONN-01, CONN-02, CONN-03, CONN-04, CONN-05, CONN-06 (v2)
**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

### Phase 999.5: Owner Loop (BACKLOG)

**Goal:** The owner and the people they trust can act on the front desk from wherever they are: more than one person can approve drafts, a draft can be approved by replying to the alert text, the office hears by email, nothing waits unseen, and the agent steps back without going silent when a person joins the conversation.
**Parked:** moved here unchanged from Phase 02.2 on 2026-10-19 (Ian), pulled by the first client with staff. See PROJECT.md Key Decisions, "The model home is the next milestone". `docs/phase-2.2-owner-input.md` stays as the bank of setup questions. Alerts sent exactly once (WR-03, WR-04, WR-22) and the `gate:export` fix (WR-09) travel with this phase and still need a home in the model home's requirements or the before-first-deploy list.
**Requirements:** TBD (define new IDs in REQUIREMENTS.md when promoted)
**Scope** (split out of Phase 2 on 2026-10-11; renumbered from 02.1 to 02.2 on 2026-10-14 when Faster Answers went first; see `.planning/milestones/v1.0-phases/02-text-front-desk/02-CONTEXT.md` § Deferred Ideas and `docs/phase-2-owner-input.md`):
- Staff users invited by the owner, each with a phone, an email, alert choices and a "can approve drafts" switch; user management and Sync Now stay owner-only (the first role split); sign out everywhere and remove a user.
- Approve by replying to the alert with a short code (K7 sends the draft as written, K7 plus words sends those words); only a registered phone can approve; a code for a replaced draft is refused with "The customer wrote again".
- Email alerts; the "handled" follow-up on the other channel names who acted and how.
- A draft nobody acts on: re-alert after a set time, a backup recipient, and an update to the customer if the wait runs past the holding reply's promise.
- Assist mode: an owner reply stops the agent sending on its own but it keeps drafting suggestions; it returns after a per-business quiet period (replaces Phase 2's owner-reply takeover).
- The after-hours on-call switch per business (urgent service requests such as plain flooding).
- `escalate_to` may name a staff user.
- Complaint alerts can go to a person the owner names (for example an office manager), chosen at setup and changeable later, with the owner as backup when that person cannot be reached; a complaint always reaches someone (Ian, 2026-10-16).
- When alerts route to different people, revisit whether the check-in person also needs an alert for a check-in held as a complaint; in 02.1 one customer text raises one alert, the more serious one, so that text raises only the complaint alert (Ian, 2026-10-18, `02.1-CONTEXT.md` D-34, G-02.1-1).
- The LLM fallback classifier driver on a ZDR route.
- A user's own phone texting the business. Phase 2 treats it as a customer (02-CONTEXT D-53), so an owner who replies to an alert text gets an agent answer as if they were a customer; the reply-code parser has to recognise a registered phone first.
- Carried from Phase 2 review (2026-10-12): owner alerts are sent exactly once, with an atomic send claim and a unique de-duplication key (`02-REVIEW-DISPOSITION.md` WR-03, WR-04; 02.2 rebuilds alerts).
- Carried from the Phase 2 review fix (2026-10-12, `02-REVIEW-DISPOSITION.md` WR-22): a front-desk run that claims texts newer than the one that queued it can still alert the owner twice when it fails. Fix with a claim token stored on the claimed rows, so `ProcessInbound::failed()` reports exactly the burst its run claimed; not with a time window in `ReportFrontDeskFailure`.
- Carried from the Phase 2 review (2026-10-12, `02-REVIEW-DISPOSITION.md` WR-09): `gate:export` reports failed and queued sends as what went out, and `original_text` differs by path; fix before the export feeds threshold tuning.
- Carried from the Phase 2 review (2026-10-12, `02-REVIEW-DISPOSITION.md` WR-17 to WR-19), test hardening: the reply-cap "does not count" test cannot detect most of what it claims (WR-17); `toContain('fi-collapsed')` is always true, so the collapsed customer details are not tested (WR-18); "throws NoActiveVersion from every accessor" omits the accessors Phase 2 added (WR-19).
- Carried from the Phase 2 UI review (`02-UI-REVIEW.md`): a held draft's Send and Discard buttons show a loading state and are disabled while running, and Discard asks for confirmation.

- Carried from the 02.1 Faster Answers discussion (2026-10-14, `.planning/milestones/v1.0-phases/02.1-faster-answers/02.1-CONTEXT.md` § Deferred Ideas): a reply code for a draft with newer customer messages must not send blindly; the "handled" follow-up covers drafts auto-closed as `answered`; the customer update past the holding reply's promise ships together with the owner re-alert; WR-09 must also export the new `answered` resolution and the new fixed-reply kinds.
- The fallback classifier (Ian, 2026-10-15, superseding the 2026-10-14 open question): a runtime fallback when Jev fails, on a route other than OpenRouter and on a ZDR route, and trigger-and-hold only: it can send the safety line, hold or hand off, never pass a send. It gets message text only (02-CONTEXT D-05). Its effect on 02-CONTEXT D-03's "fail closed" is settled in 02.2 discuss. Office admin and backup recipients get the `safety_check_down` alert (02.1-CONTEXT D-50) once 02.2 builds staff users.

**Success Criteria** (what must be TRUE):
  1. The owner invites a user from the dashboard, gives them the approve switch, and every approval, edit and discard records who did it; a user without the switch cannot approve, and only the owner manages users or runs Sync Now.
  2. Replying to an alert text with its code sends the draft (or the replacement words) through the same path as a dashboard approval, recorded on the timeline; a reply with no valid code, or from an unregistered phone, sends nothing and gets a short help text.
  3. When a draft is handled on one channel, the other channel gets a follow-up naming who handled it and how; a draft left untouched past the set time re-alerts and reaches the backup recipient.
  4. After a person replies in a conversation the agent only drafts there until the quiet period passes, then resumes on its own; Take over still stops it fully until handed back.
  5. With the on-call switch on, an after-hours urgent request alerts the on-call person right away; with it off, it waits for opening time.

**Plans:** 0 plans

Plans:
- [ ] TBD (promote with /gsd-review-backlog when ready)

Unscheduled v2 items with no sprint in the SPEC (FOLD-01, FOLD-02, KNOW-01, DEPL-01) are listed in REQUIREMENTS.md and will be attached to a phase when a client or the first deploy pulls them.
