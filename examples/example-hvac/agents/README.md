# Agents

Files with `type: agent` describe each AI helper: its model, its fallback models, what it loads and who it hands off to.
There is one agent per channel, and the only channel today is `sms`.
The sync checks each agent file in plain words before it goes live; a broken one is rejected and the previous version stays live.
