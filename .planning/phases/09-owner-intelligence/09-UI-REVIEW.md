# Phase 09 — UI Review

**Audited:** 2026-11-17
**Baseline:** 09-UI-SPEC.md (stock Filament 5, wording/order/a11y gate; looks deferred per CLAUDE.md visual-direction rule)
**Screenshots:** not captured (code-only audit; no dev-server capture was run, and the .gitignore gate and capture step were skipped for this code-only pass)
**Interaction captures:** off (workflow.ui_interaction_capture is false)

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 4/4 | Every contract string matches the code verbatim; one shared formatter |
| 2. Visuals | 3/4 | Right structure and order; no visual check on a phone was possible, owner walk still open |
| 3. Color | 3/4 | No color, hardcoded or otherwise, added; nothing to violate. Not scored 4 as unverified rendered contrast |
| 4. Typography | 3/4 | No type classes added; plain `<p>` and `TextEntry`. Unverified visually |
| 5. Spacing | 3/4 | Reuses shared section view; `columns(4)` inherited. Unverified at 375 wide |
| 6. Experience Design | 3/4 | States covered and tested; contact-screen failure is silent to the owner by design; UAT walk open |

**Overall: 19/24**

No BLOCKER findings. Looks-only items (pillars 2 to 5) are deferred to `.planning/todos/pending/2026-10-17-phone-design-pass.md`, not phase defects.

---

## Top 3 Priority Fixes

1. **Run the keyboard-only and VoiceOver walk and the phone-width check (WINDOWS #83)** — the spec's "backstop" rows for sideways scroll, focus order and screen-reader names are only code-asserted; run them at UAT and record the result. No code change expected.
2. **Record the looks-only gaps in the phone design pass todo** — the contact line sits inside a 4-column Section (`ContactInfolist.php:65`) next to up to three conditional entries; check how it wraps at 375 px and note any look issue in `2026-10-17-phone-design-pass.md` (do not fix in this phase).
3. **Consider a visible note when the contact line fails to build** — `timeToBookedLine()` (`ContactInfolist.php:141-145`) logs and hides the line, so the owner cannot tell "no number" from "broke". This is per the approved contract (Ian, 2026-11-14), so it is a recommendation only; raise at Phase 11's wording pass if wanted.

---

## Detailed Findings

### Pillar 1: Copywriting (4/4)
- Label `'Time to booked'` at `ContactInfolist.php:57` matches the contract.
- `TimeToBooked::EMPTY_LINE` (`TimeToBooked.php:31`) is exactly "No new customers booked in the last 30 days."
- `summaryLine()` (`TimeToBooked.php:99-104`) produces all four contract variants: "Half of your N new customers ... booked within X", "Your 1 new customer ...", and the "booked in under a minute" swap that avoids "within under a minute".
- `DurationText` (`DurationText.php`) implements the D-06 thresholds (90 minutes, 48 hours), singular forms, "under a minute", and drops zero parts. Unit test exists (`tests/Unit/OwnerIntelligence/DurationTextTest.php`).
- Failure wording is the shared `RoomSection::FAILED_LINE`; the section heading is `ucfirst('owner intelligence')` giving "Owner intelligence" (provider label at `OwnerIntelligenceServiceProvider.php:25`).
- No exclamation points or em dashes seen in the new strings. No buttons or forms, so no CTA copy to audit.

### Pillar 2: Visuals (3/4)
- Contact entry is placed after "Next appointment" and before "Automatic texts" (`ContactInfolist.php:56-60`), as specified; covered by test "places it after Next appointment and before Automatic texts".
- Section sits between money and schedule via default sort 20 and room-key order; covered by the boot test at `OwnerIntelligenceBootTest.php:178`.
- No icon-only controls were added, so no aria-label gap.
- Warning: rendered appearance was not seen (no screenshots). Looks-only; deferred.

### Pillar 3: Color (3/4)
- The phase adds no color classes, hex values or custom CSS; both surfaces are plain Filament text. Spec says the line is text, not color or icon, so nothing relies on color to convey meaning.
- Warning: contrast is Filament's default and was not measured here. The axe test in the boot test (`OwnerIntelligenceBootTest.php:193`, via `RoomSectionAxe`) runs with and without a qualifying contact, which covers the section but not the contact screen (the contract says the contact screen has no axe check of its own).

### Pillar 4: Typography (3/4)
- No font size or weight classes introduced; section lines are bare `<p>` in `resources/views/filament/rooms/section.blade.php`, same as sibling rooms. Within contract.
- Warning: the section renders each line as an unstyled `<p>`, so the summary has no emphasis on the number. Looks-only; deferred.

### Pillar 5: Spacing (3/4)
- No spacing classes or arbitrary values added. The shared view is reused rather than a new one.
- Warning: phone-width wrapping of the 4-column contact Section and the section line is a backstop in the contract and unverified here. A sideways scroll at phone width would be a gating finding if the UAT walk shows it.

### Pillar 6: Experience Design (3/4)
- State coverage matches the contract table and has tests: no number (no line), room off then on, build throws (entry left out and reported, `try/catch` with `report()`), empty section, failing section line (`OwnerIntelligenceBootTest.php:158`), customer card unchanged.
- Room-off check uses `ActiveConfig::hasRoom()` as required; `RoomSection::canView()` hides the section.
- Both surfaces are read-only; no destructive action, so no confirmation needed. Loading state dismissed in the spec.
- Warning: on the contact screen a failed build and "no number" look the same to the owner (`ContactInfolist.php:141-145`). Accepted in the contract; logged only.
- Warning: WINDOWS #83 (keyboard and VoiceOver walk) is open, and several Postgres CI checks (#82, #84 to #87) are open. These are recorded, not defects.
- Registry audit: shadcn not initialized, no third-party registries; skipped.

---

## Files Audited
- app/Rooms/OwnerIntelligence/OwnerIntelligenceSection.php
- app/Rooms/OwnerIntelligence/DurationText.php
- app/Rooms/OwnerIntelligence/TimeToBooked.php
- app/Rooms/OwnerIntelligence/OwnerIntelligenceServiceProvider.php
- app/Rooms/RoomSection.php
- app/Filament/Resources/Contacts/Schemas/ContactInfolist.php
- resources/views/filament/rooms/section.blade.php
- tests/Feature/Rooms/OwnerIntelligenceBootTest.php
- tests/Feature/Filament/ContactTimeToBookedTest.php
- tests/Unit/OwnerIntelligence/DurationTextTest.php
- .planning/WINDOWS.md (entries 80 to 88)
- 09-UI-SPEC.md
