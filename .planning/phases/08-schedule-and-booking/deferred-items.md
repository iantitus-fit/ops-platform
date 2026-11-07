# Phase 8 deferred items

## From 08-13

- axe color-contrast on the contact screen's details list: the empty-value placeholder (`.fi-in-placeholder`, two nodes) fails the WCAG 2.2 AA contrast check on `/admin/contacts/{id}`. It is the Filament infolist above the relation-manager tabs, not the Appointments tab, so `tests/Feature/Filament/AppointmentsKeyboardTest.php` scopes axe to the tab and its open dialogs (`.fi-resource-relation-manager`, `.fi-modal-open`). To be fixed in the Phase 11 owner-screen pass (A11Y-03).
