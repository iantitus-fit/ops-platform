# Sprint 1.1: Faster Answers

Status: in progress. `.planning/` is the plan of record: this file is the
sprint's acceptance list and the walk-through log, and phase 02.1 in
`.planning/ROADMAP.md` holds the plans.

## Goal

Routine questions finish without the owner. A price or an offer the owner wrote
goes out at once, sent by code and never written by the model. An easy answer
never waits behind a hard question that is already with the owner. A customer
who is waiting always hears back, in the owner's words, and is promised only
what the opening hours can keep.

## Done when (acceptance criteria)

1. A customer who asks a price for a service with price sentences is asked the
   intake questions that pick the sentence, then gets the owner's sentence for
   their answers (or the default sentence) sent by code. A corrected answer
   re-sends with the owner's same, higher, lower or changed lead. A price the
   model writes, or a service with `price_policy: none`, still holds. A price
   sentence whose amounts do not match its file fails the sync in plain words.
2. A discount question gets every live offer's conditional sentence (dates in
   the business time zone, end date inclusive) or, with none live, the owner's
   discount reply and an owner alert. The Business Folder page warns about an
   offer live over 90 days with no end date.
3. While a draft waits, an easy answer that does not touch it sends on its own
   and the draft stays live with "The customer wrote again since this draft". A
   message that touches it replaces the draft, with any split-off FAQ or intake
   answer sent alongside the partial holding line. Scheduling never sends while
   a scheduling draft waits.
4. A customer who writes while a draft waits always gets a reply: the check-in
   reply (which alerts the owner) or the added-info reply. A price draft
   answered by a price sentence closes as `answered` and refuses Send with
   "Already answered".
5. Holding replies use only fill-ins computed from `hours.md`. The check-in time
   is off by default. Every new reply the folder needs is required at sync, and
   the example folder passes with all of them.

## Notes for testing

- The check-in alert follows `rules/sending.md` `right_away`: it reaches your phone at once only if `right_away` lists it, otherwise it waits for opening time like any other alert.

## Walk-through log

One line per concept, added the first time it shows up in this sprint.

- A test that grows with the code (plan 02.1-01): in `HoldingReplyTest`, `->with(fn (): array => array_combine(array_map(fn (MessageKind $kind): string => $kind->value, MessageKind::startsWait()), array_map(fn (MessageKind $kind): array => [$kind], MessageKind::startsWait())))` gives the test one row per kind that starts a wait, named by the kind's text. The list comes from the enum's own `startsWait()` method, so a kind added to that list later is tested the moment it is added, and if `holdingAlreadySent()` forgot it, that row fails. The dataset is a closure, not a plain array, so Pest only asks the enum when the test runs.
- A setting that is allowed to be missing (plan 02.1-03): `'check_in_hours' => 'bail|sometimes|integer:strict|min:1|max:12'` in `Validator::rulesFor()` uses `sometimes`, which tells Laravel's validator to run the other rules only when the field is present, so an owner who leaves the line out gets no error and the check-in time stays off, while `check_in_hours: two` or `0` or `13` is refused in plain words. `integer:strict` accepts only a real number from the file, not the text `"2"`. The reply check then asks `($file->frontMatter['check_in_hours'] ?? null) === null`: `??` gives `null` for a missing key without an error, so the same line covers both absent and empty. On the sending side `FixedReplies::render()` hands `strtr()` a list of `{fill_in}` => words pairs and it swaps every one in a single pass, so words that were just inserted are never searched again for more braces. `BusinessClock::checkInPhrase()` adds hours to the next opening with `$opening->addHours($hours)` and compares two times with `>` (Carbon objects compare by the moment they name), and a check-in past closing time becomes the closing time itself.
- A model answer that is allowed to be wrong (plan 02.1-05): in `FrontDeskAgent::schema()`, `$schema->boolean()->nullable()->required()` tells the model every field must be present but may be `null`, and `$schema->array()->items($schema->string())->required()` is a list of plain texts. The model's answer is untrusted, so in `AgentReply::fromStructured()` the six new fields are read by small private readers that never throw: `($data['asks_discount'] ?? null) === true` is true only for a real boolean true (the `??` gives `null` for a missing key without an error), `in_array($value, ['check_in', 'added_info'], true)` accepts only those two words, and `ReplyLabel::tryFrom()` returns `null` instead of an error for a word that is not a label. A wrong answer falls back to the safer path instead of costing a provider attempt. On the prompt side, `FrontDeskPrompt::instructions()` got a third argument written `?Message $waitingDraft = null`: the `?` means "a Message or nothing", and the `= null` default means every older call that passes two arguments still works.
- Checks a rule string cannot say (plan 02.1-06): `Validator::rulesFor()` handles the shape of a price sentence with Laravel's own rules (`price_sentences.*.sentence` is `required|string`, `price_sentences.*.amounts_cents.*` is a whole number), but "the dollar amounts written in the sentence must equal the amounts the file declares" compares two values, so it lives in a plain method, `priceSentenceErrors()`, that runs beside the rules and adds its own lines to the same report. Amounts stay integer cents all the way: `CodeChecks::dollarAmountsInCents()` reads `$2,000` as `200000` with `(int)` and string padding, the two lists are compared as sorted lists of integers with `!==`, and `dollars()` turns cents back into `$2,000` or `$89.50` with `intdiv()`, `%` and `number_format()`, never a float. The service-name check lower-cases both sides with `mb_strtolower()` (the multibyte version, so accented letters fold too) after `Str::squish()` collapses runs of spaces, so `AC  REPAIR` still finds `AC repair`.
- Dates compared as text (plan 02.1-07): an offer's `start_date` and `end_date` are checked by Laravel's own rule, `'start_date' => 'bail|required|date_format:Y-m-d'`, which accepts only a year-month-day date with dashes. Once both are known to be in that form, `Validator::offerFileErrors()` compares them as plain strings with `$end < $start`, because `2026-09-30` sorts before `2026-10-01` letter by letter exactly as the days do; no date objects are needed. `isCalendarDate()` first rebuilds the value with `DateTimeImmutable::createFromFormat('!Y-m-d', ...)` and checks it prints back the same, so `2026-02-30` is not mistaken for a date. In `FolderSync::compile()` the new `'offer' =>` arm of the `match` stores each file under its slug (`$compiled['index']['offer'][$slug] = $path`); without that arm the `default` arm would keep only one path for the whole type, so a second offer would silently replace the first.
- Naming a result with a small value class (plan 02.1-08): `PricePick` is a `readonly class`, so once it is built nothing can change a property, and its constructor is reached through named builders (`PricePick::send(...)`, `PricePick::hold($slug)`) instead of one long argument list, so a line like `PricePick::hold($slug)` reads as what it means. `ReplyParts::kind()` uses `match (true) { ... }`, which walks its arms top to bottom and returns the value of the first arm whose condition is true; that is how "a complaint line wins, then the partial holding line, then the model's words" is written in the order it applies. In `RunFrontDesk`, `array_filter(..., fn (PricePick $pick): bool => $pick->isSend())` keeps only the picks that send, and `array_map` turns them into plain sentences; `Cache::lock()` in the new test holds the same lock the sender waits for, which is how the test makes a send get refused.
