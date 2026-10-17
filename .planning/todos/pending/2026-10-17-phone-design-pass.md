---
created: 2026-10-17T16:30:00Z
title: Phone design pass for the dashboard, the Needs you card and the Business Folder page
area: ui
severity: major
files:
  - app/Filament/Widgets/DraftsWaiting.php
  - app/Filament/Widgets/NewLeads.php
  - app/Filament/Widgets/PausedConversationsWaiting.php
  - app/Filament/Widgets/TodaysConversations.php
  - resources/views/livewire/conversation-thread.blade.php
  - app/Filament/Pages/BusinessFolder.php
  - resources/views/filament/pages/business-folder.blade.php
---

## Problem

Ian's phone walk in Phase 02.1 UAT test 1 (2026-10-17): the screens work but they don't look right. "On my phone these screens are confusing and as an owner I would not know what to do." Ian chose to record this as a design issue for a separate phone design pass, not as a 02.1 gap.

**Dashboard:**
- four tables of equal weight;
- "(no name)" rows that can't be told apart;
- columns cut off;
- nothing that says what to tap first.

**Needs you card:**
- Take over is the biggest button on the page;
- the draft box cuts off the text;
- when the "wrote again" badge shows, the "Customer wrote" label disappears and the badge, time and text run together;
- a reply a person approved and sent shows as "Front desk" instead of that person's name.

**Business Folder page:**
- The Offers warning is plain red text with no icon, and nothing points to the file to fix.
- The Front desk card repeats its own label ("Front desk: Front desk answers...") and shows model IDs.
- The fingerprint, "Not a git repository" and file types like "router" and "set_replies" are developer detail an owner doesn't need.

## Solution

Ian's layout call in the design pass.

Inputs:
- `docs/phase-2.2-owner-input.md` sections 5 and 6:
  - one urgency-ordered list, safety and complaints first;
  - a card per item showing who, what they asked, how long they've waited and one clear tap;
  - the thread shows who sent each message.
- `.planning/PROJECT.md` Frontend open items 1 to 8.

This reopens the 2026-10-11 decision (stock Filament with a brand pass; a UI-SPEC only for Phase 3's booking page). That call is Ian's.
