---
status: testing
phase: 08-schedule-and-booking
source: [08-VERIFICATION.md]
started: 2026-11-08T23:30:00Z
updated: 2026-11-08T23:30:00Z
---

## Current Test

number: 1
name: Keyboard-only walk of the booking page at desktop width and at 375 px, including Try again on the rate-limited notice
expected: |
  A customer opens a booking link, reaches every time, books by keyboard alone, and focus lands on the confirmation heading; the rate-limited notice takes focus on its heading, Tab reaches Try again, and a successful press lands focus on the page's heading
awaiting: user response

## Tests

### 1. Keyboard-only walk of the booking page at desktop width and at 375 px, including Try again on the rate-limited notice
expected: A customer opens a booking link, reaches every time, books by keyboard alone, and focus lands on the confirmation heading; the rate-limited notice takes focus on its heading, Tab reaches Try again, and a successful press lands focus on the page's heading
result: [pending]

### 2. VoiceOver walk of the booking page (picker, check step, confirmation, rate-limited notice, footer disclosures)
expected: Each time is announced with its day and time; focus moves to the confirmation; errors are read beside the field; the footer disclosures are announced as expandable
result: [pending]

### 3. 200% zoom of the booking page, and 320 px reflow
expected: The page reflows and stays usable. No test covers 320 px (08-UI-REVIEW.md)
result: [pending]

### 4. Dev chat: ask to book a visit after intake; then ask 'what time are you coming?'
expected: The offer comes back labeled scheduling with the link line (D-05); the second question is answered from the card (D-39); asking for the link again gives a fresh link line or a held draft, never a /book/[link] to the customer (D-12)
result: [pending]

### 5. Push the branch and read CI's pgsql leg for the whole suite
expected: Both sqlite and pgsql legs green, including the booking CHECK constraint, the one-open-conversation partial index, the distinct-order query, BookingLinkLeakTest's table scan on pg_class and LinksBeforeTheFixTest
result: [pending]

### 6. Attorney list: final privacy-notice wording for the booking page, and the text provider's own message log (Twilio keeps every sent body)
expected: The attorney has the facts: the page sets a session cookie, the session row keeps the device address and browser name, the rate limiter counts taps by address, and the provider keeps sent bodies that the platform cannot redact
result: [pending]

### 7. Owner screens walk: Schedule page (Upcoming and Past with a block spanning days, plain zone name in the Block time dialog) and the contact screen's Book by hand dialog (five confirmation lines), with keyboard and VoiceOver
expected: Upcoming and Past never list the same row; the helper reads 'Times are in Central Time.'; the no-phone line shows for an email-only customer
result: [pending]

## Summary

total: 7
passed: 0
issues: 0
pending: 7
skipped: 0
blocked: 0

## Gaps
