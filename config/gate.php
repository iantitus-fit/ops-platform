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
    | a sync cannot change them. Later plans add the classifier, alert and cap
    | numbers next to the one below.
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

];
