# Writing offers

An offer is a promotion you are running, written as words the system sends to a customer on its own when they ask for a discount. Nobody reads it first, so the sync checks it hard before it can go live. These are the rules, in plain words.

## One offer, one file

Each offer is its own file in this folder, named for the offer (`first-visit-promotion.md`). Its front matter has:

- `type: offer`
- `name`: what you call the offer
- `slug`: a short label in lowercase letters, numbers and dashes. No two offers can share one.
- `sentence`: the words the customer reads
- `amount_cents` or `percent`: exactly one of them. `amount_cents` is whole cents ($50 is `5000`). `percent` is a whole number from 1 to 100.
- `start_date`: the first day the offer is live, written like `2026-10-01`
- `end_date`: the last day, written the same way. It is optional.

## Write the sentence as a condition you check at the visit

Open with the condition and let the business confirm it when the customer arrives: "If you're a first-time customer, we're running a promotion that takes $50 off your first visit." The system never decides who qualifies. You do, at the visit.

## Match the sentence to the field

Write every amount as `$` and digits, such as `$50`, never "50 bucks" or "$5k". The sync compares the amounts in the sentence with `amount_cents`, and any percentage in the sentence with `percent`, and they have to match exactly in both directions. An offer in dollars names only that amount. A percentage offer names only that percentage ("10% off"). A sentence with no amount and no percentage is refused.

Say what the offer is, never promise. Words such as "guaranteed", "for sure" and "definitely" are refused, and so is anything your voice file says never to say. No fill-ins in braces either.

## Dates are in your time zone

The offer is live from the start of `start_date` until the end of `end_date`, in the business time zone, so the end date counts as a full day. Dates are written year, month, day with dashes. The end date cannot come before the start date.

## An offer with no end date

You can leave out `end_date`, and the offer runs until you remove it. The Business Folder page flags an offer with no end date after 90 days so it is not forgotten.

## When no offer is live

If a customer asks for a discount and no offer is live that day, they get the discount reply from `rules/replies.md`, and you are alerted so you can answer yourself.
