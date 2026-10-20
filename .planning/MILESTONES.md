# Milestones

## v1.0 Text Front Desk (Shipped: 2026-10-20)

**Delivered:** The folder drives the app, every way a lead arrives lands on one timeline, and an AI text front desk replies from an agent file behind a send-or-draft rule enforced in code, with owner-written prices and offers sent by code.

**Phases completed:** 3 phases (1, 2, 02.1), 76 plans, 169 tasks

**Stats:**
- Timeline: 10 days (2026-10-10 → 2026-10-20), 652 commits
- Git range: 84e3eae (scaffold) → 34b48bd; first feature commit 00c0325 `feat(01-01)`
- Files changed: 554 (+89,195 / −28, docs and planning included)
- Code: 16,327 lines of PHP in `app/`, 22,148 in `tests/`; the suite (1659 tests at 02.1-28) passes on SQLite and PostgreSQL 17

**Key accomplishments:**
- `icm:sync` validates, versions and activates the business folder; a broken change is refused in plain words and the live version stays; `App\Icm\ActiveConfig` is the only runtime config source.
- Texts, web form leads and `/dev/chat` land on one contact and one timeline with source and cost per lead, and every event re-renders that customer's card in `records/`.
- A laravel/ai front desk built from `agents/*.md` replies, asks intake questions, discloses it is an AI, and sends only when the label floor, the code checks and Jev agree; everything else is held as a draft and the owner is alerted.
- The owner works from Filament: take over and hand back, approve, edit or discard a draft from a phone-friendly Needs you card; STOP is honoured on every outbound path; a Twilio driver sits behind the same `TextChannel`.
- A price or offer the owner wrote goes out at once, picked and sent by code and checked at sync against the file's amounts; an easy answer never waits behind a hard one, and a customer who writes while a draft waits always gets an owner-written reply.
- Jev reads the customer's text on every run, the sender refuses an automated text without a reading, a Jev outage alerts the owner once down and once back, and one test runs all 1,440 waiting, complaint, hazard and failure combinations.

### Known Gaps

Closed with these v1 requirements unmet. Per PROJECT.md Key Decisions, "The model home is the next milestone" (v1 closes at 02.1, 2026-10-19), Phase 3 moves into the model home milestone; its scope is held in seed SEED-261020-v30.

- RECS-02: `records/today.md` rendered from appointments and a nightly full rebuild of `records/` (Phase 3)
- BOOK-01: Availability computed from `hours.md`, service duration and existing appointments (Phase 3)
- BOOK-02: Booking link with tap-to-choose times on a Livewire page (Phase 3)
- BOOK-03: Appointments on the timeline, the customer card and a dashboard schedule (Phase 3)
- BOOK-04: Appointment reminders sent by the scheduler (Phase 3)
- BOOK-05: Owner alerted when an appointment is booked and when a conversation needs a human (Phase 3)
- BOOK-06: Minutes from first contact to booked appointment recorded and visible (Phase 3)
- WALK-01: Walk-test files validated and compiled (Phase 3)
- WALK-02: Every sync walk-tests the candidate and activates only if all pass (Phase 3)
- WALK-03: "Dana end to end" passes as a walk test and a feature test (Phase 3)

### Close Notes

- Closeout type: override_closeout.
- No v1.0 milestone audit was run before the close.
- Phases 1 and 2 read as verification "stale" at close: 01-VERIFICATION.md and 02-VERIFICATION.md say passed, but later phases changed files they covered. Phase 02.1 reads as passed.
- Known verification overrides: 8 newly acknowledged, 0 carried forward from a prior close (see STATE.md Deferred Items).
- Phase folders archived to `milestones/v1.0-phases/`; quick tasks 261016-t5v, 261017-bz2 and 261018-dxy archived to `milestones/v1.0-quick/`.

---
