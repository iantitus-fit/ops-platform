---
phase: "4"
slug: "room-standard"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-10-22"
---

# Phase 4 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| business folder to sync | `rooms/<room>/CONTEXT.md` is owner-written text now parsed as front matter instead of kept raw | see 04-01 threat model |
| process boot to database | room providers run at every process start, before any sync and before tables may exist | see 04-01 threat model |
| test code to shipped code | the pretend room must never become part of the running product | see 04-01 threat model |
| sync to changelog.md | refusal sentences quote owner-written values into the changelog and the Business Folder page | see 04-02 threat model |
| room code to sync | a room's RoomFileType supplies the rules its files are validated with | see 04-03 threat model |
| platform records to outside systems | owning a kind would let an outside system's copy win over the platform's | see 04-04 threat model |
| room code to timeline | room-written summaries reach the customer card the owner's AI and the platform's agents read | see 04-05 threat model |
| business folder to visible records | removing a room folder changes what the owner sees without touching the database | see 04-05 threat model |
| business folder to dashboard | owns entries are owner-written text drawn into the empty line | see 04-06 threat model |
| browser to Livewire | a hidden section's component could be requested directly | see 04-06 threat model |
| Packagist to the repository | a new Composer package and its dependencies enter composer.lock and run in CI | see 04-07 threat model |
| npm registry to the repository | a new npm package enters package-lock.json and runs in CI | see 04-08 threat model |
| GitHub Actions marketplace to CI | a new action runs with the workflow's permissions | see 04-08 threat model |
| Playwright download host to CI and the Mac | a browser binary is downloaded and executed by tests | see 04-08 threat model |
| test runner to headless browser | the browser loads the app as a signed-in test user and runs injected axe code | see 04-09 threat model |

---

## Threat Register

Register authored at plan time across plans 04-01 to 04-09 (no SUMMARY raised a new threat flag). Verified at ASVS L1 (grep depth) on 2026-10-22: each mitigation was located in the named code and its test.

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-04-01 | Tampering | app/Icm/Loader.php room contract parse (04-01) | medium | mitigate | Only the exact path `rooms/<room>/CONTEXT.md` leaves the router branch (case-sensitive regex, Test 4 and Test 5); parsing reuses the Loader's existing YAML path with its errors; Plan 04-02 adds the contract's field checks | closed |
| T-04-02 | Denial of service | app/Rooms/RoomServiceProvider.php (04-01) | medium | mitigate | register() only adds a descriptor; PHPDoc forbids ActiveConfig, IcmVersion, DB and Filament::getPanel() in register() and boot(); hasRoom() and roomKeys() never throw (Test 6), so composer install and a fresh database cannot crash on a room | closed |
| T-04-03 | Elevation of privilege | tests/Rooms/PretendRoom/* (04-01) | medium | mitigate | Registered only by enablePretendRoom() in tests/Pest.php; Test 7 asserts bootstrap/providers.php lists only AppServiceProvider and AdminPanelProvider (D-20) | closed |
| T-04-04 | Information disclosure | app/Rooms/RoomSection.php (04-01) | low | mitigate | canView() hides the section unless the live version has the room; Filament's CanAuthorizeAccess aborts 403 on mount or hydrate when canView() is false (verified in vendor/filament/widgets/src/Concerns/CanAuthorizeAccess.php) | closed |
| T-04-05 | Tampering | RoomChecks::contractErrors() paths (04-02) | medium | mitigate | Empty entries, a leading / and any .. segment are refused in owns, reads and writes_records; writes_records must sit under records/ (Task 2 tests); the Loader still reads only inside the root and skips links | closed |
| T-04-06 | Tampering | refusal sentences in RoomChecks (04-02) | medium | mitigate | Every owner-written value goes through PlainWords::oneLine() or PlainWords::value(), so a value cannot start a new line or heading in changelog.md | closed |
| T-04-07 | Spoofing | rooms/<name>/ for a room the engine lacks (04-02) | low | mitigate | D-07 refusal names the folder and the rooms that exist; the previous version stays live (Task 2 Test 5) | closed |
| T-04-08 | Spoofing | contract connector field (04-02) | low | mitigate | D-25 contract side: connector must equal the registered slot or none (Task 3) | closed |
| T-04-09 | Tampering | RoomChecks claims (04-03) | medium | mitigate | D-17, D-11 and D-15 refusals (Task 2): a room claims only types its code handles, never the house's files or a connector file, never a file another room claims | closed |
| T-04-10 | Elevation of privilege | RoomChecks absent-room and unclaimed checks (04-03) | medium | mitigate | D-10 refuses files of an absent room and the one-owner check refuses unclaimed files while the handling room is present, so a removed room's files cannot linger as configuration nobody owns | closed |
| T-04-11 | Tampering | RoomFileType rules supplied by room code (04-03) | low | accept | Rules are code reviewed with the room's phase; a file of a registered type is still validated through Laravel's validator and PlainWords like every other file | closed |
| T-04-12 | Denial of service | Str::is over files times owns patterns (04-03) | low | accept | A business folder holds tens of files and a contract a handful of patterns; the cost is negligible within the existing sync lock | closed |
| T-04-13 | Elevation of privilege | ConnectorChecks::fileErrors() owns (04-04) | high | mitigate | PlatformKind values (texts, projects, timeline) are refused in owns (D-23), so no connector file can claim the platform's sent messages and bypass OutboundSender; Task 2 Test 1 covers all three | closed |
| T-04-14 | Tampering | ConnectorChecks::folderErrors() two owners (04-04) | medium | mitigate | D-26 refusal on the later file with the exact sentence; the previous version stays live (Task 3 Test 1) | closed |
| T-04-15 | Spoofing | connector slot field (04-04) | medium | mitigate | D-25: unknown slots, slots of absent rooms and a second file in one slot are refused (Task 3 Tests 3 to 5) | closed |
| T-04-16 | Information disclosure | system, direction, schedule passed through into the compiled version (04-04) | medium | accept | D-24 (locked) leaves these unchecked until CONN-01, which adds the no-secrets scan with the first real connector; Phase 4 ships no real connector, SPEC and the example README both say credentials never go in the folder, and the folder is the owner's own (same trust domain) | closed |
| T-04-17 | Tampering | refusal sentences quoting owner values (04-04) | low | mitigate | PlainWords::oneLine() on every owner-written value in ConnectorChecks | closed |
| T-04-18 | Tampering | CustomerCard room-entry arm (04-05) | medium | mitigate | The summary is quoted through the existing MarkdownText::quote() like every other body, so room text cannot add headings or instructions to the card | closed |
| T-04-19 | Repudiation | removing a room folder (04-05) | medium | mitigate | Rows are never deleted; the visibleOnTimeline scope only hides them and they return with the folder (Task 2 Tests 1 and 2 assert the row count) | closed |
| T-04-20 | Elevation of privilege | RecordRoomEntry (04-05) | low | mitigate | Writes only while the room is in the live version (D-08) and only entry keys the room's code registered (LogicException otherwise) | closed |
| T-04-21 | Information disclosure | Activity::visibleOnTimeline() before the first sync (04-05) | low | mitigate | roomKeys() never throws and returns [], so room rows stay hidden and v1.0 rows show (Task 2 Test 3) | closed |
| T-04-22 | Tampering | resources/views/filament/rooms/section.blade.php (04-06) | medium | mitigate | Owner-written owns entries are printed only through escaped `{{ }}` output; the acceptance check fails on any unescaped output tag | closed |
| T-04-23 | Information disclosure | RoomSection for an absent room (04-06) | low | mitigate | Filament's bootCanAuthorizeAccess aborts 403 when canView() is false (Task 1 Test 3), so a hidden section's data is never computed | closed |
| T-04-24 | Tampering | transitive amphp packages (04-07) | medium | mitigate | Listed for Ian at the checkpoint; pinned by composer.lock; used only by the test runner, never by the running app | closed |
| T-04-25 | Denial of service | CI install step (04-07) | low | accept | sockets is added to setup-php; a resolution failure stops the plan and is reported, never worked around by loosening other constraints | closed |
| T-04-26 | Tampering | actions/setup-node (04-08) | medium | mitigate | Pinned by full commit SHA with the tag as a comment, like every existing action; the SHA is confirmed with a read-only gh api call | closed |
| T-04-27 | Tampering | Chromium download (04-08) | low | transfer | Downloaded by Playwright's own installer from Microsoft's hosts; the version is tied to playwright 1.63.0; the workflow keeps `permissions: contents: read` | closed |
| T-04-28 | Repudiation | a vacuous accessibility pass (04-09) | medium | mitigate | checked > 0 guard plus the injected-violation test (image-alt must be reported), so a green run means rules actually ran on the section | closed |
| T-04-29 | Information disclosure | browser test data (04-09) | low | accept | Only factory users and the fictional example folder in a temp copy; screenshots and traces land in gitignored tests/Browser folders | closed |
| T-04-30 | Tampering | axe script injected into the page (04-09) | low | accept | Injected only by the test runner in tests; nothing is added to the product's pages (no overlay, PROJECT.md Frontend) | closed |
| T-04-31 | Information disclosure | stored customer card in records/ after a room is removed (04-05) | medium | mitigate | FolderSync::queueCardsForRoomChanges() queues a redraw of every card holding entries of a room that went off or on, so the card the owner's AI reads drops a removed room's entries at that sync (Task 2 Tests 5 and 6); a queueing failure is reported and never fails the live sync, and RenderCustomerCard's per-contact uniqueness collapses a burst | closed |
| T-04-32 | Tampering | Loader::NONE_MEANS_EMPTY (none read as an empty list) (04-02) | low | mitigate | Only the exact lower-case word none, as the whole value of a listed field, becomes []; any other non-list word is still refused with the list sentence (Task 1 Test 2: everything, None, nothing), whole-value none and [] load as [] (Task 1 Test 5), and [none] is checked like any entry (Task 1 Test 6: owns: [none] compiled as ['none']; Task 2 Test 2: writes_records: [none] refused with the records sentence); the rule lives in one Loader constant, so the Validator, RoomChecks and the compiled version cannot disagree about what none means | closed |
| T-04-33 | Tampering | RoomChecks placement check (D-04) (04-03) | low | mitigate | A file of a room's own type outside rooms/<room>/, another room's folder included, is refused naming the file and the folder it belongs in, with one line that also offers to add the folder or remove the file when the room's folder is absent, and the previous version stays live (Task 2 Tests 6 and 8), so a room's files cannot sit where the house's or another room's checks assume other kinds of file; only room-registered types are checked, and RoomRegistry::add() keeps those apart from today's types | closed |
| T-04-34 | Tampering | Loader::NONE_MEANS_EMPTY, connector side (shares the mitigation of T-04-32, owned by Plan 04-02) (04-04) | low | mitigate | The connector arm joins the same constant, so only the exact whole-value word none in owns or reads becomes []: both forms load as [] (Task 1 Test 4), a bare word such as customers is still refused with the list sentence (Task 1 Test 4), owns: [none] is refused as an unknown kind (Task 2 Test 1), and slot is not listed, so slot: none is refused as an unknown slot (Task 3 Test 3) | closed |
| T-04-SC | Tampering | browser-check package installs: composer pestphp/pest-plugin-browser (04-07), npm playwright (04-08); register row in (04-01, 04-02, 04-03, 04-04, 04-05, 04-06, 04-07, 04-08, 04-09) | high | mitigate (04-07, 04-08); accept elsewhere (no install) | Blocking-human checkpoint (Task 1) verifies the publisher and the exact version before install; `--save-exact` pins 1.63.0 (not the days-old 1.64.x); package-lock.json records the integrity hash; npm ci in CI installs only the lock | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-04-01 | T-04-11 | Rules are code reviewed with the room's phase; a file of a registered type is still validated through Laravel's validator and PlainWords like every other file | Plan 04-03 threat model (plan-time acceptance) | 2026-10-22 |
| AR-04-02 | T-04-12 | A business folder holds tens of files and a contract a handful of patterns; the cost is negligible within the existing sync lock | Plan 04-03 threat model (plan-time acceptance) | 2026-10-22 |
| AR-04-03 | T-04-16 | D-24 (locked) leaves these unchecked until CONN-01, which adds the no-secrets scan with the first real connector; Phase 4 ships no real connector, SPEC and the example README both say credentials never go in the folder, and the folder is the owner's own (same trust domain) | Plan 04-04 threat model (plan-time acceptance) | 2026-10-22 |
| AR-04-04 | T-04-25 | sockets is added to setup-php; a resolution failure stops the plan and is reported, never worked around by loosening other constraints | Plan 04-07 threat model (plan-time acceptance) | 2026-10-22 |
| AR-04-05 | T-04-29 | Only factory users and the fictional example folder in a temp copy; screenshots and traces land in gitignored tests/Browser folders | Plan 04-09 threat model (plan-time acceptance) | 2026-10-22 |
| AR-04-06 | T-04-30 | Injected only by the test runner in tests; nothing is added to the product's pages (no overlay, PROJECT.md Frontend) | Plan 04-09 threat model (plan-time acceptance) | 2026-10-22 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-10-22 | 35 | 35 | 0 | /gsd-secure-phase 04 (orchestrator, ASVS L1 grep verification) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-10-22
