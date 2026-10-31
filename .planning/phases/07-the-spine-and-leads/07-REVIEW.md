---
phase: 07-the-spine-and-leads
reviewed: 2026-10-31T15:25:00Z
depth: standard
files_reviewed: 43
files_reviewed_list:
  - app/Actions/CloseProject.php
  - app/Actions/OpenProject.php
  - app/Actions/RenameProject.php
  - app/Actions/ReopenProject.php
  - app/Enums/ActivityType.php
  - app/Enums/ProjectActor.php
  - app/Enums/ProjectWord.php
  - app/Filament/Resources/Contacts/ContactResource.php
  - app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php
  - app/Icm/ActiveConfig.php
  - app/Icm/Validator.php
  - app/Models/Contact.php
  - app/Models/Project.php
  - app/Providers/AppServiceProvider.php
  - app/Records/CustomerCard.php
  - app/Rooms/Leads/LeadsSection.php
  - app/Rooms/Leads/LeadsServiceProvider.php
  - app/Rooms/Leads/OpenProjectForNewLead.php
  - app/Rooms/RoomRegistry.php
  - bootstrap/providers.php
  - database/factories/ProjectFactory.php
  - database/migrations/2026_10_29_000001_create_projects_table.php
  - docs/BLUEPRINT.md
  - docs/SPEC.md
  - docs/sprints/sprint-1.1.md
  - examples/example-hvac/business/profile.md
  - examples/example-hvac/rooms/leads/CONTEXT.md
  - examples/example-hvac/rooms/marketing/CONTEXT.md
  - resources/views/filament/rooms/leads-section.blade.php
  - tests/Feature/Filament/ProjectsRelationManagerTest.php
  - tests/Feature/Projects/OpenOnNewLeadTest.php
  - tests/Feature/Projects/ProjectActionsTest.php
  - tests/Feature/Projects/ProjectCardTest.php
  - tests/Feature/Projects/ProjectRecordTest.php
  - tests/Feature/Projects/ProjectWordSpecTest.php
  - tests/Feature/Projects/ProjectWordTest.php
  - tests/Feature/Records/CardInjectionTest.php
  - tests/Feature/Rooms/FrontDeskBootTest.php
  - tests/Feature/Rooms/LeadsBootTest.php
  - tests/Feature/Rooms/LeadsSectionTest.php
  - tests/Pest.php
  - tests/Unit/ProjectWritesTest.php
  - tests/Unit/Rooms/RoomWidgetOwnershipTest.php
findings:
  critical: 0
  warning: 3
  info: 4
  total: 7
status: issues_found
---

# Phase 7: Code Review Report

**Reviewed:** 2026-10-31T15:25:00Z
**Depth:** standard
**Files Reviewed:** 43
**Status:** issues_found

## Summary

I read all the source files and the Phase 7 diffs of the pre-existing ones. I did not re-run the test suite, and I read the test files only for reliability problems. The four project actions, the room hook, the card and the dashboard section hold up well:

- Each write path locks the row, re-reads it and no-ops on a repeat.
- Owner-typed text is quoted on the card and escaped in Blade and in notifications.
- The relation manager resolves records through the contact's own relation, so another contact's project id cannot be reached.
- The partial unique index backs up the "one lead project" rule.
- The registry guard works in both registration orders.

I found no security vulnerabilities and no data-loss bugs. The three warnings are a logic gap in the automatic open, a weak enforcement test, and a missing guard on the hand open.

## Warnings

### WR-01: The opt-out check runs when the job runs, not when the lead arrived, so a real lead can silently lose its project

**File:** `app/Rooms/Leads/OpenProjectForNewLead.php:89`
**Issue:** D-23 says a lead whose first text was STOP gets no project. The job tests `$contact->isOptedOut()` at run time instead. The job is queued after commit and retried with backoff of up to 300 seconds. A genuine lead who sends a normal first text and then texts STOP before a worker picks the job up (or during a retry) is reported opted out. The job then returns without opening a project and without logging anything. Nothing in the code or the tests distinguishes "the first message was STOP" from "the contact opted out later". The loss is silent, and the sweep that would catch it is deferred.
**Fix:** Decide opt-out from the arrival, not from the contact's current state. For example, skip only when the arrival's own message produced an `OptedOut` activity:
```php
$optedOutByArrival = $contact->activities()
    ->where('type', ActivityType::OptedOut->value)
    ->where('subject_type', $arrival->subject_type)
    ->where('subject_id', $arrival->subject_id)
    ->exists();

if ($optedOutByArrival) {
    return;
}
```
Move the `$arrival === null` lookup above the check. Add a test where a second STOP text arrives before the job runs and the project is still opened.

### WR-02: The "only four actions write projects" scan has easy-to-hit blind spots

**File:** `tests/Unit/ProjectWritesTest.php:25` and `:28`
**Issue:** This test is the D-11 enforcement and is described as the architecture guarantee. Its rules are narrow:
- `a query write` matches only `Project::query()...`, so `Project::where(...)->update([])`, `Project::whereKey(1)->delete()` and `Project::find(1)->update([])` all pass.
- `an instance write` matches only a variable literally named `$project`, so `$record->update()` or `$p->delete()` on a Project outside the watched Leads and Filament-Project paths passes.
- The `Project::` static rule omits `Project::where(...)` style entry points entirely.
A new writer can therefore slip in while the test reports green. The "flags every kind of write" cases only prove the patterns the author thought of.
**Fix:** Broaden the rules. For example, treat any `Project::<anything>(...)->(update|delete|...)` in a single statement as a write:
```php
'a query write' => '/(?<![A-Za-z0-9_])Project::[A-Za-z]+\([^;]*->'.$call('update|delete|forceDelete|insert|upsert|increment|decrement|create|forceCreate|firstOrCreate|updateOrCreate|createOrFirst').'/',
```
Add snippets for `Project::where(1)->update([])` and `Project::find(1)->delete()` to the "flags every kind of write" dataset. Consider also flagging `->update(` or `->delete(` on any file that imports `App\Models\Project`.

### WR-03: "New job" has no confirmation or double-submit guard, and spurious projects cannot be removed

**File:** `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:78`
**Issue:** The header action opens a new project on every call, with no confirmation and no idempotency. D-05 allows any number of projects, so a double click or a repeated Livewire submit creates two. Nothing deletes a project; the only recourse is Close. Per-contact numbering is by id order ("Job 2", "Job 3"), so a stray project permanently shifts the numbers shown on the card and in the timeline. Compare Close, which does use `requiresConfirmation()`.
**Fix:** Add `->requiresConfirmation()` to the `new` action. Optionally debounce in `OpenProject::byHand()`, for example by refusing a second hand-open for the same contact within a few seconds, or by using Filament's `->disabled` while the request is in flight.

## Info

### IN-01: The job drops work silently and has no `failed()` handler

**File:** `app/Rooms/Leads/OpenProjectForNewLead.php:81`
**Issue:** `handle()` returns without a log line when the room is off, the contact is missing, the contact is opted out, or no arrival activity exists. After five tries a failed job only reaches `failed_jobs`, with no owner-visible trace. BLUEPRINT accepts the lost-open risk until the sweep exists, but the job gives an operator nothing to diagnose it with.
**Fix:** Add a `failed(Throwable $e)` method that calls `report()`. Add a `Log::info` for the "no arrival" and "opted out" skips.

### IN-02: Stale plan references in docblocks

**File:** `app/Enums/ProjectActor.php:6` and `app/Rooms/Leads/LeadsServiceProvider.php:19`
**Issue:** The comments say "(the automatic open, plan 07-08)" and "It has no register() in this plan". Those go stale once the plan folder is archived and they describe project history, not the code.
**Fix:** Drop the plan numbers and keep the decision ids (D-xx).

### IN-03: Duplicated whitespace-collapse regex and an unused `$user` parameter

**File:** `app/Enums/ProjectWord.php:57`, `app/Actions/RenameProject.php:81`, `app/Actions/ReopenProject.php:26`
**Issue:** Two places spell the same `/[\s\p{Z}]+/u` collapse, so a future fix to one can miss the other. `ReopenProject::handle()` and `RenameProject::handle()` take a `User $user` that they never use (documented as "the signed-in owner"). Reopen and rename therefore cannot record who acted, which is acknowledged in BLUEPRINT for 999.5 but leaves a dead parameter.
**Fix:** Extract a small shared helper for the collapse. Either keep the `$user` parameter with a comment that it is reserved, or drop it until staff logins exist.

### IN-04: The dashboard's opened time has no time-zone label

**File:** `app/Rooms/Leads/LeadsSection.php:108`
**Issue:** The visible text is `Y-m-d H:i` in the business's time zone with no zone shown. The machine-readable `datetime` attribute carries the offset, but a sighted reader and a screen-reader user hear a bare local time. This is a mild clarity gap for an owner who travels or works across zones; the wording pass in Phase 11 may already cover it.
**Fix:** Append the zone abbreviation, for example `$opened->format('Y-m-d H:i T')`.

---

_Reviewed: 2026-10-31T15:25:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
