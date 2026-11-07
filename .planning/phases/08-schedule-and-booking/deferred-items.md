# Phase 8 deferred items

## From 08-13

- axe color-contrast on the contact screen's details list: the empty-value placeholder (`.fi-in-placeholder`, two nodes) fails the WCAG 2.2 AA contrast check on `/admin/contacts/{id}`. It is the Filament infolist above the relation-manager tabs, not the Appointments tab, so `tests/Feature/Filament/AppointmentsKeyboardTest.php` scopes axe to the tab and its open dialogs (`.fi-resource-relation-manager`, `.fi-modal-open`). To be fixed in the Phase 11 owner-screen pass (A11Y-03).

## From 08-14

- `tests/Feature/Booking/BookingLinkTokenTest.php`, the "every day blocked" case of "resolves each state a link can be in": it blocks 2026-11-03 to 2026-11-20 and resolves the link at the real clock, so it started failing once the real date reached 2026-11-07 (the booking page window then reaches past the block's end and a picker is offered, not "no times"). It does not touch this plan's code (the failure reproduces on its own and on the 08-13 tip's logic); the fix is to pin the clock in that test with `travelTo`. Not fixed here (out of scope).
