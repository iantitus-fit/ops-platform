## Conflict Detection Report

### BLOCKERS (0)

(none)

### WARNINGS (0)

(none)

### INFO (5)

[INFO] Auto-resolved: A2P registrant open decision answered by a locked decision
  Note: docs/SPEC.md "Open decisions" lists "Which entity registers A2P for Ian's own test number" as open. docs/DECISIONS.md (DEC-05, locked ADR) states "Ian registers A2P for his own test number; the build does not wait on approval." ADR outranks SPEC; the SPEC open item is treated as answered. The entity-level detail (which legal entity Ian registers under) is still not stated in either source.

[INFO] Auto-resolved: PHP version statement differs between SPEC and ADR
  Note: docs/SPEC.md "Stack" says "Laravel 13.35, PHP 8.3+"; docs/DECISIONS.md (DEC-02, locked ADR) fixes the toolchain at PHP 8.5 via php.new (Herd Lite). The two are compatible (8.5 satisfies 8.3+); the ADR's PHP 8.5 is recorded as the working runtime and the SPEC's 8.3+ as the minimum.

[INFO] Sprint 0 validator scope is narrower than the SPEC file-type table
  Note: docs/sprints/sprint-0.md Step 1 says the Validator has "one rule set per file type from SPEC.md" and the example folder includes only "every Sprint 0 file type"; docs/SPEC.md assigns agent (Sprint 1), automation (Sprint 5), connector (Sprint 6) and walk_test (Sprint 2) types to later sprints. Sprint 0 also places README.md files in agents/, automations/, connectors/ and tests/. Neither source says how untyped README.md files or not-yet-supported types are treated by the Validator. Not a contradiction; flagged so the Phase 1 plan states the behavior (suggested: README files and later-sprint types are ignored/unvalidated in Sprint 0). Similarly, sprint-0 builds only INDEX.md, open-leads.md and records/CLAUDE.md of the SPEC's records set (today.md, who-owes-us.md, this-week.md come later).

[INFO] Database timing is consistent but phased
  Note: docs/DECISIONS.md (DEC-06) and docs/SPEC.md "Stack" agree: SQLite in Sprint 0 (Pest on SQLite in memory), Postgres in CI and production from Sprint 1. No Postgres work is in Sprint 0 scope.

[INFO] Cross-references to documents outside the ingest set
  Note: docs/DECISIONS.md refs docs/gsd-ingest.yaml and .planning/PROJECT.md; docs/sprints/sprint-0.md refs docs/SETUP.md, .planning/ROADMAP.md, business/lead-sources.md, examples/example-hvac and records/ (sprint-0's "SPEC.md" resolves to docs/SPEC.md); docs/SPEC.md and DECISIONS.md also cite the 2026-10-08 practice direction and Legacy (legacy-crm commit 040f7ab) which are not ingested. Cycle detection over the ingested set found no cycles (DECISIONS -> external only; SPEC -> none; sprint-0 -> SPEC). References to non-ingested files are recorded as context only.
