# Phase 1: Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md. This log keeps the alternatives that were considered.

**Date:** 2026-10-10
**Phase:** 01-foundation
**Areas discussed:** Validator & compiled version, Sync runs & changelog, Lead matching & attribution, Customer cards & records/

---

## Validator & compiled version

**Files that aren't a Sprint 0 type**

| Option | Description | Selected |
|--------|-------------|----------|
| Suggested default | Orientation files not validated or counted; later-sprint types get a "not supported yet" notice; unknown or missing `type:` is an error | ✓ |
| Strict | Every non-orientation file needs a Sprint 0 type; later-sprint types rejected | |
| Lenient | Untyped means knowledge; unknown types skipped with a warning | |

**How many errors to report**

| Option | Description | Selected |
|--------|-------------|----------|
| All errors, grouped by file | Owner fixes everything in one pass | ✓ |
| Stop at the first error | Simpler; several sync rounds | |

**What the compiled version holds**

| Option | Description | Selected |
|--------|-------------|----------|
| Front matter + bodies + routers | Sprint 1 agents load any routed path from ActiveConfig | ✓ |
| Typed files only | Routers excluded | |
| Front matter only | Smallest; bodies need a second pass later | |

**ActiveConfig API shape**

| Option | Description | Selected |
|--------|-------------|----------|
| Typed accessors + raw file lookup | `business()`, `leadSource('angi')`, `file('…')` | ✓ |
| Generic dot-path get() | Flexible; silent typos | |
| You decide | | |

**More questions?** User's free-text answer: "Treat these as plain-words errors, then move on: two lead sources with the same key, two service files with the same slug, and duplicate intake keys within one service."

---

## Sync runs & changelog

| Question | Options | User's choice |
|----------|---------|---------------|
| Same fingerprint as active | No-op but log / No-op silent / Always create a version | No-op, but log it |
| Changelog entry format | Dated heading + bullets / One line per attempt | Dated heading + bullets. **Notes:** "with times shown in the business's timezone from profile.md (fall back to the active version's timezone if profile.md is the file that's broken)" |
| Sync Now button | Inline / Queued job | Inline, show result immediately |
| Rollback in Phase 1 | No, defer / Yes, `icm:activate {version}` | No, defer it |

---

## Lead matching & attribution

| Question | Options | User's choice |
|----------|---------|---------------|
| Matching with no phone or email only | Phone first then email / Phone only / Phone required, email optional | Phone first, then email |
| Returning contact, different source | First touch on contact, every touch on timeline / Last touch overwrites | First touch on contact, every touch on timeline |
| Unknown or missing source | Accept, null cost, flag / Reject 422 | Accept, cost null, flag it |
| Form conversation and owner reply | Web conversation with reply by text / One conversation per contact / Timeline only | **Free text:** "Option 2: one conversation per contact, with the channel stored on each message. The reply box sends by text when the contact has a phone; with no phone it's disabled and shows the email." |
| After a close, customer writes again | Reopen same / Start new | Start a new conversation |
| Email matches a contact with a different phone | Match, keep, note / Separate contact / Match and add phone if empty | Match and add the phone if theirs is empty |

**Notes:** "Make provider_message_id unique per provider, as the spec's bridge section requires. US as the default phone region is fine."

---

## Customer cards & records/

| Question | Options | User's choice |
|----------|---------|---------------|
| Card filename | name-slug + id / Phone-based / Name slug only with suffix | name-slug + short id |
| Open lead definition | lead + open conversation / Every status=lead | status=lead with an open conversation |
| Card timeline depth | Full, newest first / Last N + counts | Full timeline, newest first |
| What's tracked in example records/ | README + CLAUDE / README only / Render outside repo | README + CLAUDE. **Notes:** "with the ignore rules scoped to examples/*/records/ so they can't match app/Records/ on a case-insensitive Mac." |

---

## Claude's Discretion

- Changelog timezone when no active version exists and profile.md is broken (falls back to the app timezone, UTC)
- Tests use a temporary copy of the example folder as `ICM_PATH`
- Dashboard layout details, `/dev/chat` refresh mechanism, seeder data
- `ActiveConfig` method names, compiled JSON shape, fingerprint algorithm, retention of superseded versions

## Deferred Ideas

- `icm:activate {version}` rollback command
