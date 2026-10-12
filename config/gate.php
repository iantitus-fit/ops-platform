<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Send Rule Numbers
    |--------------------------------------------------------------------------
    |
    | This file holds the send rule's numbers in code, not in the business
    | folder: the owner changes how the business runs in the folder, but the
    | limits that decide whether a text may go out on its own stay here, where
    | a sync cannot change them. The classifier thresholds sit below; later
    | plans add the alert and cap numbers next to them.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Next Opening Wording
    |--------------------------------------------------------------------------
    |
    | The {next_open} fill-in in a fixed reply says "today" only while the shop
    | is open and at least this many minutes remain before closing, in the
    | business's own time zone. Inside the last stretch it names the next
    | opening instead ("when we open at 8 am tomorrow"), so the time a text
    | promises is still true when the customer reads it.
    |
    */

    'next_open' => [
        'today_margin_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Classifier Thresholds
    |--------------------------------------------------------------------------
    |
    | How Jev's numbers become send or hold. Every value below is copied onto
    | each stored verdict, so the approval log shows what a reply was judged by
    | and these can be loosened later from what the log shows. They are
    | deliberately strict: a wrong hold costs the owner a tap, a wrong send
    | costs a customer.
    |
    | intent_min_confidence (D-45): the intent counts as sendable only when it
    | is marked sendable in rules/triage.md and Jev's confidence is at least this.
    |
    | gate_check_max_yes (D-45): price, promise, discount and complaint are a
    | clear "no" only when Jev's chance of "yes" is at or under this. Anything
    | higher holds. Also the point above which asks_for_person is unsure.
    |
    | life_safety_min_yes (D-46): a chance of "yes" above this triggers the
    | safety line, because when unsure the safe move is to send it.
    |
    | asks_for_person_min_yes (D-47): at or above this the customer is handed
    | to the owner. Between gate_check_max_yes and this the reply holds for the
    | owner to decide, without pausing the conversation.
    |
    */

    'classifier' => [
        'intent_min_confidence' => 0.85,
        'gate_check_max_yes' => 0.075,
        'life_safety_min_yes' => 0.075,
        'asks_for_person_min_yes' => 0.85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Front Desk Agent
    |--------------------------------------------------------------------------
    |
    | timeout_seconds: how long one provider gets to answer before the chain
    | moves to the next one. The SDK default is 60; a shorter limit keeps a
    | stalled primary from holding up the customer's burst.
    |
    | lock_wait_seconds: how long an automated send waits for the owner's own
    | send to the same customer to finish, so the two cannot go out together.
    |
    | chain_budget_seconds: the longest the whole provider chain may run. A
    | fallback starts only if the time spent so far plus timeout_seconds fits,
    | so one run always ends well inside ProcessInbound's 300-second limit.
    |
    */

    'agent' => [
        'timeout_seconds' => 40,
        'lock_wait_seconds' => 5,
        'chain_budget_seconds' => 150,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reply Cap
    |--------------------------------------------------------------------------
    |
    | How many texts the assistant may send in one conversation (D-52,
    | Pitfall 12). Two auto-responders texting each other, or a customer
    | stuck in a loop, would otherwise run all night at the model's cost. At
    | either number the front desk does not run: the conversation pauses and
    | the owner is alerted. The safety line is never counted and never
    | blocked. These are starting points; change them after watching real
    | conversations.
    |
    | per_hour: the most automated texts in the last 60 minutes.
    | per_day: the most automated texts in the last 24 hours.
    |
    */

    'reply_cap' => [
        'per_hour' => 6,
        'per_day' => 20,
    ],

];
