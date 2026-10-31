---
phase: "7"
slug: "the-spine-and-leads"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-10-31"
---

# Phase 7 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| owner-written business/profile.md to the sync | The owner types `project_word`; the sync decides whether it goes live | Business configuration (`project_word`) |
| sync refusal line to changelog.md and the Business Folder page | The owner's value is echoed back in the refusal | Owner-typed value, one line, cut to 80 characters |
| signed-in user to OpenProject, CloseProject, ReopenProject, RenameProject | Owner state changes and the typed project name | Project rows, project name |
| application code to the projects table | Any class could write it unless confined | Project rows |
| projects rows and owner-typed names to the customer card | The card is markdown read by the owner's AI and loaded fresh by the front desk for every reply | Project label, status, dates, quoted name |
| browser to the Filament contact screen | Signed-in user input (the rename field, button presses) | Project name, action requests |
| owner text to Filament notifications | Filament renders a notification title as HTML | Project label, refusal line |
| owner-written rooms/leads/CONTEXT.md to the sync | The contract's owns and reads decide what the room may claim | Room contract |
| room descriptors to the admin panel | Sections reach the dashboard; claims are pointers | Widget ownership |
| database rows to the dashboard HTML | Owner-typed names, contact names and form-supplied lead source keys are printed | Names, source keys |
| signed-in user to /admin | The dashboard lists contacts across the business | Contact names |
| customer text or web form to RecordInbound | Untrusted inbound creates contacts (RecordInbound unchanged) | Phone, email, message |
| RecordInbound's transaction to the leads room's hook, and the queue to OpenProjectForNewLead | The hook runs inside the inbound's transaction; the job runs later, maybe after the room was turned off | Contact id |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-07-01 | Tampering | project_word in business/profile.md | low | mitigate | Only the six `ProjectWord` cases are accepted after normalising; lookalike and zero-width words are refused as unlisted and the last good version stays live (`ProjectWordTest`) | closed |
| T-07-02 | Tampering | the refusal line echoing the owner's value | low | mitigate | `PlainWords::render()` prints the value through `PlainWords::value()` (one line, cut to 80 characters) | closed |
| T-07-03 | Tampering | projects rows opened by a new lead | medium | mitigate | Partial unique index `projects_one_lead_project_per_contact` in `2026_10_29_000001_create_projects_table.php`; `ProjectRecordTest` "refuses a second project opened by a new lead for one contact" | closed |
| T-07-04 | Repudiation | OpenProject::byHand | low | mitigate | `opened_by`, `opened_by_user_id` and `opened_at` on every open, one ProjectOpened timeline entry, `nullOnDelete` on the user key | closed |
| T-07-05 | Spoofing | ProjectOpened timeline entry on the card | medium | mitigate | Fixed platform sentences and a label built from the word list and an integer; no owner- or customer-typed text under "System note:" | closed |
| T-07-06 | Spoofing | project name on the card | high | mitigate | The name is printed only through `MarkdownText::quote()` under the untrusted label; `CardInjectionTest` proves a heading-shaped name adds no heading | closed |
| T-07-07 | Information disclosure | card section | low | accept | See AR-7-01 | closed |
| T-07-08 | Elevation of privilege | writes to projects outside the four actions, including from App\Ai | high | mitigate | `tests/Unit/ProjectWritesTest.php` scans `app/` for any other writer; its arch rule lets only `App\Actions`, the contact screen's relation managers, `App\Rooms\Leads` and tests use the four actions (D-11) | closed |
| T-07-09 | Tampering | RenameProject name input | medium | mitigate | Whitespace collapsed to one line, `HiddenCharacters` refusal, `MAX_LENGTH = 100` checked before any write | closed |
| T-07-10 | Repudiation | close, reopen, rename | low | mitigate | `closed_by` and `closed_by_user_id` on close, one fixed timeline entry per change, `lockForUpdate` row lock | closed |
| T-07-11 | Elevation of privilege | ProjectsRelationManager actions | medium | mitigate | Behind the admin panel's auth (AdminAccessTest); every action passes `auth()->user()` to the action class | closed |
| T-07-12 | Tampering | notification titles carrying a project name or a refusal | medium | mitigate | Every owner-text title goes through `e()`; `ProjectsRelationManagerTest` sends `<b>` in a refused name | closed |
| T-07-13 | Elevation of privilege | Filament's generic create, edit, delete and associate actions | medium | mitigate | None present in `ProjectsRelationManager`; `ProjectWritesTest` watches the file | closed |
| T-07-14 | Tampering | rooms/leads/CONTEXT.md owns | low | mitigate | The room handles no file type; `LeadsBootTest` pins owns and reads to SPEC | closed |
| T-07-15 | Spoofing | dashboard widget ownership | low | mitigate | `RoomRegistry` refuses a widget claimed by two rooms in either order (`RoomWidgetOwnershipTest`, WR-09) | closed |
| T-07-16 | Denial of service | the web form when the leads room is off | low | mitigate | The form and `RecordInbound` stay shared and unowned; `RecordInbound` is unchanged from main and reads no room | closed |
| T-07-17 | Tampering | names and source keys in leads-section.blade.php | high | mitigate | Escaped `{{ }}` output only; no `{!!` in the view | closed |
| T-07-18 | Information disclosure | contact names and phones on the dashboard | low | accept | See AR-7-02 | closed |
| T-07-19 | Denial of service | an unbounded list or a failing query on the dashboard | medium | mitigate | `LeadsSection::SHOWN = 20` with eager loading; a throw shows `FAILED_LINE` and the dashboard still loads | closed |
| T-07-20 | Denial of service | the inbound when the open cannot be queued or throws | high | mitigate | `OpenProjectForNewLead::queueFor()` dispatches inside `DB::afterCommit` with try/catch and `report()`; the queue-down and open-throws cases in `OpenOnNewLeadTest` | closed |
| T-07-21 | Tampering | duplicate automatic projects under retries or racing jobs | medium | mitigate | `OpenProject::forNewLead()` takes a `lockForUpdate` row lock, checks for any project and catches `UniqueConstraintViolationException` in a savepoint; lost-race case on SQLite, Postgres in CI | closed |
| T-07-22 | Spoofing | a web form typing a known customer's phone or email | medium | mitigate | A matching form creates no contact, so no project opens; `OpenOnNewLeadTest` covers a phone written another way and an email in another case | closed |
| T-07-23 | Elevation of privilege | an agent writing projects | medium | mitigate | The job only calls `OpenProject`; `ProjectWritesTest` covers `App\Rooms\Leads` and keeps the actions out of agent code | closed |
| T-07-24 | Tampering (compliance) | a project for a contact who texted STOP first | low | mitigate | The job returns when the contact is opted out when it runs (D-23); STOP case in `OpenOnNewLeadTest` | closed |
| T-07-SC | Tampering | npm/pip/cargo installs | high | accept | See AR-7-03 | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-7-01 | T-07-07 | The card section shows only what the card's readers (the owner's AI and the front desk for this same customer) already may see: open or closed, dates and who opened it; no other customer's data | Plan-time threat model (07-03) | 2026-10-29 |
| AR-7-02 | T-07-18 | /admin is behind the panel's auth middleware (AdminAccessTest), and New leads already shows the same names and phones to the same users | Plan-time threat model (07-07) | 2026-10-29 |
| AR-7-03 | T-07-SC | The phase installs no package; composer and npm manifests and lock files are unchanged from main | Plan-time threat model (07-01 to 07-08) | 2026-10-29 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-10-31 | 25 | 25 | 0 | secure-phase orchestrator (ASVS L1 grep-depth check; auditor not spawned per the short-circuit rule) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-10-31
