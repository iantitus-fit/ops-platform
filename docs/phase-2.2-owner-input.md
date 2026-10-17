# Phase 2.2 owner input (2026-10-17)

Input for `/gsd-discuss-phase 02.2` (Owner Loop) and the phone design pass. Ian's answers to situations seen in Phase 02.1 UAT test 1 (the Needs you phone walk), condensed from his words. Ian's answers are his stated direction and may change as he sees more of the system working. Items marked **Recommendation** are proposals to decide in discuss, not locked decisions. Items marked **Reopens** touch a locked decision and need an explicit call.

**Ian's frame:** the AI acts as the office admin. It acknowledges, records and reassures, and it tells the customer what happens next using only what the business has set up. It never resolves, offers or promises anything that needs someone who can (a schedule, availability, a price). The point is to take as much as possible off the plate of the people running the business, and to make sure no customer feels dropped. "We're not trying to create miracles, we're trying to serve people and give them the best experience they can have dealing with a business." Every option is chosen by the business at setup, and some businesses will not need all of them.

## 1. Who "the owner" is

**Ian:** "owner" is a label. It can be anyone the business gives access to, it can be several people, and the business owner may not be in daily operations at all. Don't let the word cause confusion in testing.

**Recommendation:**
- Two roles. The **business owner** sets the rules (the folder) and decides who gets access and who handles what. A **team member** is anyone given access to act on alerts and follow-ups; the owner can be one. 02.2's staff users are where these split.
- For testing, read "owner" as "whoever is on the inbox right now".
- What the customer hears: "our team" by default, each business chooses at setup. The example folder's replies say `{owner_name}` (Mike); if Sarah handles complaints, the customer is told Mike has it. New setup question: "Who should customers hear is handling it? (Default: our team.)"

## 2. Complaints: a follow-up for a person, not a held draft

Seen: UAT conversation 23. The complaint reply went out the same minute ("I'm sorry about this. I've passed your message straight to Mike, and he'll get back to you today if possible, or after we open at 8 am Monday."). The model's own reply was held as a draft with Send and Discard.

**Ian:** a draft waiting on a human looks like the customer is not being taken care of. Techs and office staff are in a crawl space, on a water tank install, at lunch; a draft can sit for hours. The AI should acknowledge the complaint, tell the customer it is recorded and will be addressed as soon as possible (each business words this its own way), and can tell them when they will be called or follow up if nobody has contacted them. "Drafts aren't held, but the right person gets the alerts and follows up on complaints."

**Ian, on timing:** a complaint comes in at 9 am, the AI acknowledges it and it is queued for follow-up. The business decides at setup that an automatic update goes out every hour (or every 2, 4 or 6 hours, its choice) letting the customer know they have not been forgotten, until a person steps in to communicate the next steps. "The AI doesn't need to draft anything to be held, only continue to follow up until a person has stepped in." The dashboard needs indicators so a person can see how important each one is. "No system will be able to make every single customer as happy as a business would like, but if the customer knows they aren't dropped because of negligence, that's the best it can do."

**Today:** the owner-written complaint reply goes out at once (02.1 D-31); the complaint alert follows the folder's timing (Ian, 2026-10-16); the model's words are held as a draft (Phase 2 GATE-01); the complaint reply goes out at most once per wait (G-02.1-1, Ian, 2026-10-17).

**Reopens:** Phase 2 GATE-01's wording, "a complaint is saved as a draft for the owner and never sent, whatever the model outputs". The safety point stays: the model's words about a complaint never go out. What changes is "saved as a draft" to "saved as a follow-up for the right person". Also 02.1 D-43 and D-44, which pre-fill a complaint draft with the owner's price or offer sentence: decide where that sentence goes when there is no draft (shown on the follow-up as a suggested text, say).

**Recommendation:**
- A complaint becomes a **follow-up item** for the right person, not a draft to approve. It shows who, what they said, what they were told and when, how long they have waited, and call and text buttons. The AI's suggested text appears only if the person asks for it.
- **Timed reassurance updates:** owner-written words in `rules/replies.md`, fill-ins computed in code like the holding reply's times, never written by the model. Cadence chosen at setup (every 1, 2, 4 or 6 hours, or off). They stop the moment a person sends anything in the conversation or marks the follow-up handled.
- They are a separate kind from the complaint reply, so G-02.1-1 still holds: no repeated apology for a new text.
- **Ian's answers (2026-10-17).** Build every option in the most open form and let the business set it at setup:
  - **Overnight pause:** Ian would pause overnight, with the last update of the day saying so. Built with no pause; setup decides. Because the built default is no pause, the setup must always ask this one, so no business sends hourly texts at 2 am by accident.
  - **Maximum number of updates:** none until a person has handled it, in whatever way the business defines handled. Built with no maximum; the business can set one.
  - **A new customer text:** Ian thinks it restarts the timer. Built to support either; the business chooses.
  - **Which holds get updates:** every held question (price, promise, discount, other), not only complaints. Built on for all; the business can narrow it. This fits D-24's customer-first principle and 02.2's planned update when the wait runs past the holding reply's promise.
  - Still to settle in discuss: whether the reply cap (Phase 2 D-52) counts these updates. STOP blocks them (Phase 2 D-40).
  - **Recommendation:** quiet-hours rules for automated texts differ by state; add "timed updates and overnight texts" to the attorney review already on the before-a-real-client list.

## 3. Alerts and routing

**Ian:** the business owner decides at setup whether to hear about every complaint, and how: by text in real time, by text and email, or by email or a daily briefing for a high-level view. The people doing the setup know who should get which alerts. It must be configurable for every complaint and for unanswered complaints. Weekends and after hours are the business's choice: some are completely off, some want to handle complaints immediately, and a complaint that arrives when nobody is around goes to whoever the business chose. The floor is that the customer is communicated with around the clock, so they know they are heard and it will be handled as soon as it can be.

**Ian's example:** a recent experience his family had with a dental office. An AI offered 10:15 and 10:30 slots when it was already 10:28, which lost trust. A person booked the appointment. Later the provider was out sick; a text said they would reschedule for Monday. The reply asked about Wednesday instead; the next text said the provider was out all week, and then nothing. A simple "we'll follow up to get you rescheduled" would have made the difference. Someone should have been alerted to follow up, either the owner or whoever the owner delegated.

**Maps to 02.2 scope:** staff users with their own alert choices, email alerts, re-alerts and a backup recipient when nobody acts, the customer update past the holding reply's promise, complaint routing to a named person (Ian, 2026-10-16). The 10:15-at-10:28 case is a Phase 3 rule: booking never offers a time that has already passed.

**Recommendation:** setup questions for who gets complaint alerts and who backs them up; the channel (text, email, daily briefing); every complaint or only unanswered after N hours; after-hours and weekend behavior.

## 4. The busy team member, and whether held drafts are needed at all

**Ian:** the business does not need an AI drafting replies and doing nothing with them. "If the AI is there to reply to customers that humans can't for whatever reason, what's the point in holding a draft when the person could just respond?" He is considering an available/unavailable switch so the AI does not respond every time a person is available. He is not in the trades day to day, so he wants a working trades owner's view before deciding.

**Maps to 02.2 scope:** assist mode (a person's reply stops the AI sending on its own; it keeps suggesting; it returns after a quiet period the business sets) and the after-hours on-call switch.

**Recommendation:** for complaints, section 2 removes the draft. For the drafts that remain (a price, promise or discount the business has not written a sentence for), decide in the design pass whether Send asks "the customer wrote again, send anyway or edit first?" Get a working trades owner's reaction to the follow-up flow before 02.2 discuss.

## 5. Who sent it

**Ian:** "Sarah's approved a draft. But did she send it? Why would she approve a draft and not send it? Does Mike need to micromanage Sarah? Mike should be able to see what happened and respond accordingly."

**Recommendation:** in the app "approve" and "send" are the same tap, so drop "approve" from what people see. The thread shows who sent each message: "Front desk" when it was automatic, the person's name when a person sent it, even if the AI wrote the words. 02.2 already records who acted.

## 6. The phone screens

**Ian (UAT test 1):** "I hate the way this looks on my phone. I would be confused myself."

**Seen:** the dashboard is four equal tables; Drafts waiting rows read "(no name)" and cannot be told apart; columns are cut off; nothing says what to tap first. On the Needs you card, Take over is the biggest button on the page; the draft box cuts off the text; when the "wrote again" badge shows, the "Customer wrote" label disappears and badge, time and text run together; a reply a person sent shows as "Front desk".

**Recommendation:** one short list ordered by urgency, safety and complaints first. Each item is a card: who (name, or phone number when there is no name), what they asked in plain words, how long they have waited, one clear tap. Leads and today's conversations below it or on their own page. See PROJECT.md "Frontend" open items.

**Reopens:** the 2026-10-11 decision that the admin stays stock Filament with a brand pass and that a UI-SPEC is written only for Phase 3's booking page. Ian's call.

## 7. Held from the 2026-10-17 role play (decide when the full workflow exists)

**Ian's call (2026-10-17):** 02.1 closes on wiring, not business specifics (recorded in `.planning/PROJECT.md`). The items below are held until the full workflow exists, from first contact to finished job.

- **Message shape (B):** the model writes a warm reply with the owner's fixed text inside it, answer first, and a short AI notice at the bottom. **Reopens** 02.1 D-07 (price sentence first) and Phase 2 D-50 (the notice leads the first automated text). Notice placement goes on the attorney review list. If the model's words fail their check, the fixed text goes alone, as today.
- **Open:** does B cover every fixed reply (complaint, holding, discount holding, check-in, added-info), or only price and offer sentences? Either way, the 911 safety line stays fixed, first and unwrapped.
- **When the front desk can't answer, it doesn't say so.** It moves to the step that gets the question answered: a visit or a person.
- **Intake questions for setup:**
  - the repeat-visit policy (is a return visit for the same problem charged?);
  - how an offer combines with other credits (the $89 diagnostic);
  - whether phrases like "make this right" belong on the promise list.
- **Phase 3:** the front desk sees job history (what was fixed, what was charged) and can offer real appointment times.
- **Jev tuning, due before the first client goes live:**
  - whether heat plus a newborn should trip the hazard reading;
  - CO symptoms with no alarm mentioned (headache, dizziness since the heat came on) read 0.05 on text A in UAT test 3, under the 0.075 bar. The reply model's possible_danger caught it there. The one path with no second net: if Jev misses symptoms like these while every reply model is down, D-51 sends the holding reply with no 911 line, and front_desk_failed waits on the folder's timing.
