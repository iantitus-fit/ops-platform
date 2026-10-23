# Phase 4: Room Standard - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-10-22
**Phase:** 04-room-standard
**Areas discussed:** Room folder and contract, Who owns today's files, Code home and first proof, What a connector can own

Todo cross-reference: "Phone design pass" (2026-10-17) matched; Ian left it for Phase 11.

---

## Room folder and contract

**Where do room folders sit?**

| Option | Description | Selected |
|--------|-------------|----------|
| Under rooms/ | rooms/front-desk/, rooms/marketing/...; top level unchanged | ✓ (implied) |
| At the top level | Room folders beside business/ and rules/ | |

**User's choice:** No option picked; Ian's note on the next question places room contracts at `rooms/<room>/CONTEXT.md`.
**Notes:** Ian asked that before the context commit Claude stop and show git status and the diff stat, and that the commit carry docs/BLUEPRINT.md with an Affects line per decision, moving the spine and one-owner-per-fact bullets from Decided to Phase (Phases 7 and 4).

**Is a room's CONTEXT.md words only, or a contract the sync checks?**

| Option | Description | Selected |
|--------|-------------|----------|
| Front matter the sync checks | owns, reads, writes_records, connector; body stays plain words | ✓ |
| Words only, like today's CONTEXT.md | Router the sync never checks | |

**User's choice:** Front matter, with a narrow Loader change: a CONTEXT.md under rooms/ is parsed as type room; CLAUDE.md, CONTEXT.md and START-HERE.md elsewhere stay routers, still fingerprinted.

**Where do new kinds of file live?**

| Option | Description | Selected |
|--------|-------------|----------|
| In the room's folder | New kinds live with their room | ✓ |
| By kind, like today | New top-level or business/ folders | |

**Notes:** Front desk, team, company knowledge, marketing meet "one example file" through the example business's existing files; money gets one new fictional file in rooms/money/, chosen in Phase 5 with no money rules 999.1 would rework.

**What is the example file for rooms that own only today's files?**

| Option | Description | Selected |
|--------|-------------|----------|
| The owned files already in the example | Boot test empty vs with those files | ✓ |
| Still one file in the room folder | Invent a file even where none is needed | |

**Notes:** "Empty" means booted against a base folder keeping the seven required files; required list unchanged in Phase 4.

**Unknown rooms/<name>/ with no room module?**

| Option | Description | Selected |
|--------|-------------|----------|
| Refuse in plain words | Previous version stays live | ✓ |
| Accept it as words only | Kept like a router | |

**Absent rooms/<name>/ (asked at Ian's request)**

| Option | Description | Selected |
|--------|-------------|----------|
| Not used: hidden and idle | Section hidden, module idle | ✓ |
| Refused: every room folder must exist | | |

**Which rooms can never be left out?**

| Option | Description | Selected |
|--------|-------------|----------|
| Front desk + any owner of a required file | | partly |
| Front desk only | | |

**User's choice:** Front desk, any owner of a required file, and any room the front desk needs to run: team (owner alerts, life-safety at any hour) and owner intelligence if it renders the customer card. Marketing owns lead-sources.md, so it is required too.

**File owned by an absent room?**

| Option | Description | Selected |
|--------|-------------|----------|
| Refuse in plain words | | ✓ |
| Keep the file, warn only | | |

---

## Who owns today's files

| Question | Options | Selected |
|----------|---------|----------|
| business/profile.md | Company knowledge / Front desk | Company knowledge |
| business/hours.md | Schedule and booking / Company knowledge | Company knowledge |
| business/services/*.md | Company knowledge / Front desk | Company knowledge |
| voice/how-we-talk.md | Company knowledge / Marketing / Front desk | Company knowledge |
| rules/sending.md | Front desk owns, team reads / Team owns, front desk reads | Front desk owns, team reads |
| Routers and tests/ | No room: the house / Every file gets a room | The house |
| When enforced | As each room lands / Once all ten exist | As each room lands |
| Where the table lives in Phase 4 | docs/SPEC.md / docs/BLUEPRINT.md / In code | docs/SPEC.md |
| Owner claims another room's type | Refused / Allowed | Refused |

**Notes:**
- "Every file has exactly one owner: a room or the house"; ROOM-01 reworded in the context commit.
- When a room lands, its folder joins examples/example-hvac; the only allowed test change is exampleFolderFileCount in tests/Pest.php; other test changes caused by the ownership check land in that room's phase, never in the Phase 6 wraps.
- Once a room lands, its CONTEXT.md front matter is what the sync enforces; the SPEC row is the map; the boot test fails if they disagree. BLUEPRINT links to the table.
- Rows Ian added: front desk owns rules/replies.md, rules/triage.md, agents/front-desk.md; company knowledge owns business/faq.md; each agent file is owned by the room it serves; each connector file by the room whose slot it fills.

---

## Code home and first proof

| Question | Options | Selected |
|----------|---------|----------|
| Where room code lives | app/Rooms/<Room>/ / Provider per room in app/Providers/ | app/Rooms/ approved (folder only) |
| Does existing code move at the Phase 6 wrap | Stays put; the room points at it / Moves into the room | Stays put |
| How the standard is proven | A pretend room in the tests only / First real rooms in Phase 5 | Pretend room in tests |
| Empty room's dashboard section | One plain line / The section with zeros / Hidden until it has data | One plain line |

**Notes:**
- app/Rooms/: module shape inside researched at plan time; a new composer package or top-level folder goes back to Ian; migrations stay in database/migrations; add the CLAUDE.md Map row when created.
- Existing code stays by kind; registrations may point outside app/Rooms/<Room>/. Settles the Phase 6 note; ROOM-06 and ROOM-15 get the file-count exception.
- Pretend room covers every part (file type + example, table, dashboard section, timeline entry, connector slot + fake driver + connector file, a second fixture connector for the refusal); lives under tests/, registered from test setup only.
- Empty line rendered by the platform from CONTEXT.md for every room; rooms owning no files never shown as empty; wording placeholder; no exclamation points or em dashes.
- Deferred to Phase 7: a `needs` field for rooms that need another room's records.

---

## What a connector can own

| Question | Options | Selected |
|----------|---------|----------|
| Where the kind list lives | One fixed list in code / Each room registers its kinds | One fixed list in code |
| Can a connector claim projects, texts, timeline | Not in v2.0: refused / Claimable now | Not in v2.0 |
| Which connector fields Phase 4 checks | The full SPEC row / Only owns and reads | Only owns and reads (plus slot) |
| How a connector file links to its slot | A field naming the slot / From the file name | A field naming the slot |

**Notes:**
- Keys exactly as 2026-10-19 names them; SPEC lists the same, tied by a test; listed kinds accepted even with no platform records yet.
- Second code list of platform-owned kinds (projects, texts, timeline): readable, never ownable. Texts stay the platform's (D-28/D-54, 02.1 D-49). Conversations, owner alerts, drafts on neither list. `reads: projects` accepted from Phase 4.
- system, direction, schedule pass through unchecked until CONN-01; schedule format not invented.
- Room CONTEXT.md names its slot; connector file's `slot` names what it fills; refusals for an absent room's slot, an unregistered slot, and two files in one slot. The text slot runs from .env with no connector file; Phase 6 decides.

---

## Claude's Discretion

- How rooms add timeline entries given the fixed ActivityType enum.
- How rooms register file types while today's types stay in Validator/Loader.
- Exact front matter field names in the room contract and the writes_records shape.
- Plain-words wording of the new refusals (placeholder).

## Deferred Ideas

- Phase 7 discuss: a `needs` field and a refusal when a needed room is absent.
- Split owner_alert out of rules/sending.md into a team-owned file.
- Phase 6: whether the text slot gets a connector file.
- Phase 5: the money room's example file.
