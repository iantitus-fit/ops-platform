---
created: 2026-10-17T16:30:00Z
title: Phone design pass for the dashboard, the Needs you card and the Business Folder page
area: ui
resolves_phase: 11
severity: major
files:
  - app/Filament/Widgets/DraftsWaiting.php
  - app/Filament/Widgets/NewLeads.php
  - app/Filament/Widgets/PausedConversationsWaiting.php
  - app/Filament/Widgets/TodaysConversations.php
  - resources/views/livewire/conversation-thread.blade.php
  - app/Filament/Pages/BusinessFolder.php
  - resources/views/filament/pages/business-folder.blade.php
  - app/Filament/Pages/Schedule.php
  - app/Enums/ActivityType.php
audit_acknowledged:
  milestone: v1.0
  at: 2026-10-20
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

**Draft hold reasons (02.1 review IN-02, added 2026-10-18):**
- "Held: no price on file" shows for holds that are not that: a service that only needs the customer's answers, a blank-words promise, a failed reading or an unsure person reading. For a service waiting on answers it should say so, such as "waiting on the customer's answers for X" (`RunFrontDesk::noPriceReason()`, `Verdict::plainReasonFor()`).

**Visual direction (Ian, 2026-11-11):**
- The platform has no design system yet. A full visual pass comes later and will change the look a lot; where it sits is settled in Phase 11's discuss-phase.
- Until then, a review finding that is only about looks (spacing, color, type size, column width, visual weight) is written here and not fixed in its phase (CLAUDE.md, Rules; PROJECT.md Frontend).
- These still gate a phase: wording, what each screen shows and in what order, a customer page fitting a phone with no sideways scroll, keyboard use, focus, screen-reader names, contrast and the automated accessibility check.

**From Phase 8 UAT check 7 (2026-11-11):**
- The Schedule table scrolls sideways at a normal laptop window width, with the What column squeezed (`app/Filament/Pages/Schedule.php`).
- The automatic-texts timeline badges show the raw type value (`automatic_texts_turned_on`, as 08 D-46 specifies), while other entries show a plain label (`app/Enums/ActivityType.php`).

**Not a look item (2026-11-21):** the inline `#dc2626` on refusal, warning and error lines fails WCAG 2.2 AA contrast in dark mode; a Phase 11 must-fix, recorded in `.planning/ROADMAP.md`, Phase 11 planning notes.

## Solution

Ian's layout call in the design pass.

Inputs:
- `docs/phase-2.2-owner-input.md` sections 5 and 6:
  - one urgency-ordered list, safety and complaints first;
  - a card per item showing who, what they asked, how long they've waited and one clear tap;
  - the thread shows who sent each message.
- `.planning/PROJECT.md` Frontend open items 1 to 8.

This reopens the 2026-10-11 decision (stock Filament with a brand pass; a UI-SPEC only for Phase 3's booking page). That call is Ian's.
