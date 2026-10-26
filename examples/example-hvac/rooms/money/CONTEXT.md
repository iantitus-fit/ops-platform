---
type: room
owns: [rooms/money/notes.md]
reads: [business/profile.md]
writes_records: none
connector: none
---

# Money

This room holds Example Heating & Air's notes on how the business gets paid today. They are for whoever sets up the money work later, and nothing reads them to act.

It owns one file, rooms/money/notes.md. A business keeps one notes file, and the sync refuses a second one wherever it sits. Without the file the room is empty.

Answers about paying that a customer hears go in business/faq.md, not here. The notes may name the bookkeeping tool the office uses. Which system is the truth for invoices, payments and expenses is said only in a connector file's owns, never in the notes.

The notes hold no amounts, percents or terms. They never hold account, routing, card or ID numbers. The sync refuses what looks like a card, bank routing, Social Security or tax ID number. A bank account number has no shape the sync can spot, so keep it out of the folder yourself.

There are no payment links and no payment records yet.

It reads business/profile.md for who the business is.

It writes nothing to `records/`.

It has no outside system.
