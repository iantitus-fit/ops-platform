# Phase 2.1 owner input (2026-10-13)

Input for `/gsd-discuss-phase 02.1` and, for scheduling, `/gsd-discuss-phase 3`. Ian's answers to five situations seen in Phase 2 UAT round 2 (tests 1 and 2), condensed from his words. Ian's answers are his stated direction. Items marked **Recommendation** are proposals to decide in discuss, not locked decisions. Items marked **Reopens** touch a locked Phase 2 decision and need an explicit call.

**Ian's frame:** answers come from the business's knowledge base, the folder Ian sets up with the owner at onboarding. The faster a customer's question is answered, the more business gets done; an agent that always defers to the owner hurts speed to lead. Edge cases get settled in the setup conversation with each business. Ian wants to be quizzed on edge cases while building.

## 1. Ballpark price ("How much would a new upstairs system cost, installed?")

Seen: test 1, conversation 15. Held on price; the holding reply went out.

**Ian:** an HVAC company knows its rough ranges and the knowledge base should hold them, for example "an upstairs unit normally runs between $4,000 and $7,000." Gather context first where it matters: the current unit, its condition, whether the ductwork is ready. Transparent pricing wins customers (Endless Customers; a pricing calculator on the site is the same idea). Story from a podcast: a pool company insisted on sending someone out when the customer had sent measurements and only wanted a ballpark, and the customer went elsewhere.

**Reopens:** D-08 (price can never go in `send_on_own`) and D-01's code check on dollar amounts. Phase 3's walk-test example also treats "the agent would now quote a price" as a break.

**Recommendation:**
- A price may send on its own only when it is a range the owner wrote in the folder, checked in code: the numbers in the reply must match a folder range for that service, or the reply holds. Every other price still holds.
- The range sentence is a fixed template, owner fills the numbers and conditions, the way the life-safety line and the holding reply work, so it always says it is an estimate and that the exact number follows a look.
- A replacement or new-system service file (intake: current unit, condition, ductwork; plus a range) fits the existing service shape (`price_policy`, `price_cents`, `intake`). The example folder today has only `starting_at` repair prices and no replacement file.
- Risk: a customer holds the business to a quoted range. Ranges belong to the owner, never the model.

## 2. Scheduling ("Can you come Thursday morning?")

Seen: test 2, conversation 16. Held as "may want to talk to a person": asks_for_person scored 0.30, inside the 0.075 to 0.85 unsure band. Promise was 0.08.

**Ian:** the agent reads the schedule, sees what is open around the requested day (a few days either side), and offers times or books, following the business's rules from the knowledge base (for example four-hour windows). Needs read and write access to wherever the schedule lives.

**Recommendation:**
- This is Phase 3 (Booking and the Gate). Phase 3 as written books through a link to a page; Ian's version offers times inside the text thread. Settle which, or both (times offered in the thread, link to confirm), in `/gsd-discuss-phase 3`.
- In Phase 3 the schedule lives in the platform. An outside calendar is a connector (Phase 999.4).
- Now: the asks_for_person unsure band holds ordinary scheduling texts. Carry to 2.1 calibration with gate:export data. Changing the floor reopens D-45 (D-56 left it on purpose).

## 3. Discount ("Do you offer any discounts for first-time customers?")

Seen: test 2. Held on the agent's own `discount` label before Jev ran.

**Ian:** depends on the business. A standing offer, or a deal an ad is running, belongs in the knowledge base and the agent answers it confidently. Otherwise a warm deferral: "We like to take care of our customers. I'll make sure Mike knows you asked about a discount and he'll pick up the thread."

**Reopens:** D-08 (discount can never go in `send_on_own`).

**Recommendation:** same shape as price. An offer the owner lists in the folder (amount or percent, who qualifies, start and end dates) may be quoted, checked in code against the folder. Anything else holds, with the warm deferral as its holding reply.

## 4. A second question while one waits ("Do you service heat pumps?" after Thursday)

Seen: test 2. The heat-pump answer held on promise 0.22 because the draft added "Mike will follow up on your Thursday question." It replaced the Thursday draft and nothing reached the customer.

**Ian:** a safe answer goes out on its own even while another question waits. A yes-or-no question the knowledge base answers should never wait behind a hard one; a customer kept waiting on an easy answer may be lost.

**Recommendation:**
- Cheap part: tell the agent not to mention a question that is already with the owner (the holding reply covered it). The heat-pump answer alone scored low on every check.
- Structural part: one live draft per conversation (`messages_one_live_draft_per_conversation`) and a new draft replaces the old one. Answering the safe part while the hard part waits means splitting a reply, or sending the safe reply while the older draft stays live. Decide in 2.1, including what happens to the older draft's alert.

## 5. The holding reply's time promise ("he'll get back to you today")

**Ian:** don't promise "today" when nobody knows. Closer to a real receptionist: the message is with Mike; if he can get back today he will, otherwise first thing in the morning; text back to check in if you have not heard by 10 am tomorrow. What matters most is that the customer is sure they are in a queue and will not be dropped, since many businesses never respond. After hours, "when we open at 8 am tomorrow" is fine for now.

**Recommendation:**
- The wording is owner-editable today: `rules/replies.md` `holding_reply`, with `{owner_name}` and `{next_open}`. A check-in time ("by 10 am tomorrow") needs a new placeholder computed in code from `hours.md`, so the promise stays true.
- The "don't get dropped" part is already 2.1 scope: re-alert, a backup recipient, and an update to the customer when the wait runs past the holding reply's promise.

## Edge cases

Ian asked to be quizzed on edge cases during the build. Start the 2.1 discussion from this list.
