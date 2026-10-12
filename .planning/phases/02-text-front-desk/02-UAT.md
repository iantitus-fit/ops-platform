---
status: complete
phase: 02-text-front-desk
source: [02-VERIFICATION.md]
started: 2026-10-12T13:16:50Z
updated: 2026-10-12T22:14:26.014Z
---

## Current Test

[testing complete]

## Tests

### 1. Open an owner-alert link on a real phone over Wi-Fi after triggering a held draft from /dev/chat
expected: Lands on the conversation; held reason, draft and Send are visible without scrolling
result: issue
reported: "Mostly passes: held reason, draft, Send and Discard are visible without scrolling, customer details are collapsed, sign-in worked, and Send delivered the draft with the sign-off and no second AI note. Two minor issues: (1) the thread under the Needs you card starts at the oldest message, not the latest (D-29 says it opens at the latest); (2) after Send, the sent reply is listed above the holding reply in /dev/chat because it keeps the draft's creation time; outbound messages should sort by when they were sent. Suggestion for 2.1, not an issue: show the customer's last message in the Needs you card, so the owner sees what they're answering without scrolling."
severity: minor

### 2. Set JEV_API_KEY, ANTHROPIC_API_KEY and OPENAI_API_KEY, run icm:sync for the Phase 2 folder, run a queue worker, text from /dev/chat
expected: A reply that opens with the AI disclosure, ends with the sign-off, asks the matching service's intake question, and a price question is held as a draft
result: issue
reported: "Issue, major: ordinary intake replies never send on their own. Live run: \"My AC is blowing warm air\" got a correct intake question (label intake_question, code checks clean, Jev intent intake at 1.0), but Jev scored complaint 0.47 and promise 0.30 against the 0.075 bar, so it was held. Rewording the complaint and promise questions in examples/example-hvac/rules/triage.md (uncommitted; keep it) cut those to complaint 0.07 and promise 0.09, and the reply was still held on promise. Fix: keep the new triage wording, and make the yes/no bar per question in config/gate.php: price and discount stay at 0.075, promise and complaint go to 0.15. This amends D-45; record it. Life-safety and asks_for_person thresholds unchanged. Working as designed: the AI disclosure, intake question and sign-off are right on every first reply; a furnace repair message with no matching service was held as \"other\"; \"I smell gas\" got the safety line at once, paused the conversation and alerted the owner; a 401 from a wrong Jev key held the reply. Second issue, major: the agent doesn't know today's date. Dana's draft said her visit is \"tomorrow at 9\" because she wrote \"tomorrow at 9\" on Oct 11; that visit was this morning. FrontDeskPrompt gives the model no current date or time, and the thread messages carry no timestamps. Fix: put the current date, weekday and time in the business's time zone in the prompt, and a timestamp on each thread message. Minor: /dev/chat shows held drafts as if they were sent; the furnace draft said \"We do repair furnaces\", which no business file says."
severity: major

### 3. Push the branch (no git remote exists yet) and watch the tests workflow
expected: Both matrix legs (sqlite, pgsql) green
result: pass

## Summary

total: 3
passed: 1
issues: 2
pending: 0
skipped: 0
blocked: 0

## Gaps

- gap_id: G-02-1.1
  truth: "The thread under the Needs you card opens at the latest message (D-29)"
  status: failed
  reason: "User reported: the thread under the Needs you card starts at the oldest message, not the latest (D-29 says it opens at the latest)"
  severity: minor
  test: 1
  artifacts: []
  missing: []

- gap_id: G-02-1.2
  truth: "Outbound messages sort by when they were sent, so a draft sent from the conversation screen appears after the holding reply in /dev/chat"
  status: failed
  reason: "User reported: after Send, the sent reply is listed above the holding reply in /dev/chat because it keeps the draft's creation time; outbound messages should sort by when they were sent"
  severity: minor
  test: 1
  artifacts: []
  missing: []

- gap_id: G-02-2.1
  truth: "An ordinary intake reply with clean code checks and no real price, discount, promise or complaint content sends on its own instead of being held"
  status: failed
  reason: "User reported: ordinary intake replies never send on their own. \"My AC is blowing warm air\" got a correct intake question (code checks clean, Jev intent intake 1.0) but Jev scored complaint 0.47 and promise 0.30 against the 0.075 bar. After rewording the complaint and promise questions in examples/example-hvac/rules/triage.md (uncommitted; keep it) they fell to 0.07 and 0.09 and the reply was still held on promise. Proposed fix: keep the new triage wording; make the yes/no bar per question in config/gate.php (price and discount stay 0.075, promise and complaint go to 0.15); this amends D-45 and must be recorded; life-safety and asks_for_person thresholds unchanged."
  severity: major
  test: 2
  artifacts: []
  missing: []

- gap_id: G-02-2.2
  truth: "The front-desk agent knows the current date and time, so relative dates in the thread (\"tomorrow at 9\") are read correctly"
  status: failed
  reason: "User reported: Dana's draft said her visit is \"tomorrow at 9\" because she wrote \"tomorrow at 9\" on Oct 11; that visit was this morning. FrontDeskPrompt gives the model no current date or time, and thread messages carry no timestamps. Proposed fix: put the current date, weekday and time in the business's time zone in the prompt, and a timestamp on each thread message."
  severity: major
  test: 2
  artifacts: []
  missing: []

- gap_id: G-02-2.3
  truth: "/dev/chat shows held drafts as held, not as if they were sent"
  status: failed
  reason: "User reported: /dev/chat shows held drafts as if they were sent"
  severity: minor
  test: 2
  artifacts: []
  missing: []

- gap_id: G-02-2.4
  truth: "Drafts make no claims the business files don't support"
  status: failed
  reason: "User reported: the furnace draft said \"We do repair furnaces\", which no business file says"
  severity: minor
  test: 2
  artifacts: []
  missing: []

## Deferred Follow-Ups

- test: 1
  idea: "Show the customer's last message in the Needs you card, so the owner sees what they're answering without scrolling (suggested for Phase 2.1)"
  deferred_at: 2026-10-12
