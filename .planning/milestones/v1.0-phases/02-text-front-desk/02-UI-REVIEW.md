# Phase 02 — UI Review

**Audited:** 2026-10-12
**Baseline:** abstract 6-pillar standards (no UI-SPEC.md)
**Screenshots:** not captured (no dev server on 3000/5173/8080; code-only audit)
**Interaction captures:** off (workflow.ui_interaction_capture is false). Interaction findings are code-derived.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | Plain, specific copy; generic "Send" x3 and unlabeled composer textareas |
| 2. Visuals | 2/4 | Thread and Needs-you block rely on 32 inline styles; draft block has no emphasis; two textareas look alike |
| 3. Color | 2/4 | Hardcoded rgba/hex in thread and error text; amber used for both Front desk and warning badges |
| 4. Typography | 3/4 | Little type variety; mostly default sizes; one repeated 0.875rem inline |
| 5. Spacing | 2/4 | All spacing in inline rem styles, not Filament/Tailwind scale; inconsistent 0.25/0.5/0.75/1rem mix |
| 6. Experience Design | 2/4 | Good draft-race handling; no wire:loading or double-submit guard, no Discard confirm, poll can reset UI, opt-out display not found |

**Overall: 14/24**

---

## Top 3 Priority Fixes

1. **Discard and Send on a held draft have no loading/disabled state and Discard has no confirmation** (`resources/views/livewire/conversation-thread.blade.php:21-24`). The owner can double-click Send (a duplicate outbound text is possible if the action is not idempotent) and discard a customer-facing reply by accident. Fix: add `wire:loading.attr="disabled"` and `wire:target` on both buttons; add `wire:confirm` to Discard; use `<x-filament::button>` loading indicator.
2. **Needs-you block is visually the same as a neutral section** (`conversation-thread.blade.php:3`). It is the single most important action on the page but has no color, icon or border emphasis, and sits above a timeline with the same textarea styling as the reply box. Fix: give the section `icon="heroicon-o-exclamation-triangle"` and `color="warning"`, label the draft textarea ("Edit before sending"), and make the primary button read "Send this reply" while the reply box reads "Send reply".
3. **Replace inline styles and hardcoded colors with Filament/Tailwind tokens** (32 `style=` attributes across `resources/views`; `#dc2626` at conversation-thread.blade.php:19 and :63, business-folder.blade.php alerts, dev-chat; `rgba(...)` bubbles at :45-47). Hardcoded values ignore dark mode, theme changes and Filament's own danger/warning palette. Fix: use `text-danger-600 dark:text-danger-400`, `bg-gray-100 dark:bg-white/5`, `bg-warning-50`, `bg-success-50`, `gap-2`, etc., or a small CSS class set in the panel theme.

Additional (lower) fixes:
4. Composer textareas have no `<label>`/`aria-label` (conversation-thread.blade.php:19, 71, 85); a screen reader hears two unnamed edit fields. Add `aria-label="Edit the draft"` and `aria-label="Reply"`.
5. Message author is conveyed by background tint and left/right alignment, with Front desk (amber) and You (green) differing only by hue. The text label is present (good), but tint colours fail on dark backgrounds and for colour-blind users; add a left border or icon per author.
6. The thread poll (`wire:poll.5s`) re-renders the whole component including the draft form; confirm that typing in `draftBody` is not clobbered (the code protects against a new draft but not against focus/scroll loss). `x-init` scroll-to-bottom runs only once, so new messages do not scroll into view while the owner is reading. Add `wire:model.live.debounce` or `wire:ignore` on the form, and scroll-on-new-message only when already at bottom.
7. Opt-out display (plan 02-24) and the Front desk vs Owner customer-card labels (plan 02-15): no opt-out indicator found in `app/Filament`, `app/Livewire` or `resources/views` (grep for opt/STOP returned nothing). The Customer section in ConversationInfolist shows name, phone, email and status only, and is collapsed by default. If the opt-out state is only a timeline event summary, an opted-out customer is not visibly flagged near the reply box. Verify and add a badge on the Customer section header and in the reply-blocked sentence.
8. Mobile (375px): the 4-column Customer and Contact sections and 3-column timeline rely on Filament's default responsive collapse; thread bubbles use `max-width: 85%` and `max-height: 60vh`, which on a phone keyboard leaves very little scroll area above the composer. Not verified visually.

---

## Detailed Findings

### Pillar 1: Copywriting (3/4)
- Strengths: plain owner-voice copy. "Needs you", "The assistant wrote:", "Customers waiting while you handle it", "No one is waiting on you", "No drafts waiting", "Not alerted: No phone on file. Add your phone on your profile." (ConversationThread.php draftNotAlerted), "Handed back to the assistant", "You're handling this conversation". Empty and error states exist with next steps.
- Weak: three buttons labelled bare "Send" (draft form, reply form, disabled blocked form; blade lines 22, 76, 89) with no distinction; the sign-off hint "The sign-off (and the AI note on a first text) is added when it sends." is jargon-light but easy to miss at 0.875rem.
- Inconsistent terms: "Front desk" (bubble label) vs "the assistant" (banner, notifications, "Hand back to the assistant"). Pick one.
- Customer fields use placeholders "(no name)", "(no phone)": acceptable.
- Business Folder: "Click Sync Now" with no matching button visible in this view (button is presumably a header action), and "Fingerprint abcdef123456 · Not a git repository" is developer-facing text on an owner page.
- Profile: "Use a real-format number like +12025550123." is helpful but slightly awkward.

### Pillar 2: Visuals (2/4)
- No clear focal point on the conversation view: Needs-you section, paused banner and thread are all `<x-filament::section compact>` or bare divs with identical weight.
- The paused banner (blade :35-38) is plain text in a section with no icon or colour; it is the state that stops the assistant replying.
- Status badges on outbound messages are good (danger/warning/success mapping, :55-61), but the badge shows the raw enum value (e.g. "delivered") lowercase.
- Icon-only buttons: none found (all buttons have text). No aria problems there.
- Dashboard: DraftsWaiting table row links to the conversation; no visual cue that rows are clickable beyond Filament default. Recommend a View action.
- Disabled composer (blocked reason) shows a disabled textarea and disabled Send under the sentence, which is useful context but duplicates controls; a plain notice would be cleaner.

### Pillar 3: Color (2/4)
- Hardcoded: `#dc2626` (3 uses: thread errors x2, business-folder alerts/errors x2, dev-chat x2), `rgba(128,128,128,0.15)`, `rgba(245,158,11,0.18)`, `rgba(34,197,94,0.18)` for bubbles. All bypass the Filament palette and dark mode.
- Amber is used for Front desk bubbles AND warning badges (Queued/Draft, "Not alerted") AND the Take over action (`color('warning')`), so amber means three different things.
- Green is both "You" bubble and Sent/Delivered success; ConversationsTable uses `Color::Green` for Open.
- Accent distribution is otherwise restrained: Filament default primary on primary buttons only.

### Pillar 4: Typography (3/4)
- No custom font sizes beyond a repeated inline `0.875rem` (about 8 uses) plus Filament defaults; one weight (`<strong>` bold) in use. Hierarchy comes from Filament section headings, which is fine.
- Weak: help text in the draft form and message metadata both use 0.875rem with no colour de-emphasis (no `text-gray-500`), so metadata, helper and body compete.
- dev-chat uses raw `<h1>` and unstyled inputs (dev-only, low priority).

### Pillar 5: Spacing (2/4)
- All layout spacing is inline: gaps 0.25, 0.5, 0.75rem, margins 1rem, padding 0.5rem/0.75rem. No Tailwind scale classes are used in these views, so they will drift from Filament's own spacing.
- Textareas use `padding: 0.5rem 0.75rem` inline while `fi-input` already provides padding for inputs, risking double padding.
- `max-height: 60vh` on the thread combined with sections above and a composer below can exceed the viewport on small screens.

### Pillar 6: Experience Design (2/4)
- Strong: draft race handling (D-28: stale draft refused, owner edit preserved), locked `draftId`, error under box for validation, failed sends keep words, Close conversation requires confirmation, Take over/Hand back have visibility predicates, reply box replaced with a reason when blocked, 5s polling for live updates, empty states for thread and all widgets, notifications escape exception text with `e()`.
- Missing: no `wire:loading`/disabled-during-submit anywhere in the thread view (double send risk); Discard draft has no confirmation; no skeleton/loading for the thread; no error state if polling fails; Take over has no confirm though it pauses the assistant (hand back exists, so acceptable).
- Alert-link landing (02-23) relies on the conversation view directly; Needs-you block is the right target but is not scrolled to or highlighted.
- Opt-out display not found in the audited files (see fix 7).
- Registry audit: no `components.json`; skipped.

---

## Files Audited
- resources/views/livewire/conversation-thread.blade.php
- resources/views/filament/pages/business-folder.blade.php
- resources/views/livewire/dev-chat.blade.php
- app/Livewire/ConversationThread.php
- app/Filament/Resources/Conversations/Pages/ViewConversation.php
- app/Filament/Resources/Conversations/Schemas/ConversationInfolist.php
- app/Filament/Resources/Conversations/Tables/ConversationsTable.php
- app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
- app/Filament/Widgets/DraftsWaiting.php, PausedConversationsWaiting.php, NewLeads.php
- app/Filament/Pages/Auth/EditProfileWithPhone.php
