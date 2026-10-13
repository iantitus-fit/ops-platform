# Phase 2: Text Front Desk - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-10-11
**Phase:** 02-text-front-desk
**Areas discussed:** Scope line, Alerts and approvals, How the gate decides, Takeover and the phone page, Phase 1 carry-overs, STOP and the agent file
**Input:** `docs/phase-2-owner-input.md` (Ian's answers and recommendations)

---

## Scope line

| Option | Description | Selected |
|--------|-------------|----------|
| Split into 2 and 2.1 | Phase 2 keeps the core plus customer-safety items; 2.1 (the owner loop) takes users, text approval, email, re-alerts | ✓ |
| All in Phase 2 | One big phase, probably 12 or more plans | |
| Move the alert work to Phase 3 | Join the 'owner alerts' Phase 3 already plans | |

**User's choice:** Split into 2 and 2.1.

| Option | Description | Selected |
|--------|-------------|----------|
| Phase 2, behind an interface | Code-first layer, Classifier interface, Jev HTTP driver, fake | ✓ |
| Phase 2, code keywords only | Jev in 2.1; the replying model grades its own work | |
| Phase 2, with both drivers | Jev plus the LLM fallback now | |

**User's choice:** Option 1.
**Notes:** If the classifier fails, times out or scores below the high threshold, hold (fail closed). Jev gets only the message text, never the name or phone.

| Option | Description | Selected |
|--------|-------------|----------|
| Phase 2.1 | Goes with users, alerts and the handled follow-up | ✓ |
| Phase 2 | Build now on the fake channel | |
| Backlog until owners confirm | Link path only | |

**User's choice:** Approving by text reply is in Phase 2.1.
**Notes:** Assist mode and the quiet period are in 2.1; the after-hours on-call switch is in 2.1; the life-safety line stays in Phase 2.

---

## Alerts and approvals

| Option | Description | Selected |
|--------|-------------|----------|
| Folder says what, user says who | Structured owner_alert; phone on the user record | ✓ |
| All in the folder | owner_alert holds the number | |
| Business phone from profile.md | Risks texting the business line | |

**User's choice:** Option 1.
**Notes:** The owner sets the phone on Filament's profile page. If a text alert is configured and no user has a phone, the Business Folder page warns and the dashboard flags held drafts; an alert never fails silently.

| Option | Description | Selected |
|--------|-------------|----------|
| Urgent now, the rest at opening | Complaint, person, life-safety at once; price/promise/discount after hours at next open | ✓ |
| Always right away | | |
| Owner picks per event | | |

**User's choice:** Urgent now, the rest at opening.

| Option | Description | Selected |
|--------|-------------|----------|
| Built by code from a template | Name, why held, quoted message, draft, link | ✓ |
| Model-written summary | | |
| Link only | | |

**User's choice:** Option 1.
**Notes:** Links in the quoted customer message become "[link]"; the dashboard link goes last on its own line.

| Option | Description | Selected |
|--------|-------------|----------|
| Handoff reply, agent goes quiet | Fixed reply, paused, escalate_to alerted | ✓ |
| Handoff reply, agent keeps helping | | |
| Alert only | | |

**User's choice:** Option 1.
**Notes:** Use the same paused state and hand-back as Take over. The life-safety line still sends while paused; nothing else does. Life-safety rules: gas smell, fire or smoke, CO alarm, sparking; get to safety, call 911 from outside; the owner may add the gas utility's number; never give repair or shut-off steps; send first; when unsure, send.
**Follow-up (flooding):** Plain flooding is not life-safety (the 2.1 on-call switch handles it). Water reaching outlets, the panel or plugged-in appliances is: stay out of the water, away from the electrical, call 911.

---

## How the gate decides

| Option | Description | Selected |
|--------|-------------|----------|
| Three checks must all pass | Agent self-label, code checks, Jev | ✓ |
| Code checks plus Jev only | | |
| Classify the customer's message only | | |

**User's choice:** Option 1.
**Notes:** Store each verdict and score on the message, and show a plain-words reason on the draft. One Jev call per reply (customer message and draft as two fields; intent choice with "other"; yes/no for price, promise, discount, complaint, life-safety). Pin jev-1.13.0. Code checks are a hard veto (Jev isn't injection-hardened). The decision is made in code on certainty, starting at 0.85, with a short timeout that fails closed. Confirm against docs.typesafe.ai.

| Option | Description | Selected |
|--------|-------------|----------|
| No, those four are a hard floor | Validator rejects price/promise/discount/complaint in send_on_own | ✓ |
| Configurable, with a warning | | |
| Only fixed prices may send | | |

**User's choice:** Hard floor. Option 3 recorded as a deferred idea, with its trigger.

| Option | Description | Selected |
|--------|-------------|----------|
| New rules/ files | rules/replies.md and rules/triage.md | ✓ |
| Add to existing files | | |
| Replies in code, triage in the folder | | |

**User's choice:** Option 1.
**Notes:** The life-safety core sentence is fixed in code and the folder only fills placeholders. The sync runs the same code checks on rules/replies.md and rejects a failing reply.

| Option | Description | Selected |
|--------|-------------|----------|
| Own table, tied to the conversation | intake_answers rows | ✓ |
| JSON on the conversation | | |
| On the contact | | |

**User's choice:** Option 1.
**Notes:** Store the icm_version id on each answer. A correction adds a new row, and the latest is current.

**Defaults confirmed:** about 20 s burst wait; the disclosure at the start of the first reply. Exceptions: life-safety skips the wait, and as a first message it puts the safety sentence first, then the disclosure.

---

## Takeover and the phone page

| Option | Description | Selected |
|--------|-------------|----------|
| An owner reply takes over | Pauses the agent with a banner | ✓ |
| The agent keeps going | | |
| Ask on send | | |

**User's choice:** Option 1.
**Notes:** While paused, every new customer message alerts the owner. Approving or editing and sending a draft does not pause; only a fresh owner-typed reply does.

| Option | Description | Selected |
|--------|-------------|----------|
| One live draft, the old one replaced | Fresh draft over the whole thread; a stale send is refused | ✓ |
| Drafts stack up | | |
| Old draft stands, no new reply | | |

**User's choice:** Option 1.
**Notes:** Alert links point to the conversation. A replaced draft re-alerts only if urgency rises. At most one holding reply while a draft waits. In 2.1, a reply code for a replaced draft is refused the same way.

| Option | Description | Selected |
|--------|-------------|----------|
| Same view, phone-friendly | Existing Filament view, fixed for phone width | ✓ |
| Separate phone page | | |

**User's choice:** Option 1.
**Notes:** Acceptance: at phone width, the held reason, draft and Send are visible without scrolling; the thread opens at the latest message; customer details collapse. UAT on a real phone over Wi-Fi.

**Defaults confirmed:** the home screen widgets, remember-me ticked, return to the thread after sign-in, sign out everywhere in 2.1.

---

## Phase 1 carry-overs

| Item | Options | Selected |
|------|---------|----------|
| WR-13 | Only numbers that texted in / Form leads with consent box | Only numbers that texted in ✓ |
| WR-02 | dispatched_at on messages / Dispatch inside the transaction | dispatched_at ✓ |
| Small items | Fix name, leave IN-03 / Fix both / Leave both | Fix name, leave IN-03 ✓ |
| AGNT-03, GATE-04 | Confirmed / Change one | Confirmed ✓ |

**Notes:**
- WR-13: the later consent-box design is recorded (a YES confirmation text proves consent and number ownership). Trigger: the first client with web-form leads.
- WR-02: add a scheduled sweep (inbound older than 2 minutes with no dispatched_at). The sweep and the retry share one dispatch step that is unique per message.
- Name: an agent-given name fills an empty name; a form name fills only on a contact the form created; otherwise it's a timeline note.

---

## STOP and the agent file

**User's choice (free text):**
- STOP: Twilio handles STOP, START and HELP and its confirmation; a Messaging Service with Advanced Opt-Out; code matches opt-out words as a backup. An opt-out is recorded on the contact and blocks every outbound text, including the life-safety line (Twilio error 21610). The reply box shows "Opted out by texting STOP on <date>". A text from an opted-out customer alerts the owner. START or UNSTOP clears it. The fake channel simulates all of this.
- Agent file: one front-desk agent per channel (the validator rejects a second); escalate_to is "owner"; loads covers profile, services, FAQ, hours, voice and sending rules, plus the fresh card. Provider and model are Claude's call with a fallback on a different provider. The ZDR check before a real client applies to every provider the agent file names.

---

## Claude's Discretion

- The example agent's provider, model and different-provider fallback (DEC-07)
- The burst-wait length, Jev timeout, alert cut-offs and code-check word lists
- The front-matter schemas for owner_alert, set_replies and triage; the verdict columns; the export format
- How the paused state is stored; how a service file is matched
- The CI provider and workflow

## Deferred Ideas

- Phase 2.1, the owner loop (full list in CONTEXT.md)
- Fixed-price auto-send (trigger: owners approve fixed-price drafts unchanged)
- Texting web-form leads first, with a consent box and YES confirmation (trigger: the first web-form client)
- Email reply-to-approve
- ZDR checks before a real client (Jev and every agent provider)
