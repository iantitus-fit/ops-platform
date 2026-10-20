---
id: SEED-261020-v30
status: dormant
planted: 2026-10-20
planted_during: v1.0 close-out, after Phase 02.1
trigger_when: model home milestone
scope: Medium
audit_acknowledged:
  milestone: v1.0
  at: 2026-10-20
  status: dormant
---

# SEED-261020-v30: Phase 3, Booking and the Gate

## Why This Matters

v1 closes at Phases 1, 2 and 02.1 (Ian, PROJECT.md Key Decisions, "The model home is the next milestone" and "v1 closes at 02.1"). Phase 3 had not started, so its ROADMAP entry moves here unchanged and its requirements (RECS-02, BOOK-01 to BOOK-06, WALK-01 to WALK-03) are recorded as known gaps at the v1.0 close. The model home milestone picks it up.

## When to Surface

**Trigger:** model home milestone

This seed will surface during `/gsd-new-milestone` when the milestone scope matches.

## Scope Estimate

**Medium**: one phase, as the ROADMAP entry below defines it.

## The ROADMAP entry, word for word

Phase list line:

- [ ] **Phase 3: Booking and the Gate** - Customers book from a link and every folder change is walk-tested before it goes live (SPEC Sprint 2)

Details:

### Phase 3: Booking and the Gate

**Goal**: A customer can finish the conversation by booking a time, the owner sees and is told about it, and no folder change goes live until scripted customers have walked through it.
**Depends on**: Phase 2
**Requirements**: BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, RECS-02, WALK-01, WALK-02, WALK-03
**Success Criteria** (what must be TRUE):
  1. A customer who is texted a booking link opens a page, taps one of the offered times and ends with a confirmed appointment; only times inside `hours.md` hours, outside closures, past `booking_lead_minutes`, long enough for the service, and in the business timezone are offered.
  2. A booked appointment appears on the contact's timeline, as the next appointment on the customer card, in `records/today.md` and on a dashboard schedule; the customer is reminded before it; the owner is alerted; and a nightly full rebuild of `records/` is scheduled.
  3. The minutes from a contact's first message to their booked appointment are shown on the contact and in a dashboard summary.
  4. Every sync runs each scripted customer in `tests/*.md` against the candidate version; after a change that breaks one (for example the agent would now quote a price that is not in the folder, or the expected time is no longer offered) the sync fails, names the test and the check that failed in plain words, keeps the previous version live, writes the attempt to `changelog.md` and shows it on the Business Folder page.
  5. "Dana end to end" passes: a scripted customer texts in, answers the intake questions, picks a time and is booked, with the owner alerted and the card and records updated.

**Plans**: TBD
**UI hint**: yes

**Planning notes** (settle these in discuss-phase):
- How a walk test drives the agent deterministically (scripted model versus a live model) is not defined in the SPEC; the activation gate cannot depend on a flaky call.
- Reminder timing and where it is configured are not defined (BOOK-04); `hours.md` only carries `booking_lead_minutes`.
- BOOK-06 and RECS-02 are derived from the SPEC rather than named in the Sprint 2 row; confirm scope.
- New concepts expected: scheduler, notifications, Livewire pages, time zones. Time zone handling comes from `business/profile.md`.

Progress row:

| 3. Booking and the Gate | 0/TBD | Not started | - |

## Breadcrumbs

- `.planning/REQUIREMENTS.md`: RECS-02 and BOOK-01 to BOOK-06, WALK-01 to WALK-03, unchecked (archived as `.planning/milestones/v1.0-REQUIREMENTS.md` at the close).
- `.planning/STATE.md` Blockers: "[Phase 3]: Walk-test determinism, reminder timing and the BOOK-06 and RECS-02 scope are undefined in the SPEC."
- `.planning/PROJECT.md` Key Decisions: "The project is the spine", "The model home is the next milestone", "One owner per fact", "v1 closes at 02.1".
- `docs/BLUEPRINT.md` lines 102, 234, 356 and 395: the four "roadmap pending" links, pointed at the new phases in the model home roadmap's commit.
- `docs/phase-2.2-owner-input.md` §3: booking never offers a time that has already passed.

## Notes

Moved out of ROADMAP.md on 2026-10-20 (Ian) so `milestone.complete` can close v1.0 without `--force`.
