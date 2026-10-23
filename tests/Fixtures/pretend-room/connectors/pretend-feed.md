---
type: connector
system: Pretend Feed
direction: in
slot: pretend_feed
owns: [documents]
reads: [customers, projects]
schedule: hourly
---

# Pretend feed

This connector exists only in tests. It stands in for an outside system that sends the pretend room its documents once an hour.

It is the truth for documents and only copies customers and projects. No credentials are kept here.
