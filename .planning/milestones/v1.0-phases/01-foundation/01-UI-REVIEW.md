# Phase 01 — UI Review

**Audited:** 2026-10-11
**Baseline:** abstract 6-pillar standards (no UI-SPEC.md), plus plans 01-06 and 01-07 and 01-CONTEXT. Filament screens judged against what Filament conventions make achievable.
**Screenshots:** not captured (no dev server on ports 3000, 5173, 8080 or 8000). Code-only audit.
**Interaction captures:** off (`interaction_capture: false`).

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | Clear owner-readable sentences; raw enum values and developer jargon leak into badges and the Business Folder page |
| 2. Visuals | 2/4 | Thread has no inbound/outbound distinction; Business Folder is plain paragraphs; dev-chat unstyled |
| 3. Color | 2/4 | Hardcoded `#dc2626` ignores theme and dark mode; Contacts badges all default gray |
| 4. Typography | 3/4 | Mostly inherits Filament's scale; dev-chat and layout use bare headings |
| 5. Spacing | 2/4 | 18 inline `style=` attributes with ad-hoc rem values, outside any scale |
| 6. Experience Design | 2/4 | Good refusal and failure states; no sending/loading state (double-send risk), no list search, transient error notification |

**Overall: 14/24**

---

## Top 3 Priority Fixes

1. **Thread does not distinguish customer from owner** — the owner's core task is reading and replying; only the author name differs, and failed sends show no reason — give inbound and outbound distinct treatment (alignment or tinted container using Filament tokens) and show `meta.error` under failed messages (`resources/views/livewire/conversation-thread.blade.php:2-20`). Classified BLOCKER-level for task clarity.
2. **No loading or disabled state on Send** — a double-click can text a real customer twice — add `wire:loading.attr="disabled"` and `wire:target="sendReply"` (with a spinner) on the Send button (`conversation-thread.blade.php:34-44`).
3. **Hardcoded `#dc2626` and inline styles bypass the Filament theme** — breaks dark mode and the panel's `->colors([...])` — replace with Filament classes/components or `var(--danger-600)` (`conversation-thread.blade.php:39`, `business-folder.blade.php:42`, `dev-chat.blade.php:23,29`).

Further fixes:
4. Make the Rejected sync notification `->persistent()` (`app/Filament/Pages/BusinessFolder.php:111-113`).
5. Add `HasLabel` and `HasColor` to the status and activity-type enums in `app/Enums`.
6. Add search to the Conversations list (`ConversationsTable.php:18-22`).

---

## Detailed Findings

### Pillar 1: Copywriting (3/4)
Strengths: "Type a message first.", "This conversation is closed. Reopen it to reply.", "No version is live yet. Click Sync Now."; sync outcome copy and error lines name the file and field.

Gaps (WARNING):
- `conversation-thread.blade.php:19` and `dev-chat.blade.php:15`: bare "No messages yet." with no next step.
- `business-folder.blade.php:15`: "Fingerprint 3fa9c1d2e4b7 · Not a git repository" is developer jargon on an owner page.
- Raw enum values display: thread status badge shows `sent`/`failed` (`conversation-thread.blade.php:13`); Contacts and Conversations status badges and the timeline `type` badge render values such as `text_received`. No enum in `app/Enums` implements `HasLabel`.
- "Send" is generic; "Send text" says what happens.

### Pillar 2: Visuals (2/4)
- WARNING: thread has no focal hierarchy or inbound/outbound distinction (see fix 1).
- WARNING: `dev-chat.blade.php` is unstyled (browser-default input, textarea, button, bare `<h1>`). Local-only audience, but looks broken.
- WARNING: Business Folder is plain `<p>` lines. Files render as `path · type` text, not a table or badges, so they are hard to scan at scale. Last sync has no status icon or badge.
- WARNING: contact timeline puts message body in a 1-of-3 column (`ContactInfolist.php:71`, `->columns(3)`); long customer texts will be cramped. Add `columnSpanFull()` to the body entry.
- Disabled textarea at `conversation-thread.blade.php:27` has no accessible label. No icon-only buttons found.

### Pillar 3: Color (2/4)
- Hardcoded `#dc2626` appears 4 times (thread error, Business Folder error lines, dev-chat x2). It ignores dark mode and the panel palette (`AdminPanelProvider.php:31`).
- Rejected error lines on the Business Folder use color alone as the cue, with no icon or text label (`business-folder.blade.php:42`).
- Conversations status badge sets green/gray explicitly (`ConversationsTable.php:25-28`); Contacts status and Source badges do not, so lead and customer are both default gray. Inconsistent between lists.
- No accent overuse; primary is Filament default.

### Pillar 4: Typography (3/4)
- Custom text uses only `font-size: 0.875rem` and `<strong>` (one extra weight); everything else inherits Filament's scale.
- WARNING: `dev-chat.blade.php` and `layouts/app.blade.php` use bare `<h1>` and system-ui with no heading styles.

### Pillar 5: Spacing (2/4)
- 18 inline `style=` attributes: 8 in the thread, 6 in dev-chat, 4 in the Business Folder page. Ad-hoc rem values (0.25, 0.5, 0.75, 1rem), no shared scale.
- The plan notes Filament's compiled CSS lacks utilities such as `whitespace-pre-line`, which explains the inline styles. But `conversation-thread.blade.php:16` carries both the class and the inline style, and no theme build was added.
- The thread and reply box use a different rhythm from the surrounding Filament sections.

### Pillar 6: Experience Design (2/4)
Covered well: refusal states (closed, no phone, empty, over-length), failed-send handling, Close with confirmation, Reopen hidden when another open conversation exists, busy-lock notification, empty states, 5s and 2s polling.

Gaps (WARNING):
- No loading/disabled state on Send (fix 2).
- Rejected-sync danger notification auto-dismisses (`BusinessFolder.php:111`); errors do persist in the Last sync section, which mitigates this.
- Conversations list has no search; Customer and Phone are not `searchable()`. The "last message" column shows only a relative time, not a preview.
- Thread has no scroll-to-latest; in a long thread the reply box sits far below the newest message.
- Contacts "Cost per lead" and "Source" columns have no null placeholder, so a blank is ambiguous next to a real `$0.00`.
- Layout and polling have never been rendered in a browser. The plan's human-check is pending in `.planning/WINDOWS.md`, so these findings are code-derived.

Registry audit: skipped (no third-party registries listed in the plans).

---

## Files Audited
- resources/views/livewire/conversation-thread.blade.php
- resources/views/livewire/dev-chat.blade.php
- resources/views/layouts/app.blade.php
- resources/views/filament/pages/business-folder.blade.php
- app/Filament/Pages/BusinessFolder.php
- app/Livewire/ConversationThread.php
- app/Filament/Resources/Contacts/Tables/ContactsTable.php
- app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
- app/Filament/Resources/Conversations/Tables/ConversationsTable.php
- app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
- app/Filament/Resources/Conversations/Pages/ViewConversation.php
