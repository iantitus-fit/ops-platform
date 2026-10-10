# Synthesis Summary

Entry point for gsd-roadmapper. Mode: new. Generated from classifications in `.planning/intel/classifications/`.

## Doc counts by type
- ADR: 1 (docs/DECISIONS.md, precedence 0, locked)
- SPEC: 2 (docs/SPEC.md, precedence 1; docs/sprints/sprint-0.md, precedence 2)
- PRD: 0
- DOC: 0
- UNKNOWN: 0
- Total: 3

All three types were set by the manifest (manifest_override). No PRD exists, so requirements were derived from sprint-0.md's acceptance criteria per the orchestrator's instruction; sprint-0.md should feed Phase 1.

## Decisions locked: 14
All from docs/DECISIONS.md (every dated entry treated as locked): DEC-01 through DEC-14 in `decisions.md`. Topics: GSD process settings, PHP via php.new, records as files, scaffold versions, fake text channel, SQLite dev / Postgres CI+prod from Sprint 1, laravel/ai, Filament 5 + Livewire, one instance per client, ICM foundation, route-don't-retrieve, rebuild-not-clone, Laravel, supersession of one clause of the 2026-10-08 practice direction.

## Requirements: 10
REQ-icm-sync-activate, REQ-icm-sync-reject-broken, REQ-inbound-text-fake, REQ-inbound-web-lead, REQ-contact-match-by-phone, REQ-owner-reply-fake-channel, REQ-records-render-customer-card, REQ-dashboard-screens, REQ-dev-chat-page, REQ-suite-green-money-config-rules. All are Sprint 0 / Phase 1, derived from sprint-0.md "Done when" criteria 1 to 10 (plus an out-of-scope note). Sprints 1 to 6 are context only (SPEC: Sprints 0 to 2 committed; 3+ pulled by a client).

## Constraints: 43
Type breakdown: schema 17, nfr 13, protocol 11, api-contract 2. See `constraints.md`.

## Context topics: 7
What this is, who it is for, modules and build order, open decisions, Sprint 0 goal and planning notes, practice direction background, framework-choice motivation.

## Conflicts
- 0 blockers
- 0 competing variants
- 5 info entries (2 auto-resolved, 3 informational)
Detail: `/Users/iantitus/Projects/ops-platform/.planning/INGEST-CONFLICTS.md`

## Intel files
- `/Users/iantitus/Projects/ops-platform/.planning/intel/decisions.md`
- `/Users/iantitus/Projects/ops-platform/.planning/intel/requirements.md`
- `/Users/iantitus/Projects/ops-platform/.planning/intel/constraints.md`
- `/Users/iantitus/Projects/ops-platform/.planning/intel/context.md`
