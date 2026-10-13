---
status: diagnosed
trigger: "Gap G-02-1.1: the thread under the Needs you card starts at the oldest message, not the latest (D-29 says it opens at the latest). Seen on a real phone after tapping an owner-alert link to a conversation with a held draft."
created: 2026-10-12T00:00:00Z
updated: 2026-10-12T00:00:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: CONFIRMED. The "opens at latest" mechanism scrolls only the inner 60vh box. At phone width that box starts below the fold (y=526) behind the Needs you card, the page itself is never scrolled, and a thread shorter than the box has nothing to scroll. So what the owner sees under the card is the top of the thread (oldest) or the middle of it, never the bottom where the latest message is.
test: headless Chrome at iPhone sizes against a scratch copy of the app (scratch sqlite DB, php -S on 127.0.0.1:8765), reading scrollTop and per-message visibility after load and after a poll
expecting: n/a, diagnosis complete
next_action: hand root cause to plan-phase --gaps

## Symptoms

expected: The thread under the Needs you card opens at the latest message (D-29, 02-CONTEXT.md line 65: "the thread opens at the latest message").
actual: "the thread under the Needs you card starts at the oldest message, not the latest" (real phone, over Wi-Fi, after tapping an owner-alert link to a conversation with a held draft).
errors: none reported
reproduction: Test 1 in 02-UAT.md: trigger a held draft from /dev/chat, open the owner-alert link on a phone.
started: found in Phase 2 UAT, 2026-10-12. Feature added in commit 9a097cf (plan 02-23).

## Eliminated

- hypothesis: the x-init scroll-to-bottom never runs (Alpine not initialised inside the nested Livewire component)
  evidence: in a 14-message thread at 390x844 and 390x664 the box ended with scrollTop 1001 / 1109, atBottom true, Alpine present. It runs.
  timestamp: 2026-10-12

- hypothesis: the 5-second wire:poll morph resets the box to the top
  evidence: probe at t+2.5s, t+5.5s and t+11.5s (after at least one poll) gave identical scrollTop 1001.
  timestamp: 2026-10-12

- hypothesis: messages are ordered newest-first or the wrong way round
  evidence: Conversation::messages() is orderBy created_at, id ascending (app/Models/Conversation.php:73); DOM order in the probe was 1..14 ascending. Bottom of the box is the latest.
  timestamp: 2026-10-12

## Evidence

- timestamp: 2026-10-12
  checked: resources/views/livewire/conversation-thread.blade.php
  found: Needs you section is lines 2-32 (above the thread). The thread box is line 40: `<div x-init="$el.scrollTop = $el.scrollHeight" style="display:flex; flex-direction:column; gap:0.75rem; max-height:60vh; overflow-y:auto;">`. That attribute is the only scroll code in app/ or resources/ (grep scrollTop|scrollHeight|x-init).
  implication: "latest" depends entirely on scrolling the inner box. Nothing scrolls the page and nothing anchors the latest message in view.

- timestamp: 2026-10-12
  checked: tests/Feature/Filament/ConversationViewTest.php lines 80-86
  found: the D-29 test only asserts the rendered HTML contains the strings `x-init="$el.scrollTop = $el.scrollHeight"`, `max-height: 60vh`, `overflow-y: auto`. It never runs JS or measures layout.
  implication: the behaviour was never verified; the plan's real-phone check was a human-check recorded as unrun (02-23-SUMMARY.md line 223, .planning/WINDOWS.md).

- timestamp: 2026-10-12
  checked: browser probe, 14-message thread (conv 1) at 390x844 and 390x664
  found: box top at y=526 CSS px, page scrollY 0. Inner box scrolled to bottom, but the in-view slice shows only messages 10-13 (844) or 11-12 (664) of 14. Latest message not visible. Send/Discard bottom at y=494, so the Needs you acceptance holds.
  implication: even when x-init works, the visible part of the box is its upper slice, because the lower part of the box (where the latest message is) is below the fold.

- timestamp: 2026-10-12
  checked: browser probe, short threads of 2, 3 and 6 messages at 390x664 (and 2 messages at 390x844)
  found: scrollHeight == clientHeight (145 / 193 / 399 vs 145 / 193 / 398), scrollTop 0, so the assignment is a no-op. Message 1 (oldest) is the first thing in view directly under the Needs you card. Latest not fully visible at 664 for all three; for the 2-message thread it is fully visible only at 844.
  implication: for the short threads typical of a fresh /dev/chat text the thread literally starts at the oldest message and the latest sits below the fold. This is exactly the UAT report.

- timestamp: 2026-10-12
  checked: read-only query of database/database.sqlite messages grouped by conversation
  found: UAT conversations 9, 11, 12, 13 have 3 messages each including one draft (so 2 in the thread); Dana's conversation 1 has 6 and no live draft. None of them overflow a 60vh box on a phone by much.
  implication: the real UAT threads were short, the no-op branch of the mechanism.

- timestamp: 2026-10-12
  checked: D-29 acceptance criteria in 02-CONTEXT.md line 65
  found: two criteria in one line: (a) held reason, draft and Send visible without scrolling, (b) the thread opens at the latest message. The layout puts the thread after the Needs you block (a), so the thread's bottom is necessarily below the fold at phone width unless something else carries the latest message into view.
  implication: the two criteria are in tension under the chosen layout. The deferred idea (show the customer's last message in the Needs you card) addresses exactly this.

## Resolution

root_cause: The only "open at latest" mechanism is `x-init="$el.scrollTop = $el.scrollHeight"` on the 60vh thread box (resources/views/livewire/conversation-thread.blade.php:40). It works (Alpine runs it, a poll does not undo it), but it can only scroll inside the box. It fails to deliver "the thread opens at the latest message" for two reasons that combine: (1) the box sits after the Needs you card and the page header, starting about 526px down a phone screen, and the page is never scrolled (and cannot be without pushing Send out of view, which D-29 forbids), so only the top 140-320px of the box is in view and the latest message, at its bottom, is below the fold; (2) for a thread shorter than the box, which is every fresh /dev/chat conversation (2-6 messages), the box does not overflow, so the scroll is a no-op and the owner sees the oldest message first. The D-29 test asserts only that the attribute string is in the HTML, so nothing caught it.
fix: (not applied, diagnose-only)
verification: n/a
files_changed: []
