# Phase 09 deferred items

Out-of-scope discoveries logged during execution. Not fixed in the phase plan that found them.

## 09-01

- `tests/Feature/Booking/BookingPageBrowserTest.php`: 5 Pest browser tests fail on the local machine (2026-11-16):
  the smallest-control measure at 375 px and at desktop width and the axe target-size check on the picker and check
  step (booking time buttons measure 21 px high), and the footer disclosure hover border reads `rgb(0, 0, 0)`. A
  rebuild with `npm run build` did not change the result. No file in plan 09-01 (GateExport, VisitAskedAbout,
  SendOwnerReply, their tests, BLUEPRINT) touches the booking page, its views or its CSS. Recorded as WINDOWS.md entry
  81 (unrun-verify): confirm the same tests pass on CI before ship.
