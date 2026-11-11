---
status: investigating
trigger: "UAT G-08-1: the Space key does nothing on any expandable section of the booking page (day headings and footer Privacy notice / Accessibility statement); Enter works."
created: 2026-11-11T00:00:00Z
updated: 2026-11-11T00:00:00Z
goal: find_root_cause_only
outcome: INVESTIGATION INCONCLUSIVE (app code exonerated; cause is outside the repo or in native-event handling that could not be reproduced)
---

## Current Focus

hypothesis: Something that is NOT the booking page's own code calls preventDefault() on Space keydown or keyup in the user's Chrome (a browser extension content script, a key remapper, or similar). This is the only mechanism found that reproduces the exact symptom "Space does nothing, Enter works" (see Evidence 6).
test: ask the user to press Space on a summary in (1) an Incognito window with extensions off, (2) a plain details page such as MDN's <details> example in the same normal Chrome, and (3) run a DevTools snippet that logs defaultPrevented for Space keydown/keyup.
expecting: Space works in Incognito -> an extension is the cause, no app change. Space fails on MDN too -> browser/OS-level, no app change. Space fails only on the booking page even in Incognito -> something page-specific in the user's real environment (dev server response) that headless Chrome 157 does not show; then capture getEventListeners(window/document) in DevTools.
next_action: report INVESTIGATION INCONCLUSIVE to the caller with the three user-side checks; do not edit app code.
bug_class: Bohrbug on the user's machine, not reproducible in synthetic-input Chrome 157 or bundled Chromium 153 (so SBFL / bisect have nothing to localise)

## Symptoms

expected: Every expandable section on the booking page (day headings, footer Privacy notice and Accessibility statement) opens and closes from the keyboard with Space as well as Enter.
actual: Space does nothing on any expandable section, both the day headings and the footer's. Enter works on all of them.
errors: none reported
reproduction: UAT test 1 (.planning/phases/08-schedule-and-booking/08-UAT.md, gap G-08-1). Chrome on macOS, desktop and 375 px.
started: discovered during UAT 2026-11-09

## Eliminated

- hypothesis: an app-authored keydown/keyup/keypress handler or preventDefault swallows Space (layout, app.js, Alpine x-on, Livewire wire:keydown)
  evidence: grep of resources/views, resources/js, app/Livewire finds none; git log -S"keydown" and -S"preventDefault" over resources/ finds none in any commit; resources/js/app.js is an empty "//" file; rendered page has no inline on* handlers, no contenteditable, no inert.
  timestamp: 2026-11-11
- hypothesis: Livewire 4.4.7 / Alpine runtime registers a global Space handler
  evidence: livewire.js has only focus-trap keydown listeners (active only under x-trap, unused) and the wire:navigate Enter-only link handler (isNotPlainEnterKey). A census of addEventListener("key*") calls made by page scripts after load in real Chrome 157 returned an empty list. At window bubble phase every Space keydown/keypress/keyup had defaultPrevented=false.
  timestamp: 2026-11-11
- hypothesis: CSS/markup (display:flex on summary, list-none, min-h-12, transition on chevron, group-open) stops Chromium's native Space activation
  evidence: Space toggles the real booking page's day and footer summaries in Chrome 157.0.8103.50 (installed Google Chrome, new headless) and in Playwright's bundled Chromium 153, with and without a 100-150 ms key hold, Tab-focused and .focus()-focused, with the pointer hovering, with a mouse move between keydown and keyup, after a Livewire round trip, under --force-renderer-accessibility, --force-prefers-reduced-motion and device scale 2.
  timestamp: 2026-11-11
- hypothesis: Laravel Boost's local-only injected browser-logger script intercepts Space
  evidence: BrowserLogger.php registers only error, unhandledrejection and beforeunload listeners and patches console methods and window.onerror; no key listener.
  timestamp: 2026-11-11
- hypothesis: wire:ignore.self / Livewire morph re-renders the day <details> and undoes the toggle
  evidence: the footer disclosures sit outside the Livewire component and fail too; and Space toggles days correctly in the probe.
  timestamp: 2026-11-11

## Evidence

- timestamp: 2026-11-11
  checked: resources/js/app.js, layouts/customer.blade.php, livewire/booking-page.blade.php, components/booking/{day,footer,time,button,notice,error}.blade.php, resources/css/app.css
  found: no key handler anywhere. Days and footer disclosures are plain <details><summary class="flex ... list-none ..."> with no interactive content inside the summary. Only Alpine uses are x-on:click on time/back buttons and x-init focus calls.
  implication: nothing app-authored can swallow Space.
- timestamp: 2026-11-11
  checked: tests/Feature/Booking/BookingPageBrowserTest.php
  found: every keyboard test presses Tab and Enter only; none presses Space on a disclosure.
  implication: why the gap was never caught by a gate; a Space test would be green today.
- timestamp: 2026-11-11
  checked: throwaway Pest browser probe run from the scratchpad (SpaceProbeTest.php, not in the repo) against the real booking page in bundled Chromium
  found: pressKey(' ') on the 2nd day summary toggled it open and Enter closed it; same for footer "Privacy notice". Also with a 150 ms hold.
  implication: page toggles on Space in Playwright Chromium; the UAT failure does not reproduce there.
- timestamp: 2026-11-11
  checked: the same served page driven from an external Node Playwright script using the installed Google Chrome 157.0.8103.50 (same major as a current user's Chrome), variants a-e (Tab focus, hover, mouse move during hold, press('Space'), after Livewire round trip)
  found: Space toggled in every variant.
  implication: not a Chromium-version or timing problem; page code is fine for synthetic keys.
- timestamp: 2026-11-11
  checked: census of key listeners registered by page scripts (wrapped EventTarget.prototype.addEventListener before load) and defaultPrevented state of every Space event at window bubble
  found: zero key listeners registered by Vite client, app.js, Livewire or Alpine; every Space keydown/keypress/keyup defaultPrevented=false; footer disclosure opened.
  implication: nothing on the page touches Space.
- timestamp: 2026-11-11
  checked: signature test on a minimal <details> in Chrome 157 with injected document-level capture handlers
  found: keydown preventDefault on Space -> Space NO EFFECT, Enter toggles. keyup preventDefault on Space -> Space NO EFFECT, Enter toggles. keydown stopImmediatePropagation (without preventDefault) -> both toggle.
  implication: the reported symptom (every disclosure, page-wide, Space only) is exactly what a page-wide preventDefault on Space produces. Since the page's own code does not do it, the preventDefault comes from outside the page's code in the user's browser (extension content script is the usual source), or from native-event handling not reproducible without OS-level key injection (Accessibility trust is not granted here, so CGEvent injection was not possible).
- timestamp: 2026-11-11
  checked: web search for a Chromium regression on Space + summary on macOS
  found: nothing relevant; Chrome toggles a main summary on Space keyup via HandleKeyboardActivation.
  implication: no known browser bug to blame.

## Resolution

root_cause: not confirmed. Page code exonerated. Leading remaining cause: a keydown/keyup preventDefault on Space injected by the user's environment (extension/remapper), outside this repo.
fix: none (goal is diagnose only; no repo code changed)
verification: n/a
files_changed: []
