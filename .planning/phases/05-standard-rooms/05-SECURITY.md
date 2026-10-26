---
phase: "5"
slug: "standard-rooms"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-10-25"
---

# Phase 5 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| business folder to sync | owner-written contracts, file names and room files (brief, money notes, categories) are parsed and checked | see 05-02, 05-04, 05-06, 05-07, 05-09 threat models |
| sync to changelog.md and refusal lines | refusal sentences quote owner-written names and values into stored lines | see 05-04, 05-07, 05-09 threat models |
| business folder to dashboard | room sections draw counts, offer names and the primary category inside the authenticated panel | see 05-03, 05-05, 05-08 threat models |
| engine to Google | the Google Business Profile slot is the first outside system a room names; only a fake driver is bound | see 05-08 threat model |
| test code to shipped code | rewritten Phase 4 tests must still prove what they proved | see 05-01 threat model |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-05-01 | Repudiation | rewritten Phase 4 tests | low | mitigate | Exact lines and lists built from code; no case removed (suite 1981 to 2148) | closed |
| T-05-02 | Elevation of privilege | RoomChecks::claims() wildcard skip | medium | mitigate | Pattern-matched routers skipped, exact names and walk tests still refused (RouterClaimTest) | closed |
| T-05-03 | Denial of service | absent never-left-out room | medium | mitigate | One named line, no "remove the file" advice, previous version live (Unit NeverLeftOutTest) | closed |
| T-05-04 | Information disclosure | CompanyKnowledgeSection | low | mitigate | Renders only in the authenticated panel; RoomSection::canView() hides it when off | closed |
| T-05-05 | Tampering | company knowledge contract owns | low | mitigate | Boot test pins owns to SPEC rows (patternsOwnedBy); extra knowledge file refused | closed |
| T-05-06 | Tampering | ValidationReport::lines(), changelog.md | high | mitigate | Loader refuses hidden-character names; lines() marks every Cc, Cf, Zl, Zp (HiddenCharacters) | closed |
| T-05-07 | Spoofing | Loader name check | medium | mitigate | Cf refused, ZWJ and ZWNJ included (HiddenCharacters::PATTERN, HiddenCharacterTest) | closed |
| T-05-08 | Denial of service | HiddenCharacters::found() and marked() | low | mitigate | preg_match false counts as a hit; marked() runs mb_scrub first (HiddenCharactersTest) | closed |
| T-05-09 | Tampering | MarketingSection offers line | medium | mitigate | Asks OfferSentences::liveToday(), the front desk's rule (MarketingBootTest, four moments) | closed |
| T-05-10 | Denial of service | absent required room | low | mitigate | One plain line, previous version live (Feature NeverLeftOutTest through icm:sync) | closed |
| T-05-11 | Tampering | rooms/content_generation/brief.md | medium | accept | No agent reads the brief this phase (D-14); see accepted risks | closed |
| T-05-12 | Elevation of privilege | content generation connector field | low | mitigate | Connector other than none refused for a room with no slot (ContentGenerationBootTest) | closed |
| T-05-20 | Tampering | single-file check, owner-edited contract | medium | mitigate | D-27 RoomChecks::singleFileErrors() keeps one file per single-file type (SingleFileTypeTest) | closed |
| T-05-13 | Information disclosure | rooms/money/notes.md | high | mitigate | AccountNumberScan refuses card-shaped (check digit) and every 9-digit span (MoneyNotesCheckTest) | closed |
| T-05-14 | Information disclosure | refusal line, rejected version, changelog.md | high | mitigate | Fixed sentence with no digit; ordering test asserts no digit in line or changelog | closed |
| T-05-15 | Information disclosure | numbers the check cannot see | medium | accept | Bank account numbers have no shape; warned in rooms/money/CONTEXT.md; see accepted risks | closed |
| T-05-21 | Tampering | a second money notes file | low | mitigate | MoneyNotesType::singleFileLabel() is 'money notes file' (MoneyBootTest) | closed |
| T-05-16 | Information disclosure | GoogleListingSource binding | high | mitigate | Only the fake is bound, other drivers throw, one read method; Http::assertNothingSent() in the boot test | closed |
| T-05-17 | Tampering | connector file owns | medium | mitigate | owns and reads are none; [none] refused (GoogleBusinessProfileBootTest, ConnectorFileTest) | closed |
| T-05-18 | Information disclosure | credentials in the connector file | medium | mitigate | No credential field (acceptance grep); credentials will live encrypted in the engine | closed |
| T-05-22 | Tampering | a second categories file | medium | mitigate | GbpCategoriesType::singleFileLabel() set; second file refused wherever owns points | closed |
| T-05-19 | Tampering | GbpCategoriesType::checks() | low | mitigate | Owner values reach a line only through PlainWords::oneLine(); list bounded at nine (GbpCategoriesTest) | closed |
| T-05-SC | Tampering | npm/pip/cargo installs | high | accept | No package installed in any Phase 5 plan; composer and package files unchanged against main | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-05-01 | T-05-11 | The content brief is guidance read by no agent in Phase 5 (D-14); the rules that must hold stay in never_say, checked by code on every draft when the writer is built | plan 05-06 threat model (Ian's planning decisions) | 2026-10-25 |
| AR-05-02 | T-05-15 | Bank account numbers have no shape the check can name; a ZIP+4 or a leading-+ phone shape passes by D-26's own terms; the room's CONTEXT.md warns the owner | plan 05-07 threat model (D-26) | 2026-10-25 |
| AR-05-03 | T-05-SC | No package is installed in this phase (05-RESEARCH.md Package Legitimacy Audit: none) | plan threat models | 2026-10-25 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-10-25 | 23 | 23 | 0 | secure-phase orchestrator (L1 grep-depth; auditor skipped by the short-circuit rule) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-10-25
