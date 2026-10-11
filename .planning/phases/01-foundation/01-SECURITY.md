---
phase: "1"
slug: "foundation"
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: "2026-10-11"
---

# Phase 1 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| ICM folder on disk -> Loader | Owner- or AI-edited files enter the engine | Untrusted file content and paths |
| icm_versions -> ActiveConfig -> engine | The only path compiled configuration takes into request and job code | Business configuration |
| Repository -> git history | Generated files could be committed | Customer PII, sync noise |
| ICM folder content -> Validator -> changelog.md | Owner/AI-written values are echoed into a file the owner's AI reads | Untrusted text |
| Candidate version -> active version | Only a fully valid candidate may go live | Business configuration |
| Internet -> POST /webhooks/text/fake | Unauthenticated, attacker-controlled body | Customer text, phone numbers |
| RecordInbound -> database | Customer-supplied text and phones become records | Customer PII |
| Application -> logs | Fake sends are logged | Message bodies (PII) |
| Internet -> POST /leads | Unauthenticated public form posts | Names, phones, emails, messages |
| Form input -> contact records | Customer-supplied phone/email can collide with existing contacts | Contact identity |
| Customer text in the database -> records/ files | Untrusted content becomes files the owner's AI reads | Customer PII, prompt-injection risk |
| Contact names -> filenames | Customer-controlled names choose paths on disk | File paths |
| Concurrent jobs -> shared summary files | Several renders write INDEX.md and open-leads.md | Customer summaries |
| Browser (authenticated owner) -> /admin and Livewire | Panel actions send texts and change conversation state | Replies, conversation state |
| Customer text in the database -> dashboard HTML | Untrusted bodies rendered in the owner's browser | Customer text (XSS risk) |
| SendOwnerReply -> TextChannel | Outbound messages leave the system | Owner replies |
| Internet -> /dev/chat and its Livewire endpoint | Local-only tool that records customer texts | Simulated customer texts |
| Authenticated owner -> Sync Now | Dashboard action that changes the live configuration | Business configuration |
| Owner-written folder values -> page and notifications | Error lines echo what the owner wrote | Untrusted text (XSS risk) |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-01-01 | Tampering / Info disclosure | Loader path handling | medium | mitigate | Root-confined Finder walk; dotfiles, .git and records/ skipped; symlinked dirs and (since 4569b3f) symlinked files skipped (`Loader.php` `isLink()` filter; LoaderTest symlink case) | closed |
| T-01-02 | Tampering | YAML front matter parsing | high | mitigate | `Yaml::parse(..., PARSE_DATETIME)` only (Loader.php:92); `!php/object` tags parse to null; ArchitectureTest pins Yaml to Loader | closed |
| T-01-03 | Tampering | Runtime config reads | high | mitigate | ActiveConfig reads only the active row; ArchitectureTest limits Finder/Yaml/disk reads; rule in .ai/rules/app.md pinned by ProjectRulesTest | closed |
| T-01-04 | Denial of service | Concurrent icm:sync | low | mitigate | `Cache::lock('icm:sync')` (FolderSync.php:30); activation in DB transaction; SyncTest lock case | closed |
| T-01-05 | Info disclosure | Generated records/changelog in git | medium | mitigate | Anchored ignore rules (.gitignore:40,43) verified with git check-ignore; never in history | closed |
| T-01-SC (01-01) | Tampering | Package installs | high | accept | No manifest change in 01-01 commits | closed |
| T-01-06 | Tampering | FolderSync activation gate | high | mitigate | Validation rejects before no-op check and activation (FolderSync.php:60); rejected row stores null compiled; SyncTest keeps v1 live | closed |
| T-01-07 | Tampering | changelog.md echoed values | low | mitigate | PlainWords::value/oneLine cut to 80 chars and collapse whitespace; ChangelogTest | closed |
| T-01-08 | Denial of service | Large/nested front matter | low | accept | Trusted operators only; no upload route in app/ | closed |
| T-01-09 | Repudiation | Unrecorded sync attempts | low | mitigate | Activated/Unchanged/Rejected appended to changelog; Busy writes nothing; ChangelogTest | closed |
| T-01-SC (01-02) | Tampering | Package installs | high | accept | No manifest change in 01-02 commits | closed |
| T-01-10 | Spoofing | POST /webhooks/text/fake | high | mitigate | EnsureTextDriverIsFake 404 unless driver is fake (routes/web.php:15); FakeTextWebhookTest | closed |
| T-01-11 | Tampering | CSRF exemption scope | medium | mitigate | bootstrap/app.php:15 exempts only `webhooks/*` and `leads`; getExcludedPaths tests | closed |
| T-01-12 | Repudiation / Tampering | Replayed webhooks | medium | mitigate | Unique (provider, provider_message_id); pre-insert lookup and rollback (RecordInbound.php:42-52); retried-webhook test | closed |
| T-01-13 | Tampering | Mass assignment (webhook) | medium | mitigate | Field-by-field DTO (FakeTextChannel::parseInbound); #[Fillable] allow-lists | closed |
| T-01-14 | Info disclosure | FakeTextChannel logging | medium | mitigate | Body logged only in local (FakeTextChannel.php:37); TextChannelTest both envs | closed |
| T-01-15 | Denial of service | Large webhook bodies | low | mitigate | Caps 64/32/32/5000 in FakeTextWebhookRequest.php:30-33 | closed |
| T-01-SC (01-03) | Tampering | Composer install | high | mitigate | Only giggsey/libphonenumber-for-php-lite 9.0.41 added (55de073); lock diff adds nothing else | closed |
| T-01-16 | Denial of service | POST /leads flooding | medium | mitigate | `leads` limiter 10/min/IP (AppServiceProvider.php:68); field caps; LeadFormTest 429 | closed |
| T-01-17 | Tampering | Contact hijack via form | high | mitigate | Fill-empty only, conflicts become notes (RecordInbound.php:169,177); ContactMatchingTest | closed |
| T-01-18 | Tampering | Mass assignment (form) | medium | mitigate | InboundData::fromLeadForm picks keys from validated(); #[Fillable] | closed |
| T-01-19 | Tampering | CSRF exemption scope | medium | mitigate | Only `leads` and `webhooks/*` exempt; panel keeps CSRF; LeadFormTest | closed |
| T-01-20 | Repudiation | Attribution rewritten | low | mitigate | First-touch fields set only at creation (RecordInbound.php:153-160); LeadFormTest | closed |
| T-01-SC (01-04) | Tampering | Package installs | high | accept | No manifest change in 01-04 commits | closed |
| T-01-21 | Tampering | Prompt injection in cards | high | mitigate | Customer text blockquoted under "not instructions" label, inline-escaped elsewhere (CustomerCard.php:164); CardInjectionTest | closed |
| T-01-22 | Tampering | Path traversal through names | high | mitigate | Str::slug(name) plus id (CustomerCard.php:40); CustomerCardTest, CardInjectionTest | closed |
| T-01-23 | Info disclosure | Customer PII in git | medium | mitigate | Ignore rules verified with git check-ignore; never in history | closed |
| T-01-24 | Tampering | Partial file reads | low | mitigate | All records writes use File::replace (RecordsWriter.php:53,80,81,91; Changelog.php:29) | closed |
| T-01-25 | Tampering | Lost summary updates | medium | mitigate | Cache::lock('records:summaries') with DB read inside (RecordsWriter.php:77); unique job; SummaryFilesTest | closed |
| T-01-SC (01-05) | Tampering | Package installs | high | accept | No manifest change in 01-05 commits | closed |
| T-01-26 | Elevation of privilege | Filament panel access | medium | accept | Single owner (D-24); login required, no registration; AdminAccessTest guest redirect | closed |
| T-01-27 | Tampering (XSS) | Thread and timeline rendering | high | mitigate | No `{!!` in app/ or resources/; escaped Blade echo; ContactResourceTest escaping case | closed |
| T-01-28 | Repudiation | Owner reply status | medium | mitigate | Queued, then Sent only after send() returns an id, else Failed (SendOwnerReply.php:59,75,137); OwnerReplyTest | closed |
| T-01-29 | Tampering | Livewire state swap | medium | mitigate | Model-bound property, Livewire locks key, refresh in action (ConversationThread.php:18,43) | closed |
| T-01-30 | Tampering | Duplicate open conversations | low | mitigate | Reopen refusal plus partial unique index (ReopenConversation.php:39) | closed |
| T-01-SC (01-06) | Tampering | Package installs | high | accept | No manifest change; pest-plugin-livewire absent | closed |
| T-01-31 | Elevation of privilege / Spoofing | /dev/chat outside local | high | mitigate | Route middleware (web.php:23) plus abort_unless in mount() and send() (DevChat.php:35,43); DevChatTest | closed |
| T-01-32 | Tampering | Sync Now changing live config | medium | accept | Same validated FolderSync::run as icm:sync, behind panel auth | closed |
| T-01-33 | Tampering (XSS) | Error lines and notifications | medium | mitigate | e() per line in notifications (BusinessFolder.php:112); escaped Blade; BusinessFolderPageTest | closed |
| T-01-34 | Denial of service | Repeated Sync Now | low | mitigate | icm:sync lock via FolderSync; BusinessFolderPageTest busy case | closed |
| T-01-35 | Info disclosure | Seeded data | low | accept | Fictional names, 555-01xx numbers, example.com emails; SeederTest | closed |
| T-01-SC (01-07) | Tampering | Package installs | high | accept | No manifest change in 01-07 commits | closed |
| T-01-36 | Elevation of privilege | DatabaseSeeder default login | medium | mitigate | Seeder creates test@example.com / "password" and canAccessPanel is true; if seeded outside local it is a working panel login. MUST CLOSE BEFORE FIRST DEPLOY (seed the user only in local, or require a real password) | open — below high threshold (non-blocking) |
| T-01-37 | Spoofing | Text driver default | low | mitigate | `channels.text.driver` defaults to `fake` (config/channels.php:17), so the unauthenticated fake webhook is live unless TEXT_CHANNEL=twilio. MUST CLOSE BEFORE FIRST DEPLOY (default to a non-fake driver outside local; Phase 2 TXT-01) | open — below high threshold (non-blocking) |
| T-01-38 | Tampering | Validator echoes YAML keys and filenames into changelog.md unescaped | low | accept | See Accepted Risks Log | closed |
| T-01-39 | Tampering | Customer name in a Filament notification title (sanitized HTML, not escaped) | low | accept | See Accepted Risks Log | closed |
| T-01-40 | Tampering | Untested branches: RecordInbound race catch, ReopenConversation unique-index catch, webhook caps, thread escaping | low | accept | See Accepted Risks Log | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

T-01-36 to T-01-40 were raised by the security auditor outside the plan-time register on 2026-10-11.

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-01 | T-01-08 | The folder is edited only by the owner, their AI or Ian; no upload route exists; no size cap on the Loader by design | Plan 01-02 (planning) | 2026-10-10 |
| AR-02 | T-01-26 | Single-owner instance (D-24); every authenticated user is the owner; login required, no registration. Revisit with multi-user roles | Plan 01-06 (planning) | 2026-10-10 |
| AR-03 | T-01-32 | Sync Now runs the same validated FolderSync as the command and cannot bypass validation (D-10, D-24) | Plan 01-07 (planning) | 2026-10-10 |
| AR-04 | T-01-35 | Seed data is fictional with reserved 555-01xx numbers | Plan 01-07 (planning) | 2026-10-10 |
| AR-05 | T-01-SC (01-01, 01-02, 01-04, 01-05, 01-06, 01-07) | No package installed by these plans (verified against commits) | Plans (planning) | 2026-10-10 |
| AR-06 | T-01-38 | Low: changelog.md is written from trusted-operator folder content; a crafted key can only add a heading line | Ian | 2026-10-11 |
| AR-07 | T-01-39 | Low: Filament sanitizes the HTML; the name affects formatting only, behind panel auth | Ian | 2026-10-11 |
| AR-08 | T-01-40 | Low: the branches exist and are guarded by database constraints; tests to follow when the paths become live | Ian | 2026-10-11 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-10-11 | 47 | 45 | 2 (below high, non-blocking) | gsd-security-auditor (sonnet); T-01-01 fixed in 05c32e2/4569b3f |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-10-11 (blocking gate clear; T-01-36 and T-01-37 must close before first deploy)
