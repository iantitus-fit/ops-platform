---
status: complete
phase: 06-front-desk-and-team-rooms
source: [06-01-SUMMARY.md, 06-02-SUMMARY.md, 06-03-SUMMARY.md, 06-04-SUMMARY.md, 06-05-SUMMARY.md, 06-06-SUMMARY.md, 06-07-SUMMARY.md]
started: 2026-10-28T18:03:02Z
updated: 2026-10-28T18:10:14.476Z
---

## Current Test

[testing complete]

## Tests

### 1. Cold Start Smoke Test
expected: Stop `composer run dev` if it is running. Run `php artisan migrate:fresh --seed` (this wipes your local database; all six Phase 6 migrations, 2026_10_27_000001 to 000006, run with no error), then `php artisan icm:sync` (it reads examples/example-hvac unless ICM_PATH is set): the sync is accepted with both new rooms, rooms/front_desk/ and rooms/team/, in it. Start `composer run dev` and log in to /admin as test@example.com / password. The dashboard loads and shows, in this order: Drafts waiting, Customers waiting while you handle it, New leads, Today's conversations, each widget once, with no error in the logs tab.
result: pass

### 2. Postgres leg of CI green for the whole phase
expected: The CI tests workflow on the phase branch head (42d80eb, after the review fixes) is green on both legs. Already read from GitHub: run 40738614777, pest (sqlite) and pest (pgsql) both success, 2272 passed (17336 assertions) on each. Confirm you accept this run as the Postgres check. One check stands for seven carried hand checks: 06-01 D4, 06-02 D7, 06-03 D4, 06-04 D4, 06-05 D2, 06-06 D4, 06-07 D4 (WINDOWS.md rows 27 to 33; rows 30 to 33 are still open in the ledger).
result: pass
notes: "CI run 40738614777 on 42d80eb: pest (sqlite) and pest (pgsql) both success, 2272 passed each."

### 3. 06-01 D1: The line-185 branch (an absent room that alone handles a file's type is refused with one line, and the previous version stays live) has its own case over a hand-built registry, passing on today's code
expected: The line-185 branch (an absent room that alone handles a file's type is refused with one line, and the previous version stays live) has its own case over a hand-built registry, passing on today's code
result: pass
source: automated
coverage_id: 06-01-D1

### 4. 06-01 D2: The line-77 and line-160 branches stay covered by the two existing hand-built RoomChecks cases
expected: The line-77 and line-160 branches stay covered by the two existing hand-built RoomChecks cases
result: pass
source: automated
coverage_id: 06-01-D2

### 5. 06-01 D3: RoomOwnershipTest loses exactly its three triage-only cases (18 to 15) and the whole suite stays green
expected: RoomOwnershipTest loses exactly its three triage-only cases (18 to 15) and the whole suite stays green
result: pass
source: automated
coverage_id: 06-01-D3

### 6. 06-02 D1: The front desk is a room: provider registered after GoogleBusinessProfile, contract in the example business, boots empty and with its example folder, contract matches SPEC's front desk rows plus agents/*.md
expected: The front desk is a room: provider registered after GoogleBusinessProfile, contract in the example business, boots empty and with its example folder, contract matches SPEC's front desk rows plus agents/*.md
result: pass
source: automated
coverage_id: 06-02-D1

### 7. 06-02 D2: A folder without rooms/front_desk/ is refused with one never-left-out line and the previous version stays live
expected: A folder without rooms/front_desk/ is refused with one never-left-out line and the previous version stays live
result: pass
source: automated
coverage_id: 06-02-D2

### 8. 06-02 D3: The text slot names TextChannel as a string, the architecture rule passes unchanged, and the fake still fills the slot
expected: The text slot names TextChannel as a string, the architecture rule passes unchanged, and the fake still fills the slot
result: pass
source: automated
coverage_id: 06-02-D3

### 9. 06-02 D4: Room::$claimedWidgets and its guard: accepted claims stay off the panel list, a missing class, a non-widget and a doubly claimed widget are refused at boot
expected: Room::$claimedWidgets and its guard: accepted claims stay off the panel list, a missing class, a non-widget and a doubly claimed widget are refused at boot
result: pass
source: automated
coverage_id: 06-02-D4

### 10. 06-02 D5: The dashboard shows Drafts waiting, Customers waiting while you handle it, New leads and Today's conversations in that order, each claimed widget held once, NewLeads claimed by no room
expected: The dashboard shows Drafts waiting, Customers waiting while you handle it, New leads and Today's conversations in that order, each claimed widget held once, NewLeads claimed by no room
result: pass
source: automated
coverage_id: 06-02-D5

### 11. 06-02 D6: The wrap changes no reply, hold, alert or screen: the full suite passes with the example folder file count the only existing test change
expected: The wrap changes no reply, hold, alert or screen: the full suite passes with the example folder file count the only existing test change
result: pass
source: automated
coverage_id: 06-02-D6

### 12. 06-03 D1: The team is a registered room: key team, owner_alerts table, DraftsWaiting claimed as a pointer, empty contract ownership, three folder-path reads, listed after the front desk in bootstrap/providers.php
expected: The team is a registered room: key team, owner_alerts table, DraftsWaiting claimed as a pointer, empty contract ownership, three folder-path reads, listed after the front desk in bootstrap/providers.php
result: pass
source: automated
coverage_id: 06-03-D1

### 13. 06-03 D2: A folder without rooms/team/ is refused with one line and the last good version stays live; with the front desk also gone, two lines in key order, stable across syncs
expected: A folder without rooms/team/ is refused with one line and the last good version stays live; with the front desk also gone, two lines in key order, stable across syncs
result: pass
source: automated
coverage_id: 06-03-D2

### 14. 06-03 D3: The wrap changes no alert, approval or who-acted behavior: whole suite passes with the file count (24 to 25) as the only existing test change
expected: The wrap changes no alert, approval or who-acted behavior: whole suite passes with the file count (24 to 25) as the only existing test change
result: pass
source: automated
coverage_id: 06-03-D3

### 15. 06-04 D1: Two stale copies of one alert, or a second worker that reaches it mid-send, text the owner once, with one outcome write and one timeline entry
expected: Two stale copies of one alert, or a second worker that reaches it mid-send, text the owner once, with one outcome write and one timeline entry
result: pass
source: automated
coverage_id: 06-04-D1

### 16. 06-04 D2: A claim is taken right before the send, never for a blank phone or a held alert; a fresh claim is left alone and a stale one is texted once
expected: A claim is taken right before the send, never for a blank phone or a held alert; a fresh claim is left alone and a stale one is texted once
result: pass
source: automated
coverage_id: 06-04-D2

### 17. 06-04 D3: A dead sender's alert is re-sent by the sweep at exactly 120 seconds (119 leaves it alone), oldest first, and skip() never ends an alert being sent
expected: A dead sender's alert is re-sent by the sweep at exactly 120 seconds (119 leaves it alone), oldest first, and skip() never ends an alert being sent
result: pass
source: automated
coverage_id: 06-04-D3

### 18. 06-05 D1: Two workers raising the same conversation alert at once store one row and send one text; the loser returns no alert
expected: Two workers raising the same conversation alert at once store one row and send one text; the loser returns no alert
result: pass
source: automated
coverage_id: 06-05-D1

### 19. 06-05 D3: Business-wide alerts carry no key and repeat freely
expected: Business-wide alerts carry no key and repeat freely
result: pass
source: automated
coverage_id: 06-05-D3

### 20. 06-05 D4: Alerts that touch but differ (reason, person, message) get different keys; no subject or no phone still de-duplicates
expected: Alerts that touch but differ (reason, person, message) get different keys; no subject or no phone still de-duplicates
result: pass
source: automated
coverage_id: 06-05-D4

### 21. 06-05 D5: Backfill keys stored conversation alerts, first of each duplicate group only, and is idempotent
expected: Backfill keys stored conversation alerts, first of each duplicate group only, and is idempotent
result: pass
source: automated
coverage_id: 06-05-D5

### 22. 06-06 D1: Every row a front-desk run claims carries that run's token, written by the claiming update; a tokenless claim makes a fresh one; a STOP text handled on arrival carries none
expected: Every row a front-desk run claims carries that run's token, written by the claiming update; a tokenless claim makes a fresh one; a STOP text handled on arrival carries none
result: pass
source: automated
coverage_id: 06-06-D1

### 23. 06-06 D2: The token is made with the job and survives serialization, so failed() on the rebuilt copy reports the right burst
expected: The token is made with the job and survives serialization, so failed() on the rebuilt copy reports the right burst
result: pass
source: automated
coverage_id: 06-06-D2

### 24. 06-06 D3: A stopped run tells the owner about exactly the texts it claimed: a later text, an earlier run's text and a STOP text are never blamed; a run that claimed nothing reports nothing; a form-only burst is reported against the form; the words stay fixed
expected: A stopped run tells the owner about exactly the texts it claimed: a later text, an earlier run's text and a STOP text are never blamed; a run that claimed nothing reports nothing; a form-only burst is reported against the form; the words stay fixed
result: pass
source: automated
coverage_id: 06-06-D3

### 25. 06-07 D1: A failed run is reported once per claim token whichever of the run's catch, failed() and RunFrontDesk's own reports gets there first, including a burst whose newest row is a web form
expected: A failed run is reported once per claim token whichever of the run's catch, failed() and RunFrontDesk's own reports gets there first, including a burst whose newest row is a web form
result: pass
source: automated
coverage_id: 06-07-D1

### 26. 06-07 D2: A report that cannot be written gives its claim back and rethrows, so the other path still tells the owner; the exception's words never reach the note or alert
expected: A report that cannot be written gives its claim back and rethrows, so the other path still tells the owner; the exception's words never reach the note or alert
result: pass
source: automated
coverage_id: 06-07-D2

### 27. 06-07 D3: The catch path reports the newest text message the run claimed (the form when it claimed only a form), and a failed web-form-only burst is reported on the catch path
expected: The catch path reports the newest text message the run claimed (the form when it claimed only a form), and a failed web-form-only burst is reported on the catch path
result: pass
source: automated
coverage_id: 06-07-D3

## Summary

total: 27
passed: 27
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none yet]
