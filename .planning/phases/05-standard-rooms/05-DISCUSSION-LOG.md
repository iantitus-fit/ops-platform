# Phase 5: Standard Rooms - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-10-25
**Phase:** 05-standard-rooms
**Areas discussed:** Google Business Profile, Content generation, Money's example file, Sections, names, G-04-1

---

## Google Business Profile

### What new file does the room own?

| Option | Description | Selected |
|--------|-------------|----------|
| Listing extras | Only the facts Google asks for that the folder lacks (primary category, description); reads profile and hours | ✓ (narrowed) |
| Posting plan | What the business posts and how often | |
| Both files | Listing extras and a posting plan | |
| You decide | Claude picks during planning | |

**User's choice:** Listing extras, narrowed to categories only (primary and additional). No description or service area.
**Notes:** The contract reads profile.md (name, phone, service_area, body as description), hours.md (weekly, closures as holiday hours), services/*.md (name, price_policy, price) and offers/*.md (live offers, for posts later). Add GBP as a reader of hours, services and offers in the SPEC table. Body: three local ranking factors; the folder is the one source; Google's copy is never written back; differences are shown later (CONN-01). The 750-character limit waits for the real connector. Ian also gave a list of what a good profile needs (deferred).

### What does the connector file declare?

| Option | Description | Selected |
|--------|-------------|----------|
| owns: none, reads: none | Google is the truth for no record kind | ✓ |
| Add a reviews kind now | Extends D-22 | |
| owns: documents | Photos as documents | |

**User's choice:** owns: none, reads: none, both as the bare word none.
**Notes:** `[none]` is refused as an unknown kind (verified, ConnectorFileTest.php:125). Not documents: ownership is per kind, so it would make Google the truth for every document.

### What can the slot's interface do?

| Option | Description | Selected |
|--------|-------------|----------|
| Read only | One method fetching the listing; fake returns fictional data | ✓ |
| Read and push listing | Adds a send method, needs a chokepoint | |
| Bare slot | Interface with no methods | |

**User's choice:** Read only, listing only.
**Notes:** The listing is not stored or shown this phase. Send methods come with the real connector, each through a chokepoint with owner approval.

### How strictly is the categories file checked?

| Option | Description | Selected |
|--------|-------------|----------|
| Shape only | Primary required, up to 9 additional, no repeats | ✓ |
| Shape, primary optional | Primary may be left out | |
| Check against Google's list | Ship Google's category names | |

**User's choice:** Shape only, comparing ignoring case and extra spaces.
**Notes:** No category picked means the file is left out; the empty room is valid.

---

## Content generation

### Model role

| Option | Description | Selected |
|--------|-------------|----------|
| Mid-tier drafts | Mid-tier model drafts, owner approves | ✓ |
| Frontier, back office | Slow work, frontier model | |
| Leave it open | Decide when the agent is built | |

**User's choice:** Mid-tier drafts, recorded in BLUEPRINT only; no agent file this phase.
**Notes:** Mid-tier is a capability level (any DEC-07 provider, open-weight included). Prices and offers only as folder sentences inserted by code; the same words check as the front desk; owner approves every draft; publishing through a chokepoint; no model final check.

### Its one file

| Option | Description | Selected |
|--------|-------------|----------|
| Brief, body only | Plain-words brief, front matter only its type | ✓ |
| Brief with topics list | Adds a checked topics list | |
| One file per piece | Content calendar | |

**User's choice:** Brief, body only, at most one.
**Notes:** Guidance only; anything that must hold goes in voice never_say, and CONTEXT.md says so.

### Reads

| Option | Description | Selected |
|--------|-------------|----------|
| Voice, profile, services, offers | Four reads | |
| Add faq and hours | Six reads | ✓ |
| Voice only | Only the table's reader | |

**User's choice:** Six reads (voice, profile, services, offers, hours, faq); writes_records: none.
**Notes:** Posts should answer price, timing and redemption. The reads list is the room's map; the agent's loads pick the smallest set.

---

## Money's example file

| Option | Description | Selected |
|--------|-------------|----------|
| Money notes, body only | Plain-words notes on how the business gets paid | ✓ |
| Books pointer, checked | Names the accounting tool and billing contact | |
| Payment methods list | Checked list of payment methods | |

**User's choice:** Money notes, body only, at most one.
**Notes:** For whoever sets up 999.1; nothing reads it to act; not customer-facing (faq is). May name the tool; truth only in connector owns. No amounts, percents or terms, and never account, routing or card numbers. The CONN-01 no-secrets scan covers rooms/money/.

### Follow-up: number check

**User's choice:** Refuse card numbers (13 to 19 digits, together or split, passing the check digit) and any 9-digit number (together or 123-45-6789), naming the file in plain words; the previous version stays live. Phone numbers recognized with App\Support\PhoneNumber and skipped. Bank account numbers are left to the CONTEXT.md warning and the CONN-01 scan.

---

## Sections, names, G-04-1

### Folder names

| Option | Description | Selected |
|--------|-------------|----------|
| Full names | company_knowledge, marketing, content_generation, google_business_profile, money | ✓ |
| Short knowledge | rooms/knowledge/ | |
| Short GBP too | rooms/knowledge/ and rooms/gbp/ | |

**User's choice:** Full names.

### What each furnished section shows

| Option | Description | Selected |
|--------|-------------|----------|
| Facts from the folder | Plain lines from the live version | ✓ |
| Pointer only | One line naming files | |
| Facts plus numbers | Adds leads and cost per source | |

**User's choice:** Facts from the folder.
**Notes:** Company knowledge: services count, FAQ in. Marketing: lead sources count and offers live today via the front desk's own code. GBP: primary category and a not-connected line. Content generation and money: brief or notes in. Shared section view, placeholder wording, axe check, keyboard and VoiceOver walk at UAT.

### G-04-1

| Option | Description | Selected |
|--------|-------------|----------|
| Refuse at load, clean at print | Loader refuses; lines() marks as backstop | ✓ |
| Clean at print only | lines() only | |
| Refuse at load only | Loader only | |

**User's choice:** Refuse at load, clean at print, using Unicode Cc, Cf, Zl and Zp, with the print step after PlainWords::oneLine.
**Notes:** Hidden characters inside file values deferred to Safety and compliance open items.

### Split

| Option | Description | Selected |
|--------|-------------|----------|
| Planner decides | Split at plan time if needed | ✓ (amended) |
| One branch, no split | | |
| Split now | | |

**User's choice:** Planner proposes, Ian decides. A split becomes 5.1, and later numbers don't change.

### Context commit

**User's choice:** BLUEPRINT, REQUIREMENTS (ROOM-11, CONN-01 note) and ROADMAP edits in the same commit as CONTEXT and this log; SPEC readers and file types in execution (04 D-14). Stop before committing and show git status and the full diff.
**Notes:** Carried in: never-left-out for company knowledge and marketing; success criterion 1 read with 04 D-05; the accessibility status reworded in BLUEPRINT row 10 and the Safety and compliance Phase line.

---

## Claude's Discretion

- File names and type keys for the three new files and the example connector file.
- The slot interface's name, method and return shape, and the fake's listing.
- Company knowledge's and marketing's reads lists and CONTEXT.md bodies.
- Placeholder wording of the new refusals and section lines.
- How never-left-out is built in RoomChecks.

## Deferred Ideas

- What a good Google profile needs (Ian, 2026-10-25): BLUEPRINT Ideas inbox.
- Hidden characters inside file values: BLUEPRINT Safety and compliance open item.
- A reviews kind (extends 04 D-22, Ian's call).
- Showing where Google differs from the folder, and the 750-character check: with the real connector.
- Content generation's agent file: when built.

## Review before commit (Ian, 2026-10-25)

- Approved Claude's two calls: D-04 (the brief and the money notes are their own types) and D-05 (no slot for company knowledge, marketing, content generation and money).
- D-05: where the web lead form and other lead intake sit is the leads room's call (Phase 7, ROOM-07); marketing does not claim it.
- D-02: an absent room that can never be left out gives one line naming the room and the reason, and skips the per-file "belongs to the X room" lines for its files (`app/Icm/RoomChecks.php:358`).
- D-03: company knowledge and marketing can never be empty of the required files they own, so their empty boot test keeps them (04 D-06).
- BLUEPRINT Safety and compliance: the WCAG bullet split into a Built line (automated check, Phase 4) and a Phase line (booking page and owner screens, Phases 8 and 11).
