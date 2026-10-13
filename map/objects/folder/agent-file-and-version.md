---
type: object
cluster: folder
status: live
verified: 2026-10-13 @ 2bac8cb (working tree, source inspection; owner ruling 2026-10-12)
entity: app/Ai/AgentFile.php
---

# Agent file and compiled version

**Type:** object · **Mark:** live; one field half-wired (`escalate_to`: admitted-but-unrouted, intent parked for Phase 2.1) · **Checked:** 2026-10-13 @ 2bac8cb · **Where:** `agents/*.md` in the business folder, `app/Ai/AgentFile.php:10`

**One sentence.** An agent file (`type: agent`) says who answers a channel, with which models and loading which folder files; the app never reads it from disk but from the live compiled copy in `icm_versions.compiled`.

**Why this shape.** Code reads business configuration only through `ActiveConfig`, so a sync that fails validation cannot change what the front desk does (CLAUDE.md rules). The agent is found by channel, not by path, and the validator allows one agent per channel (D-35).

## Shape
- Example: `examples/example-hvac/agents/front-desk.md` (frontmatter fields name, channel, provider, model, fallback, loads, escalate_to; the body is the agent's instruction text). Field rules: `Validator.php:202-213`; `channel` admits only `sms`.
- One agent per channel: `Validator.php:628`. `records/` can never be loaded, since the customer card is added by code: `Validator.php:679`.
- Compile: the sync writes `compiled.index.agent[<channel>] = <path>` (`FolderSync.php:223`). Read: `ActiveConfig::agent()` (`ActiveConfig.php:187`), typed by `AgentFile::forChannel` (`AgentFile.php:30`). The front desk asks for channel `sms` (`RunFrontDesk.php:111`); a live version with no agent for texts writes a note on the timeline and alerts the owner (`RunFrontDesk.php:113-117`, through `ReportFrontDeskFailure.php:31`). Zero agents stays legal, and the Business Folder page still shows "Front desk: off" (`BusinessFolder.php:91`).
- Provider chain: primary then each fallback in file order (`AgentFile.php:62`), tried in turn by `RunFrontDeskAgent.php:49`. A fallback starts only while the time already spent plus `gate.agent.timeout_seconds` fits inside `gate.agent.chain_budget_seconds` (`config/gate.php:101,103`, checked at `RunFrontDeskAgent.php:50` and `:85-88`); an entry skipped for time is recorded as skipped (`RunFrontDeskAgent.php:51-55`), and the order never changes. Every one failing raises `FrontDeskUnavailable` (`RunFrontDeskAgent.php:79`) and the front desk writes a note and alerts the owner (`RunFrontDesk.php:119-125`).
- Prompt: the body is added at `FrontDeskPrompt.php:67`; each `loads` entry is expanded at `FrontDeskPrompt.php:180`; the customer card is added by code (`FrontDeskPrompt.php:85`). The instructions end with a Right now section, the time in the business's zone, after the customer card (`FrontDeskPrompt.php:86`, `:207-213`).
- The model class is `FrontDeskAgent` (`app/Ai/Agents/FrontDeskAgent.php:20`): no tools, so it can only propose words (`:15-19`).

## Half-wired field: `escalate_to`
Admitted by the validator (`Validator.php:212`, only `owner`), stored by the sync and carried in `AgentFile::$escalateTo` (`AgentFile.php:19,45`). Nothing reads it. The hand-off to the owner is hard-wired in `RunFrontDesk::handOffToOwner` (`RunFrontDesk.php:256`). Direction: admitted-but-unrouted. Intent: **parked** for Phase 2.1, which lets it name a staff user (`.planning/phases/02-text-front-desk/02-CONTEXT.md:13`, D-23, D-35; Ian, 2026-10-12). Do not build on it; wire the missing end deliberately as its own change.

## Words on this card
"Agent" means four things. This card: the **agent file**. The SDK class `FrontDeskAgent`: the **front desk agent**. `MessageAuthor::Agent`: the author of every automated text. The SDK's `agent_conversations` tables: leftover. "Version" means two things: the `icm_versions` row, which is the live compiled folder (`ActiveConfig.php:24`, `app/Models/IcmVersion.php:24`), and `gate.version` (`DecideSendOrDraft.php:49`), the shape number inside a stored verdict. They are unrelated.

## Connected to
Owned by the folder (written only by the sync, `FolderSync.php`); read by `RunFrontDesk`. Looks like it but is not: `ActiveConfig::sendingRules()` (`rules/sending.md`, an input to the send rule, not to the agent's identity) and the `records/` folder on disk, which the agent never loads.

## If you change this
**Hits:** the validator, the compile index, `AgentFile`, `FrontDeskPrompt`, `RunFrontDeskAgent`, the Filament Business folder page (shows name and chain, `app/Filament/Pages/BusinessFolder.php:99`). A new channel needs a validator rule, a channel driver and a caller of `forChannel`. [answer-inbound](../../processes/answer-inbound.md) step 7 reads it.
**Does not hit:** stored messages and verdicts (they record which model replied, in `gate.reply_model`, but are not rebuilt from the file); the send rule's thresholds (`config/gate.php`, not the folder).

## Surfaces
Writers: the owner (edits the file), the sync. Readers: `ActiveConfig`, `AgentFile`, `FrontDeskPrompt`. External consumers: none.

## See
`app/Ai/AgentFile.php`, `app/Icm/FolderSync.php`, `app/Icm/Validator.php`; `docs/SPEC.md:206`.
