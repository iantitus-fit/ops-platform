# Phase 8 deferred items

## From 08-13

- axe color-contrast on the contact screen's details list: the empty-value placeholder (`.fi-in-placeholder`, two nodes) fails the WCAG 2.2 AA contrast check on `/admin/contacts/{id}`. It is the Filament infolist above the relation-manager tabs, not the Appointments tab, so `tests/Feature/Filament/AppointmentsKeyboardTest.php` scopes axe to the tab and its open dialogs (`.fi-resource-relation-manager`, `.fi-modal-open`). To be fixed in the Phase 11 owner-screen pass (A11Y-03).

## From 08-14

- `tests/Feature/Booking/BookingLinkTokenTest.php`, the "every day blocked" case of "resolves each state a link can be in": it blocks 2026-11-03 to 2026-11-20 and resolves the link at the real clock, so it started failing once the real date reached 2026-11-07 (the booking page window then reaches past the block's end and a picker is offered, not "no times"). It does not touch this plan's code (the failure reproduces on its own and on the 08-13 tip's logic); the fix is to pin the clock in that test with `travelTo`. Not fixed here (out of scope).

## From 08-25

- `tests/Feature/Booking/BookingPageBrowserTest.php` is timing-flaky under the load of a whole-suite or broad run: in one run 'books by keyboard at 375 wide' failed (the page title read 'BODY' where 'You are booked' was expected, Pest.php:580), in the next 'normalizes a non-breaking space in a name' failed on Playwright's 5000ms timeout. Each is a different case, each passes when the file runs alone (20 of 20, three times), and plan 08-25 changes nothing the customer's booking page uses. A fixed wait or a longer locator timeout in those cases would likely settle it; left for Ian.
