# Verification log: the text front desk

A work list for Ian, not a card and not part of the catalog's routing. One entry per deviation found by the inventory pass (checked 2026-10-12 @ be50284). Nothing here is fixed or removed; Ian decides on a separate track. Scope set by Ian's ruling 2026-10-12: these three entries only.

## 1. `agent_conversations` table
- **What:** the laravel/ai SDK's conversation-memory table, created by the SDK's migration.
- **Where:** `database/migrations/2026_10_10_191255_create_agent_conversations_table.php:17`
- **Type:** orphan (leftover, no referrer)
- **Expected:** a table in the schema has a writer or a reader in the app.
- **Actual:** grep for `agent_conversation` and `AgentConversation` over `app/`, `config/`, `routes/`, `resources/`, `tests/`, `database/seeders` and `database/factories` finds no referrer. The front desk passes its own thread to the SDK (`app/Ai/FrontDeskPrompt.php:140`, `app/Ai/RunFrontDeskAgent.php:72`).
- **Related card:** `objects/conversations/conversation-and-pause.md`

## 2. `agent_conversation_messages` table
- **What:** the SDK's per-message table for the same memory feature, created in the same migration.
- **Where:** `database/migrations/2026_10_10_191255_create_agent_conversations_table.php:27`
- **Type:** orphan (leftover, no referrer)
- **Expected:** a table in the schema has a writer or a reader in the app.
- **Actual:** same search as entry 1 finds no referrer; our own `messages` table holds every message.
- **Related card:** `objects/messages/message-and-verdict.md`

## 3. "approval log" in `config/gate.php`
- **What:** a comment that says each stored verdict is copied so "the approval log shows what a reply was judged by".
- **Where:** `config/gate.php:41`
- **Type:** ghost reference
- **Expected:** a screen, table or log named "approval log" exists, or the comment names the real thing.
- **Actual:** grep for "approval log" over `app/`, `resources/`, `config/` and `docs/` finds only this comment. The verdict is stored on `messages` (`gate_outcome`, `gate_reason`, `gate`) and printed by `gate:export` (`app/Console/Commands/GateExport.php:27`).
- **Related card:** `objects/messages/message-and-verdict.md`
