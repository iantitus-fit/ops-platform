# Ops Platform (working name)

[![tests](https://github.com/iantitus-fit/ops-platform/actions/workflows/tests.yml/badge.svg)](https://github.com/iantitus-fit/ops-platform/actions/workflows/tests.yml)

Operations software for owner-run service businesses, built for the trades first. A customer texts the business, an AI front desk answers, asks the business's own intake questions and sends a booking link, and the customer picks a time. The owner gets a text and the job lands on one timeline with everything else that customer has said or done.

Each business runs from a folder of plain files: its services, hours, prices, intake questions, how it talks and what the AI may send without asking. The owner, or the owner's own AI, edits that folder and the platform checks every change before it goes live.

**Where it stands (November 22, 2026):** still being built, and it only runs locally so far. The text front desk, booking, owner alerts, the contact timeline and a minutes-to-booked report are on `main`. The current work is walk tests, scripted customers that run through every folder change before it can go live, so an edit that would let the AI quote a price that isn't in the folder gets refused. That work is on the `gsd/phase-10-walk-tests-and-the-gate` branch.

## A ten-minute walk through the code

`map/processes/answer-inbound.md` follows one text from the webhook to the reply, step by step, with file and line references, and `map/CLAUDE.md` indexes the rest of the map. That's the fastest way in.

Every reply passes a send rule in `app/SendRule/` before it goes anywhere. If a reply might quote a price, make a promise, offer a discount or answer a complaint, it's held as a draft for the owner to approve. The model never writes a price or an offer; code copies them into the text from the owner's own files. Every outbound text goes through one sender, `app/Actions/OutboundSender.php`.

`examples/example-hvac/` is a made-up HVAC company and the quickest way to see what a business folder looks like. Start with its `START-HERE.md`.

For why things work the way they do, `docs/BLUEPRINT.md` lists what's built and decided in each area of the business along with what each decision affects, and `docs/SPEC.md` is the product spec. `.planning/` keeps every phase's decisions, plans, reviews and verification.

The tests are Pest, in `tests/`, and CI runs the full suite against SQLite and PostgreSQL on every push.

If you'd rather point an AI assistant at it, `CLAUDE.md` at the root and `map/CLAUDE.md` tell it where things are.

## How it's built

I'm Ian Titus and I build with Claude Code. Each phase starts with a written discussion where I make the calls, then a plan, then test-first work in small commits that get checked before I push them. It's slower than letting an agent run on its own. What I get for that is a repo where every decision sits next to the reason for it.

## Stack and running it

Laravel 13, Filament 5, Livewire 4, laravel/ai and Pest, on PostgreSQL or SQLite. `docs/SETUP.md` covers a first run on a Mac. With the app running, `/dev/chat` lets you text the front desk as a customer without a phone.

Copyright 2026 Ian Titus. All rights reserved.
