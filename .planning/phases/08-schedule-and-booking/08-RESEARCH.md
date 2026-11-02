# Phase 8: Schedule and Booking - Research

**Researched:** 2026-11-02
**Domain:** Laravel 13 / Livewire 4 customer page, Filament 5 owner screens, scheduler-driven texts, time-zone availability, the OutboundSender chokepoint
**Confidence:** HIGH on the repo (every integration point was read this session); MEDIUM on three design choices flagged in the Assumptions Log

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
### Locked, carried forward (do not reopen)
- DEC-01 to DEC-14 (PROJECT.md); 02 D-01 to D-56 (02-CONTEXT.md); 02.1 D-01 to D-51 (02.1-CONTEXT.md), with D-49 amended by D-26 below at Ian's explicit call; 04 D-01 to D-27 (04-CONTEXT.md); 05 D-01 to D-33 (05-CONTEXT.md); 06 D-01 to D-21 (06-CONTEXT.md); 07 D-01 to D-23 (07-CONTEXT.md), with D-21 and D-23 as amended 2026-10-31.
- The alert-timing and one-alert rules (ALRT-01, REQUIREMENTS.md; "one customer text raises one alert, the more serious one", Ian 2026-10-18, `app/Actions/RunFrontDesk.php:880`, recorded with 02.1 D-34 in 02.1-CONTEXT.md). D-34 below adds a reason to the order and changes nothing else.
- 02.1 IN-11 (02.1-REVIEW-DISPOSITION.md): never a missing alert for a duplicate.
- PROJECT.md Key Decisions "The project is the spine", "The model home is the next milestone" and "One owner per fact" (2026-10-19), the v2.0 floor plan (2026-10-21) and "The chokepoint rule" (2026-10-22).
- The WCAG 2.2 Level AA rule (CLAUDE.md; PROJECT.md Frontend) and the chokepoint rule (CLAUDE.md).
- 02 D-20 timing as built (`RaiseOwnerAlert::dueAt()`, `app/Actions/RaiseOwnerAlert.php:159`): a reason not under `right_away` goes out now while the business is open and waits for the next opening only after hours, unless `after_hours` is `right_away`.
- Carried facts this phase builds on: every automated text goes through `OutboundSender` and `TextChannel` is used only there and in `SendOwnerAlert` (06 D-09, 06-CONTEXT.md); opt-out blocks every outbound text (02 D-40, 02-CONTEXT.md); while paused the agent sends nothing (02 D-24, 02-CONTEXT.md); a contact has at most one open conversation, only the owner closes one (07-CONTEXT.md carried facts); projects are written only by `OpenProject`, `CloseProject`, `ReopenProject` and `RenameProject` (07 D-11, 07-CONTEXT.md); the appointment carries the link to its project (07 D-09, 07-CONTEXT.md); customer-facing strings carry no exclamation points or em dashes (02.1 D-41, 02.1-CONTEXT.md); a room writes only while it is in the live folder (04 D-08, 04-CONTEXT.md).

### The booking link
- **D-01:** Code appends the owner-written link line to a reply the agent labels `scheduling`, when the service resolved by D-02 has `book_online: true` and the room is live (04 D-08, 04-CONTEXT.md). It rides on the existing `scheduling` send_on_own right (`app/Enums/ReplyLabel.php`; `examples/example-hvac/rules/sending.md`), so no new decision right is added; the text is a combined text (model words plus a code sentence), an existing 02.1 D-49 kind (02.1-CONTEXT.md), and the model never writes the URL. Code leaves the link off while the contact has an upcoming booked appointment, so a booked customer asking about their time gets the reply without a second link. Affects: customer communication, operations, safety and compliance.
- **D-02:** The link books the service this scheduling reply is about (the reply's own `service_slug`, `app/Ai/AgentReply.php`). If the reply names none, the conversation's intake service, only when exactly one exists (`intake_answers.service_slug`). Otherwise no link, the way the price rule never guesses between two services. The page re-checks the service when it opens and when a time is tapped; if the service file is gone or `book_online` is now false, it offers no times and shows one plain line asking the customer to text the business. Affects: operations, customer communication, knowledge.
- **D-03:** The link line lives in a new file type the schedule and booking room owns, in `rooms/schedule_and_booking/` (for example `booking.md`), which also holds every booking setting decided below; it is the room's example file. The sync checks the link line holds `{link}` exactly once and has no exclamation points or em dashes. `booking_lead_minutes` stays in `business/hours.md`, where BOOK-01 names it. With the room live and no booking file, nothing customer-facing goes out (no link, confirmation or reminder): code has no default line. Booking by hand and Block time still work, with no buffer. The room's dashboard section names the file that turns the rest on, as an empty room names its files (04 D-21, 04-CONTEXT.md). Affects: knowledge, operations, customer communication. — **Reversibility:** costly — the file type and its keys end up in every client's folder.
- **D-04:** Link only. The page is the only way to book in Phase 8; times are not offered or booked in the text thread. Affects: customer communication, operations.
- **D-05:** The example `agents/front-desk.md` gets one owner-written line in its body: once the last intake question is answered, the front desk offers to book a visit. This is folder wording, the business's own ("The model home is the next milestone", PROJECT.md: wording and how it runs are the business's furniture), so it reopens nothing; `FrontDeskPrompt` and front-desk code are unchanged by it. No code fallback: if the model labels the offer anything but `scheduling`, no link goes out and that is accepted. Plans name every existing test the edit forces to change, by file and line, as 07 D-22 (07-CONTEXT.md) did. UAT checks in Dev chat that the offer comes back labeled `scheduling` and carries the link. Affects: customer communication, knowledge.

### Times offered (BOOK-01)
- **D-06:** The page offers start times on a step set in the booking file (example: every 60 minutes). An appointment stores a start and an end (start plus `duration_minutes`), and every overlap check compares spans, so arrival windows can be added later as another way to generate times. Affects: operations. — **Reversibility:** one-way — the appointments table and its start and end columns arrive by migration.
- **D-07:** A time is offered only while fewer booked appointments and blocks overlap it than the capacity. Capacity is fixed at 1 in Phase 8 with no setting, written as a count check so a setting or crews later is a field, not a rewrite. A job must end by that day's closing time. The check runs again inside a lock when the customer taps, on SQLite and Postgres both (DEC-06, PROJECT.md); a time taken meanwhile is refused with fresh times and one plain line. Affects: operations, safety and compliance.
- **D-08:** The booking file sets a drive buffer in minutes (example: 30), added to the end of every appointment's span in the overlap check, the new one included, so one van is never booked back to back. The customer never sees it, and a job still only has to end by closing. Affects: operations.
- **D-09:** The booking file sets how many days ahead the page offers (example: 14). Times are grouped under each day's heading, earliest first, and only days with an open time appear. How the days are shown on a phone (all open, or one day at a time) is left to `/gsd-ui-phase 8`, held to criterion 6, since 14 days of hourly starts can be close to 100 buttons. With no open time in the range, the page says so in one plain line and asks the customer to text the business. Affects: operations, customer communication.
- **D-10:** On the page, the lead time is counted from the later of now and the next opening, so a booking made after hours cannot take a time inside `booking_lead_minutes` of the next opening: a 10 PM booking in the example can take 10:00 the next morning, not 8:00. During open hours nothing changes. This is stricter than a plain "now plus lead" and fits BOOK-01 and `hours.md` ("Appointments start no sooner than two hours after a customer asks"); it pairs with D-33, where an after-hours booked alert waits for the opening. Affects: operations, team.
- **D-11:** Times are shown and stored against the business timezone from `business/profile.md`; the page names the zone once at the top and never converts to the phone's zone. A time that has already passed is never offered (`docs/phase-2.2-owner-input.md` §3). Availability tests cover the fall-back and spring-forward days. The page's rate limit follows the `throttle:leads` precedent (`routes/web.php`), numbers at plan time. Affects: operations, customer communication, safety and compliance.

### The link token
- **D-12:** Each link is a stored random token; only its hash is stored, as Laravel stores password reset tokens, so a copy of the database holds no working link. The row records the contact, the conversation, the service, the message that carried the link and the expiry. A newer link for the same conversation supersedes older ones, and a link can be revoked. The BLUEPRINT Ideas inbox entry "On my way text and technician tracking" is updated to assume this token mechanism instead of Laravel temporary signed URLs. Affects: safety and compliance, customer communication, operations. — **Reversibility:** one-way — a new table by migration.
- **D-13:** A link expires after a number of days set in the booking file (example: 7). A link books at most once, ever: after a booking it shows that appointment (service, day, time) and never offers times again, even if that appointment is later cancelled; a second booking needs a new link (D-33 depends on this). The page shows the business name and the service, never the customer's name, phone or address, so a forwarded link leaks nothing. An expired, superseded or revoked link says so in one plain line and asks the customer to text the business. Affects: customer communication, safety and compliance.

### Appointments: states, writers and owner actions
- **D-14:** An appointment is booked, cancelled or done. Only booked holds time. Done is marked by the owner only, never automatically, and only once the start time has passed; marking done does not close the project (D-24). Cancel is final and is fixed by booking again; done is not undone in Phase 8, since nothing reads it yet. Affects: operations, sales.
- **D-15:** Only named actions write appointments and blocks, and an architecture test lets nothing else write those tables, as `tests/Unit/ProjectWritesTest.php` does for projects: Book (a customer on the page, or the owner by hand, recording who; a page booking also records the link token row it came through, as provenance and as the path to the booked alert's subject message), Cancel, MarkDone, EditVisitNote (D-18), BlockTime and RemoveBlock. Reminder and confirmation state lives in its own table (D-31), so no reminder writer joins this list. Each action records who acted and writes one timeline entry. None is an agent action, by the reasoning of 06 D-09 (06-CONTEXT.md) and 07 D-11 (07-CONTEXT.md): no model decides or writes an appointment. The chokepoint rule applies the day an agent books, cancels or marks done. The link row is provenance, not a chokepoint approval. Affects: safety and compliance, operations.
- **D-16:** Book by hand, Cancel and Mark done live on the contact screen beside the projects list; each schedule screen row links to its contact. Block time and Remove block live on the schedule screen: a block holds a span with a short owner note and no contact, project or text, and counts in the overlap check. Cancel, Mark done and Block time each confirm in a dialog naming the appointment or the span, as 07-09's Close and Reopen do (T-07-26, 07-UI-SPEC.md). The cancel dialog says in plain words that the customer is not texted, so the owner tells them. Move is not its own action: the owner cancels and books again. Affects: operations, team.
- **D-17:** Booking by hand may pick a time outside `hours.md` hours or inside the lead time, but never a past time and never one that overlaps a booked appointment or block while capacity is 1. The owner picks the project in the dialog from the contact's open projects or a new one; with no open project it opens one as D-22 says. It sends the confirmation under D-28 and raises no booked alert (D-33). Affects: operations, sales, team.
- **D-18:** An appointment carries an optional owner-typed visit note (an address, a gate code), added or edited on any booked appointment, from a page booking or by hand, through EditVisitNote. It shows on the schedule list and on the card as quoted text, neutralized like 07-03's owner-typed project name (07 D-13, 07-CONTEXT.md), and is never matched or searched. A page booking carries no note at booking, since the page never asks for typing. Affects: operations, customer communication.

### Contacts by hand and the automatic-texts switch
- **D-19:** The owner can add a contact by hand. This reverses the Phase 1 rule in `ContactResource::canCreate()` (`app/Filament/Resources/Contacts/ContactResource.php:30`, "never by hand"); Ian's call, 2026-11-02. It goes through the same matching as an inbound, phone first and then email (01 D-12, 01-CONTEXT.md), so a later text lands on the same contact. A hand-added contact starts as Lead, like an inbound, and shows in New leads for 7 days; no status rule changes (07 D-07, 07-CONTEXT.md). It opens no project on its own; booking by hand opens it. Affects: sales, customer communication, safety and compliance.
- **D-20:** Each contact who has never texted the business has a switch named for what the platform sends on its own (for example "Automatic texts: confirmations and reminders"), off by default. The owner turns it on when the customer agrees, on a call for example; the platform records who turned it on and when. A text from the customer turns it on, recorded with that message; a text that is itself STOP never does, it records the opt-out as today and the switch stays off (the test 07 D-23 as amended uses, 07-CONTEXT.md). The switch governs Automated sends only: the owner's own reply stays the owner's judgment, as it is for a form lead today. For a contact who has never texted with the switch off, the reply box shows one plain line that the customer has not texted the business or agreed to texts; it informs and does not block. Affects: safety and compliance, customer communication, team.
- **D-21:** `OutboundSender::whyBlocked()` refuses every Automated send unless the number has texted the business or the switch in D-20 is on, checked where opt-out is (02 D-40, 02-CONTEXT.md). Owner replies and the life-safety line keep today's rules. `ProcessInbound` keeps 02 D-41's per-conversation rule (02-CONTEXT.md) for front-desk replies, unchanged (`app/Jobs/ProcessInbound.php:82`); the agent still never texts a form lead first. The front desk already answers only texters, so its behavior does not change; the check now also sits at the chokepoint. Plans name every existing test the move forces to change, by file and line. Affects: safety and compliance, customer communication.

### Appointment and project
- **D-22:** A booking for a contact with no open project opens a new one through `OpenProject`, never reopening a closed one: a returning customer's new booking is a new job (07-CONTEXT.md Deferred). A page booking opens it with a new `ProjectActor` for a customer booking; a booking by hand opens it as the user. Each has its own fixed timeline sentence. The project links to the link's conversation (page) or the contact's open conversation (by hand), per 07 D-06 (07-CONTEXT.md), and to none when the contact has no conversation (a hand-added contact); no conversation is created for it. The four-writer test (07 D-11) holds. Affects: sales, operations. — **Reversibility:** costly — the new actor value is stored on project rows.
- **D-23:** A page booking picks its project without guessing at any step: first the open project tied to the link's conversation, only when exactly one open project is; otherwise the contact's only open project, when there is exactly one; otherwise a new project (D-22). Affects: sales, operations.
- **D-24:** No project closes on its own; 07 D-05 (07-CONTEXT.md) stands. When the owner marks a project's last booked appointment done, the same dialog offers to close the job too, unchecked by default, so a remodel with visits not yet booked stays open unless the owner ticks it. Affects: operations, sales.
- **D-25:** Closing a project is refused while it has a booked appointment still ahead; the close dialog names the visit and asks the owner to cancel it first. This adds a condition to `CloseProject` (07 D-11, 07-CONTEXT.md) and leaves 07 D-05 standing. No appointment is ever booked onto a closed project; past, cancelled and done appointments stay on it as its history. Affects: sales, operations.

### Texts the platform starts: confirmation and reminders
- **D-26:** 02.1 D-49 (02.1-CONTEXT.md) is amended, Ian's explicit call (2026-11-02), with one new kind: a text the platform starts about a booked appointment. BOOK-04's reminders cannot be sent without it, because `OutboundSender` requires `gate.customer_reading` for every Automated send (`app/Actions/OutboundSender.php:203-204`), and a reminder answers no customer text; the confirmation reuses the same kind rather than a second amendment. The kind needs: an appointment id in meta, still booked and still ahead when sent; a body that is an owner-written line from the live booking file, rendered by code, with no model words (meta.parts carries no `model_words`); and no inbound text in that conversation still waiting for its reading or its front-desk run, so a cheerful reminder never goes out over an unanswered "I smell gas". While such a text waits, the send waits and retries; the wait ends when the appointment is no longer booked or no longer ahead, and the send is then dropped. Opt-out stays first (02 D-40), D-21 applies, and the existing kinds are unchanged. Affects: safety and compliance, customer communication, operations. — **Reversibility:** costly — every automated text passes this sender, and the kind becomes part of what it accepts.
- **D-27:** A confirmation or reminder that does not go out, for the unread-text drop in D-26 or any refusal (opted out, switch off, closed or paused conversation), writes one timeline note on the contact naming the text and the reason, and raises no owner alert. Affects: customer communication, operations, team.
- **D-28:** The customer is texted an owner-written confirmation line from the booking file after a booking, checked by the sync like the link line (placeholders for service, day and time are the planner's). A page booking's confirmation goes at once, whatever the hour, because the customer just booked. A booking by hand's confirmation goes at once inside the send window (D-29), and outside it waits for the window's next opening, skipped only if the appointment has started by then. It is sent right after the booking commits, with the sweep in D-31 as the backstop, as owner alerts are. A cancel sends nothing automatic (D-16). Affects: customer communication, operations.
- **D-29:** The booking file lists reminder lead times, how long before the start each reminder goes (example: one, 24 hours before). Each fires once per appointment. A reminder whose time had already passed when the booking was made is skipped silently; there is no catch-up. The booking file also sets a send window in business time (example: 8 AM to 8 PM); both ends count as inside. A reminder due outside the window goes at the end of the window before: a 2-hour reminder for an 8 AM visit, due at 6 AM, goes at 8 PM the evening before; a 24-hour reminder for an 8 AM visit comes due at 8 AM the day before and goes on time. If the moved time had already passed when the booking was made, it is skipped with no note. Two reminders for one appointment that land at the same moment after moving send once. The window's legal bounds are on the attorney list (D-44). Affects: operations, customer communication, safety and compliance.
- **D-30:** A confirmation or reminder goes in the contact's open conversation. With none open, the platform opens a new conversation for the text under the one-open rule and its index (`conversations_one_open_per_contact`): a second way to start a conversation, beside an inbound; "only the owner closes one" and "at most one open" stand. A paused conversation keeps 02 D-24 (02-CONTEXT.md): the text is refused and leaves its D-27 note, and is never sent in a new conversation instead, since the owner is handling that thread. A conversation the platform starts opens no project and changes no contact status. Affects: customer communication, sales.
- **D-31:** Confirmations and reminders are tracked in their own table, one row per appointment and lead time (the confirmation is a row with lead time zero), claimed before sending under the owner-alert rule: 06 D-06 and D-07 (06-CONTEXT.md), the same claim, the same 2-minute wait and the same every-minute sweep, reused as they are. A reminder exists to prevent a no-show, which costs the business a trip; the only duplicate is a crash after the carrier accepted the text, which costs a second text. A released row re-checks every condition before it is sent again: still booked, still ahead, inside the send window, nothing unread. A cancelled appointment's pending rows never send. A scheduler entry joins `routes/console.php`. Affects: operations, safety and compliance, team.
- **D-32:** No customer self-service change or cancel in Phase 8. The booked view, the confirmation and each reminder say how to change: text the business. The owner cancels and books again by hand. Phase 8 leaves room for self-service cancel later: Cancel records who cancelled, the booking file takes new settings additively, and the booked view can take a Cancel button. Affects: customer communication, operations.

### Owner alerts (BOOK-05)
- **D-33:** A new alert reason for a booked appointment. Only a customer booking on the page raises it; a booking by hand raises none, since the owner made it. Its subject is the message that carried the link (stored on the token row, D-12), so the key keeps today's shape (`c{conversation}:m{message}:{reason}:u{user}`, `app/Models/OwnerAlert.php:67`) and 06 D-08 (06-CONTEXT.md) stands. Because a link books at most once (D-13) and a new link comes only in a new scheduling reply, a new message and so a new key, no second booking in one conversation can share a key, and no booked alert is dropped as a duplicate (02.1 IN-11, 02.1-REVIEW-DISPOSITION.md). It is not listed under `right_away` in the example's `rules/sending.md`: it goes now while the business is open and at the next opening after hours (D-10 keeps the lead time intact). Exactly once through the existing claim (ALRT-01; 06 D-06, D-07). Affects: team, operations, safety and compliance.
- **D-34:** A second new alert reason, the change request: when a contact with an upcoming booked appointment texts, and either the reading of the text or the agent's reply label says scheduling, the owner is alerted. Its subject is that inbound text, in today's key shape, so 06 D-08 stands. The alert names the booked visit (service, day, time). The front desk replies as it does today; a customer confirming their time also alerts, which is accepted. It follows `owner_alert` timing and is listed under `right_away` in the example. Under the one-alert rule it ranks above `check_in` and `draft_waiting` and below `complaint`, `asked_for_person`, `life_safety`, `paused_message` and `front_desk_failed`. Why: a held "Sure, Wednesday works" raises `draft_waiting`, and an owner who approves it from that alert can promise the move without moving the appointment, leaving Tuesday booked, its reminder going out and Wednesday still offered. The rule stands; only the order is new. The reason joins the one-alert order inside `RunFrontDesk`, the front-desk code change this decision implies; plans name every test it forces to change, by file and line. Affects: team, customer communication, operations.

### The schedule and booking room (ROOM-12)
- **D-35:** The room's key and folder are `schedule_and_booking` and `rooms/schedule_and_booking/` (05 D-01, 05-CONTEXT.md). It owns the booking file (D-03) and lists its tables in its Room (06 D-04, 06-CONTEXT.md): appointments, blocks, booking links and the confirmation and reminder sends. It declares `connector: none`: it talks to no outside system in Phase 8 (v2.0 floor plan, PROJECT.md); Google Calendar stays backlog 999.4 and adds the slot when it lands. It is optional, off the never-left-out list (04 D-09, 04-CONTEXT.md). The `needs` field (04-CONTEXT.md Deferred; 07 D-20, 07-CONTEXT.md) is not added: no room needs another room's records, because booking opens its own projects, projects are platform-owned (07 D-10) and the front desk that adds the link can never be left out; the note is closed as not needed in v2.0. The `CONTEXT.md` body and the `reads` list (folder paths only) are the planner's. Affects: operations, knowledge, connectors and data. — **Reversibility:** costly — the folder name ends up in every client's folder (05 D-01).
- **D-36:** The sync refuses a folder where the schedule and booking room is live and any connector file owns `schedule`, in plain words naming both files (for example "the schedule is owned by connectors/x.md; the schedule and booking room would keep a second one; turn the room off, or have the connector read the schedule instead"), and the previous version stays live. A connector that only reads the schedule is fine with the room live. A business whose outside calendar owns the schedule turns the room off, and the folder passes; with the room off the front desk sends no link and nothing else changes. Lifts when 999.4 brings a real calendar connector. ("One owner per fact", PROJECT.md.) Affects: connectors and data, operations, knowledge.
- **D-37:** The sync refuses a folder that drops the room while any booked appointment is still ahead, in plain words naming how many and the earliest, and says how to stop new bookings without dropping the room: set `book_online: false` on the services, which stops the link while booked visits keep their reminders. The previous version stays live. Past and cancelled appointments never block it. (04 D-08, 04-CONTEXT.md, stands: a room off writes nothing.) Affects: operations, knowledge, customer communication.

### The card, the schedule screen, status and service (BOOK-03)
- **D-38:** The customer card gets a "Next appointment" line near the top when one is booked ahead (day, time in the business zone, service, project label), and each project line in the projects section lists its appointments with their state. Timeline entries record booked, cancelled, done, and a confirmation or reminder sent or not sent (D-27). All are drawn by `CustomerCard` as platform lines with fixed code wording, like 07 D-13 (07-CONTEXT.md), and the visit note is quoted (D-18). A contact with no appointment renders exactly as today. Plans name every existing card test the change forces, by file and line, as 07 did. Affects: customer communication, operations, owner intelligence.
- **D-39:** The front desk sees the appointment lines on the card it already loads (02 D-36, 02-CONTEXT.md; PROJ-02). No new front-desk rule: the existing promise label still holds a reply that states a time or a visit. UAT adds a Dev chat run where a booked customer asks "what time are you coming?" and the reply is checked. Affects: customer communication.
- **D-40:** The schedule screen is a stock Filament list page: booked appointments and blocks from today forward, grouped under each day's heading, earliest first, with the service, the customer (a link to the contact), the project label and the length; Block time and Remove block live here. A filter shows past, cancelled and done. The room's dashboard section shows today's and the next few visits. No new package. Affects: operations, team.
- **D-41:** A booking changes no contact status; what makes a contact a Customer stays with backlog 999.1 (07 D-07, 07-CONTEXT.md). New leads and `records/open-leads.md` are untouched (07 D-15, D-17). Affects: sales, marketing.
- **D-42:** Each appointment stores its service, the slug and the name at booking, so a renamed or removed service file does not lose it. The project shows its services through its appointments; there is no service field on the project. There is no address field in Phase 8. A business that needs the visit address asks for it as a free-text intake question in its service file (the sync already allows a question with no options, `app/Icm/Validator.php:227`); the answer shows on the card with the intake answers; the example folder's services are unchanged in Phase 8, so front-desk intake tests do not churn. The owner's visit note (D-18) holds an address for a booking by hand. Affects: operations, sales, knowledge.

### Accessibility and the UI contract (A11Y-02)
- **D-43:** The booking page gets the axe check and Pest browser tests in CI: keyboard order, Tab reach at desktop width and at 375, focus moving to the confirmation, each time named with its day and time, and errors as text beside the field. The live keyboard-only and VoiceOver walk happens at Phase 8 UAT, where 200% zoom and the 24-pixel tap targets are checked (criterion 6). The owner screens (the schedule page, the contact screen's appointment actions and dialogs, and the room section) get the axe check and browser tests for keyboard and focus in CI now; their live VoiceOver listen moves to the Phase 11 owner-screen pass (A11Y-03), as Phases 5 and 7 did. 07 IN-08, IN-09 and IN-11 (07-REVIEW-DISPOSITION.md) land with this first walk: the focus wait in `ProjectsRelationManagerKeyboardTest` rethrows anything but a timeout, the dashboard walk's Tab cap in `LeadsBootTest` is measured and recorded, and the key-press helper moves to `tests/Pest.php`. `08-UI-SPEC.md`, from `/gsd-ui-phase 8`, covers the booking page in full, phone-first, and records the stock-Filament contract for the owner screens in short, as `07-UI-SPEC.md` did (07 D-21 as amended, 07-CONTEXT.md). Reduced motion is the UI-SPEC's. The booking page has a place for a privacy notice and an accessibility statement; their wording comes from the attorney list before the first client, and the tour's fictional business uses plain placeholder wording until then. Affects: safety and compliance, customer communication, operations.

### Before a real client
- **D-44 [informational]:** The attorney list (BLUEPRINT section 10) gains: whether texting the business, or booking on the page, is enough consent for confirmations and reminders, for every contact; whether a customer's spoken yes on a call is enough (D-20); how the A2P registration describes that opt-in; and the wording of the booking page's privacy notice and accessibility statement. "The hours booking reminders may go out" is already on it and now bounds D-29's window. Affects: safety and compliance, customer communication.

### Claude's Discretion

- Names: the booking file's name and field keys, the new reasons, the new `ProjectActor` value, the new message kind, the actions, tables and columns, in the house style.
- Wording in the PlainWords style, with no exclamation points or em dashes: the example's link, confirmation and reminder lines, the page's plain lines, the alert texts, the timeline sentences, the dialogs, the sync refusals, the reply-box line in D-20 and the switch's label.
- The placeholders the link and confirmation lines may use, as long as the sync checks them.
- How "never two" and the tap-time re-check are enforced on SQLite and Postgres (a lock, a conditional insert or a constraint), within D-07.
- How the link token is generated and hashed, within D-12.
- The rate limit numbers (D-11), the sweep's cadence and its scheduler entry (D-31).
- The test layout for the room's boot test, the appointment-writes architecture test and the browser tests.
- The room's `CONTEXT.md` body and its `reads` list (folder paths only), from D-35.

### Deferred Ideas (OUT OF SCOPE)

- **[deferred] BLUEPRINT open item, customer communication and operations, after v2.0:** offering or booking times inside the text thread (`docs/phase-2.1-owner-input.md` §2), revisited when "No new front-desk depth" (PROJECT.md, "The model home is the next milestone") can be lifted; when it comes, it needs a named chokepoint and a decision-rights rules file the sync checks. Affects: customer communication, operations.
- **[deferred] BLUEPRINT open item, operations, next to crews:** arrival windows and how many jobs a window holds, pulled by the first client that books in windows; capacity above one. Affects: operations.
- **[deferred] BLUEPRINT open item, owner intelligence:** a no-show state, where it would be counted. Affects: owner intelligence, operations.
- **[deferred] BLUEPRINT open item, operations:** a Move action, revisited if the customer is to be texted when the owner changes a booking. Affects: operations, customer communication.
- **[deferred] BLUEPRINT open item, operations and finance:** customer self-service cancel. The booking file sets a cancel-before window the business chooses (24 hours, 48 hours); before it, the booked view offers Cancel, confirmed first and recorded as the customer; inside it, the page shows the business's late-cancel policy and offers to text the business, or to cancel with the policy applied. A late-cancel fee needs the policy shown and agreed before booking and a way to charge, so it waits for backlog 999.1 (payments) and the attorney list. Phase 8 leaves room (D-32). Affects: operations, finance, customer communication.
- **[deferred] Backlog 999.1:** an undo for done, where an invoice would read it; what makes a contact a Customer (D-41). Affects: finance, sales, marketing.
- **[deferred] Phase 11 owner-screen pass (A11Y-03):** a calendar grid with a switch between month and day views. It needs a calendar package, which comes back to Ian for approval (04 D-18, 04-CONTEXT.md), and a grid is harder to use by keyboard and screen reader than a list, so the Phase 8 list stays as the accessible view beside it. Affects: operations, team, safety and compliance.
- **[deferred] BLUEPRINT open item, operations and sales:** a structured service address and service history by address, pulled by the first step that collects an address (an intake question, owner entry or a connector). Automatic matches stay phone or email; an address match under a different name asks a person on a dashboard screen, never on the card (DEC-03, PROJECT.md). Affects: operations, sales, customer communication, owner intelligence.
- **[deferred] Phase 9:** minutes to booked (BOOK-06), `records/today.md` and the nightly rebuild. Affects: owner intelligence.
- **[deferred] Backlog 999.4:** a calendar connector and the room's slot; lifts D-36's refusal. Affects: connectors and data, operations.

### Ian's planning notes (2026-11-02, from CONTEXT.md; honor these)

## Planning notes (Ian, 2026-11-02)
- D-04 and D-41 are covered by tests in the plans they touch: no inbound text creates or changes an appointment; a booking leaves the contact's status unchanged.
- A plan that changes shared code (OutboundSender, RunFrontDesk's alert order, CustomerCard, CloseProject, the example folder, ContactResource) first sweeps for existing tests the change would hollow out (a count or "nothing sent" assertion that would pass without running its branch) and re-points them in that same plan, each named by file and line, as Phases 6 and 7 did.
- A queued job that gains a required field drains the queue first (BLUEPRINT section 10).
- No new package without Ian's approval (04 D-18): if research recommends one, name it and stop before planning on it.
- Wave order: the new tables, the OutboundSender changes (D-21, D-26) and the alert changes (D-33, D-34) land in early waves, so CI proves them on Postgres before the waves that build on them. Ian pushes between waves.
- Browser tests use Playwright locator waits only; in pest-plugin-browser 5.1 waitForFunction() and waitForURL() never wait (STATE.md, Phase 7).
- If the planner recommends splitting the phase, stop and bring it to Ian before any plan is written.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROOM-12 | Schedule and booking room, to the room standard | Room, provider, file type, boot test and SPEC-table pattern copied from the Money/Leads rooms (Patterns 1, 9); new sync checks for D-36/D-37 (Pattern 10) |
| BOOK-01 | Availability from hours, closures, lead time, service duration, existing appointments, business timezone | `BusinessClock` extension + one `Availability` class; span-overlap query; DST verified (Pattern 3, Pitfall 3) |
| BOOK-02 | Agent texts a link; Livewire page; tap a time; confirmed without typing | Token (Pattern 2), link appended at the send step (Pattern 5), Livewire page (Pattern 6), Book action under a lock (Pattern 4) |
| BOOK-03 | Appointment on timeline, customer card, dashboard schedule screen | Platform timeline lines, `CustomerCard` change, Filament Page + relation manager + `RoomSection` (Patterns 11, 12) |
| BOOK-04 | Reminders sent by the scheduler | Sends table, claim reuse, new `OutboundSender` kind, `routes/console.php` entry (Patterns 7, 8) |
| BOOK-05 | Owner alerted once on booking and when a conversation needs a human | Two new `AlertReason` cases through the existing dedupe key and claim (Pattern 8, Pitfalls 8, 9) |
| A11Y-02 | Booking page meets WCAG 2.2 AA | UI-SPEC is approved; research adds the Livewire-specific traps (Pitfalls 5, 6, 7) and the test plan (Validation Architecture) |
</phase_requirements>

## Summary

Phase 8 is large but almost every piece has a working sibling in the repo: the owner-alert claim/sweep (`SendOwnerAlert`, `SendDueAlerts`) is the template for the confirmation/reminder sends; `OpenProject`/`CloseProject` and `tests/Unit/ProjectWritesTest.php` are the template for the appointment actions and their architecture test; `MoneyServiceProvider` plus `ContentBriefType` are the template for a room that defines a file type; `LeadsSection` is the template for the room's dashboard section; `ProjectsRelationManager` is the template for the appointments tab. The new ground is (1) the first public, token-gated Livewire page, (2) availability on the business wall clock, (3) a new kind of automated text with no customer text to answer, and (4) the two changes to shared, hot code: `OutboundSender` and `RunFrontDesk`'s one-alert order.

**No new Composer or npm package is needed.** Everything in the approved UI-SPEC and CONTEXT is reachable with what is installed (Laravel 13.35.0, Livewire 4.4.7 via Filament, Filament 5.10.1, Carbon 3, Tailwind 4.3.3, pest-plugin-browser 5.1.2, Playwright 1.63.0). Section "Standard Stack" lists the options that would need a package and the no-package choice for each; none of them is recommended.

Eight findings change how the plans must be written, and none is stated in CONTEXT or the UI-SPEC:

1. **A page-route `throttle:booking` does not cover the taps.** Livewire sends every tap and every "Book this time" as a POST to its own update endpoint, and Livewire re-applies only a short list of "persistent" middleware to those requests; `ThrottleRequests` is not on it. The rate limit must be called inside the component (Pitfall 1).
2. **CI has no `npm run build` step and `public/build` is gitignored**, so the new layout cannot load Vite unconditionally and the 48 px tap-target browser test cannot measure unstyled buttons. A CI build step is a required plan item (Pitfall 2). `.github/workflows/tests.yml` is Ian's to push.
3. **A third `ProjectActor` value is mislabeled by four existing binary ternaries** (`CustomerCard.php:200`, `ProjectsRelationManager.php:79`, `OpenProject.php:101`, `LeadsSection.php:110`): each treats "not Lead" as "the owner" or as a user. Without a sweep a customer-booked project reads "opened by the owner" (Pitfall 4).
4. **`tests/Unit/ProjectWritesTest.php` flags any app file that imports `App\Models\Project` and calls `->update(` / `->save(` / `->delete(`**, and its arch rule limits who may call `OpenProject`/`CloseProject`. The Book action (which opens projects) and the sends code must be written to pass it, or the test is deliberately extended in the same plan (Pitfall 10).
5. **`OutboundSender::partsOf()` returns `[]` for any kind it does not name** (`OutboundSender.php:398`), and `whyReadingBlocks()` ignores unknown part names. A new booking kind or a new `booking_link` part name would silently skip the reading check unless it gets its own arm (Pitfall 9).
6. **When the picker is replaced by the check step, native `<details>` open state is lost** unless the picker stays in the DOM (hidden) or the component remembers it. `wire:ignore.self` alone does not help because the elements are removed (Pitfall 6).
7. **`handled_at` on an inbound message is stamped when a run *claims* it** (`ClaimInboundBurst.php`), not when the run finishes. D-26's "no inbound text still waiting" is therefore "no customer message with `handled_at` null"; a text mid-run is already "handled" (Pitfall 11).
8. **`OwnerAlertText::build()` always appends `They wrote: "<last customer text>"`** (`OwnerAlertText.php`, `build()`), which is wrong on a booked alert (the last customer text may be days old). The booked alert needs its own body shape (Pitfall 8).

**Primary recommendation:** Build in this order (matches Ian's wave note): tables and models; `OutboundSender` changes (D-21, D-26) with the full existing-test sweep; the two `AlertReason` cases and the `RunFrontDesk` order change; availability + token + Book action + architecture test; sends table + sweep; the room (provider, file type, sync checks, example folder, SPEC rows); Livewire page; owner screens; card and timeline; UAT. Use `Cache::lock` plus a transaction re-check for "never two", a sha256-hashed random token for the link, a precomputed `due_at` per send row, and keep the picker in the DOM while the check step shows.

## Project Constraints (from CLAUDE.md)

- Files hold how the business runs, the database holds what happened; business config is read only through `App\Icm\ActiveConfig` (`.ai/rules/app.md`). Money is integer cents (no money in this phase).
- Tests first for anything touching money, sending or the sync. Run `php artisan test` before calling a step done (Boost: run the narrowest set, ask Ian to run the full suite).
- Sending authority is enforced in code. **Every agent action goes through a code chokepoint**; a plan that adds an agent action names its chokepoint. Phase 8 adds none (D-15): appointments are written by named actions, not by an agent. Name the chokepoint for the *texts* (OutboundSender) and the actions (an architecture test).
- Ian reads code with AI help: the first time a Laravel concept appears (scheduler, notifications, Livewire pages, time zones, locks, rate limiting) explain it in a few plain sentences and add a line to the walk-through log at the end of the current sprint file in `docs/sprints/`.
- Git under GSD: local commits, one per task, on the phase branch; never push, merge or rewrite history.
- No secrets or real client data. Examples fictional.
- **WCAG 2.2 AA in the markup, never an overlay; every image alt text; keyboard-only and screen-reader walk at UAT.**
- `docs/BLUEPRINT.md` is updated in the same commit as any decision/plan that changes an area; add an "Affects:" line.
- Boost rules: use `php artisan make:` commands, Pint (`vendor/bin/pint --dirty --format agent`) after PHP edits, do not create documentation files unless asked, do not delete tests, do not add dependencies without approval, create factories with new models, use `search-docs` for ecosystem behavior.
- `.ai/rules/index.md` maps `app/**`, `database/factories/**`, `database/migrations/**`, `tests/**` to rule files; all four contain only the "money is integer cents" rule (plus ActiveConfig for `app/**`). [VERIFIED: .ai/rules/app.md, migrations.md, tests.md, factories.md read this session]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Offering times (availability) | API / Backend (`Availability` service) | Database (overlap query) | Pure server computation from `ActiveConfig`; the browser never computes a time (D-11) |
| Tap-time re-check and booking | API / Backend (Book action under lock) | Database (transaction) | Race safety must live where the write is (D-07) |
| Picking a time, focus, disclosures | Browser (native `<details>`, Alpine focus) | Frontend Server (Livewire render) | Native elements; Livewire only re-renders state |
| Link token mint/verify | API / Backend | Database (hash column, unique index) | Bearer credential; never in the browser beyond the URL |
| Rate limiting taps | API / Backend (inside component) | Cache (RateLimiter) | Page-route middleware does not see Livewire updates |
| Confirmation and reminders | API / Backend (scheduler job + `SendBookingText`) | Database (sends table = the claim) | Exactly-once by conditional update, as owner alerts are |
| Owner alerts for booked / change request | API / Backend (`RaiseOwnerAlert`) | Database (dedupe key) | Existing path; reasons added |
| Schedule, appointments tab, contact actions, section | Frontend Server (Filament 5 / Livewire) | API / Backend (the named actions) | Stock Filament; every write calls an action |
| Customer card lines | API / Backend (`CustomerCard`) | — | Markdown built on the server (D-38) |
| Sync checks (link line, D-36, D-37) | API / Backend (`FolderSync` / `RoomChecks` / room file type) | Database (D-37 reads appointments) | Validation happens at sync; D-37 is the first check that needs the DB |
| Static assets for the page | CDN / Static (Vite build output) | — | Needs `npm run build` in CI (Pitfall 2) |

## Standard Stack

### Core (all already installed; nothing to add)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.35.0 | Scheduler, cache locks, RateLimiter, migrations, Eloquent | The app's framework [VERIFIED: `composer show --direct`] |
| livewire/livewire | v4.4.7 | The booking page (full-page component via `Route::livewire`) | Installed through Filament; DEC-08 names Livewire for customer pages [VERIFIED: `composer show livewire/livewire`] |
| filament/filament | 5.10.1 | Schedule page, relation manager, dialogs, create page | DEC-08; same stack as Phase 7 [VERIFIED: `composer show --direct`] |
| nesbot/carbon | ^3.8.4 (Carbon 3) | Business-zone wall-clock math | Laravel's date library [VERIFIED: `composer show -t`] |
| tailwindcss + @tailwindcss/vite | 4.3.3 | Utility classes for the customer page | Already in `package.json`; UI-SPEC U-01 [VERIFIED: node_modules/tailwindcss/package.json] |
| pestphp/pest + pest-plugin-browser | 5.3.1 / 5.1.2 | Feature and browser tests; axe is copied onto every browser page | Installed; Phase 4/7 precedent [VERIFIED: `composer show --direct`] |
| playwright | 1.63.0 | Browser tests (locator waits only) | `package.json` [VERIFIED] |
| giggsey/libphonenumber-for-php-lite | 9.0.41 | Phone normalisation for Add contact (via `App\Support\PhoneNumber::toE164`) | Already used for inbound [VERIFIED] |

### Alternatives Considered (each would need a package; none recommended, nothing to approve)

| Instead of | Could Use | Tradeoff / no-package choice |
|------------|-----------|------------------------------|
| Hand-written `Availability` | a recurrence/slot package (e.g. spatie opening-hours) | Would duplicate `BusinessClock`/`hours.md` rules and fight DST handling. Use `BusinessClock` plus a small `Availability` class. |
| Calendar grid | a calendar package | Explicitly deferred to Phase 11 (CONTEXT Deferred). Use the stock Filament grouped table. |
| Postgres exclusion constraint (`btree_gist`) | raw SQL, Postgres only | Strongest guarantee, but not portable to SQLite and bakes in capacity 1 (D-07 wants a count check). Not needed: `Cache::lock` + transaction (Pattern 4). |
| Laravel Notifications for the customer/owner texts | built-in `Notification` | The platform's text path is `OutboundSender`/`SendOwnerAlert` by design (06 D-09). "Notifications" in the roadmap note means the owner-alert pipeline, not Laravel's `Notification` class. Use the existing pipeline. |
| Signed URLs for the link | `URL::temporarySignedRoute` | D-12 chose a stored hashed token (revocable, supersedable, single-use, provenance). Signed URLs cannot be revoked. |
| Icons | `blade-heroicons` | UI-SPEC U-18: three inline SVGs in Blade partials. |

**Installation:** none. `composer.json` and `package.json` do not change. **Flag for Ian: no new package open item.** [VERIFIED: composer show --direct and package.json read this session]

**Version verification:** versions above were read from `composer show --direct`, `composer show livewire/livewire` (released 2026-09-28), and `node_modules/tailwindcss/package.json` on 2026-11-02.

## Package Legitimacy Audit

No external package is installed or recommended by this phase, so the audit has nothing to check. The `gsd-tools package-legitimacy check` was not run because there is no package name to submit.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none) | — | — | — | — | — | — |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
 TEXT THREAD (existing)                                     OWNER (Filament, existing panel)
 customer texts ──► ProcessInbound ──► RunFrontDesk              │  Book by hand / Cancel / Mark done / Edit note
                      │  reply labeled `scheduling`,             │  Block time / Remove block / Automatic texts switch
                      │  verdict = Send, room live,              ▼
                      │  service resolves (D-02), book_online   Named actions (D-15): BookAppointment, CancelAppointment,
                      ▼                                         MarkAppointmentDone, EditVisitNote, BlockTime, RemoveBlock
              mint BookingLink (hash stored)  ──URL──► text sent via OutboundSender (combined text: model words + link line)
                      │ message_id back-filled on the link row
                      ▼
 CUSTOMER taps link ──► GET /book/{token} ──► BookingPage (Livewire, #[Locked] token)
                           │ resolve token → state (picker | booked | expired | replaced | revoked/unknown | no times | service gone)
                           │ Availability::forService(service, now) ◄── ActiveConfig (hours, profile tz, service, booking file)
                           │                                          ◄── BusinessClock (windows, closures, DST)
                           │                                          ◄── appointments + blocks (span overlap, buffer, capacity)
                           ▼
              tap a time ─► RateLimiter (inside component) ─► Check step (picker stays in DOM, hidden)
              "Book this time" ─► BookAppointment (page) ─► Cache::lock + DB::transaction
                           │   re-check availability inside the lock ── taken ──► fresh times + error line
                           │   resolve project (D-23) / OpenProject (actor: customer booking)
                           │   insert appointment, link row consumed, timeline entry
                           │   insert send rows: confirmation (lead 0) + one per reminder lead, due_at precomputed
                           ▼ after commit
              RaiseOwnerAlert(appointment_booked, subject = link's message) ──► SendOwnerAlert (existing claim)  [after hours: due_at = next opening]
              SendBookingText(confirmation row) ──► OutboundSender (new kind, Automated)
                           ▲
 SCHEDULER every minute ──► SendDueBookingTexts (asks hasRoom first) ──► claim row (2-minute lease) ──► re-check:
                           booked, ahead, in send window, no unread customer text, not opted out, switch/texted (D-21), not paused/closed
                           ──► OutboundSender::send(kind booking text, meta.appointment_id, parts without model_words)
                           └─ refusal/drop ─► one timeline note (D-27), no owner alert

 INBOUND text from a contact with a booked appointment ─► RunFrontDesk: reading intent `scheduling` OR reply label `scheduling`
        ─► RaiseOwnerAlert(change_request, subject = that inbound text) ranked above check_in / draft_waiting (D-34)
```

### Recommended Project Structure

```
app/Rooms/ScheduleAndBooking/
├── ScheduleAndBookingServiceProvider.php     # extends RoomServiceProvider; room(): key schedule_and_booking, fileTypes, sections, tables, timelineEntries?
├── BookingFileType.php                        # implements RoomFileType, type 'booking' (name is the planner's); rules() + checks()
├── ScheduleSection.php                        # extends RoomSection, view like LeadsSection
├── Availability.php                           # offered start times for a service (pure, no writes)
├── BookingSettings.php                        # typed reader of the booking file via ActiveConfig::filesOfType()
└── (actions live in app/Actions/ if they cross rooms; see Pattern 9)
app/Actions/
├── BookAppointment.php, CancelAppointment.php, MarkAppointmentDone.php, EditVisitNote.php, BlockTime.php, RemoveBlock.php
├── MintBookingLink.php, SendBookingText.php, SetAutomaticTexts.php, RenderBookingText.php
app/Jobs/SendDueBookingTexts.php               # scheduler entry in routes/console.php; first line asks hasRoom()
app/Models/Appointment.php, ScheduleBlock.php, BookingLink.php, BookingSend.php   # + factories
app/Livewire/BookingPage.php
app/Filament/Pages/SchedulePage.php            # stock Page + table, grouped by day
app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php
resources/views/layouts/customer.blade.php, livewire/booking-page.blade.php, components/booking/*, filament/rooms/schedule-section.blade.php
database/migrations/2026_11_0x_*  (one concern per file, house style)
examples/example-hvac/rooms/schedule_and_booking/{CONTEXT.md,booking.md}
```

Where the actions live is the planner's call. The only hard constraint is the architecture test (Pitfall 10): whichever directory, the test names it.

### Pattern 1: A room that defines one file type (copy Money)

**What:** `MoneyServiceProvider` + `MoneyNotesType` (owns `rooms/money/notes.md`, `singleFileLabel()`, `singleFileDefaultPath()`), the room's CONTEXT.md front matter (`type: room`, `owns`, `reads`, `writes_records`, `connector`), a boot test in the `MoneyBootTest` shape (empty case and example case, contract compared against SPEC rows through `Tests\Rooms\SpecTables::patternsOwnedBy`), and an axe check on the section through `RoomSectionAxe`.
**Specifics for this room (D-35):**
- `Room(key: 'schedule_and_booking', label: 'schedule and booking', fileTypes: [new BookingFileType], sections: [ScheduleSection::class], tables: ['appointments','schedule_blocks','booking_links','booking_sends'] /* names are the planner's */, timelineEntries: [...])`. `connector: none`, no slot, `neverLeftOutBecause: null`. [CITED: app/Rooms/Room.php:19-37 constructor docblock]
- The register in `bootstrap/providers.php` is added after `LeadsServiceProvider::class`. `AppServiceProvider` must stay first (RoomServiceProvider docblock).
- `register()` and `boot()` read nothing (no `ActiveConfig`, DB, or Filament) — the `RoomServiceProvider` rule. Any scheduled task the room adds must call `ActiveConfig::hasRoom('schedule_and_booking')` when it runs and do nothing while the room is off. [VERIFIED: app/Rooms/RoomServiceProvider.php docblock, read this session]
- Update `docs/SPEC.md`: the "Who owns each file" table gets a row `rooms/schedule_and_booking/booking.md` (owner: schedule and booking), and the sentence at SPEC.md:237 that says "Schedule and booking ... own none of today's files (D-12)" must drop schedule and booking from that list. `RoomContractTest`/`SpecTablesTest` compare these. Also update the "Readers named so far" cells only if they change.
- `exampleFolderFileCount()` returns 26 (`tests/Pest.php`); the example gains two typed files (`rooms/schedule_and_booking/CONTEXT.md`, the booking file), so it becomes 28. Every `*BootTest` uses `exampleFolderFileCount() - n`, so only that one number moves. [VERIFIED: tests/Pest.php `exampleFolderFileCount()` returns 26]
- A room whose folder is absent when appointments are booked ahead is refused by D-37 (Pattern 10).

### Pattern 2: The link token (D-12, D-13)

**What:** `Str::random(40)` (or `bin2hex(random_bytes(32))`, 256 bits) is the URL secret. Store only `hash('sha256', $token)` in a `unique` indexed `token_hash` column. Look up by hash. Because the secret is high entropy, a fast hash is correct and an indexed lookup is possible.
**Why not `Hash::make`:** Laravel's password-reset repository stores `hasher->make($token)` (bcrypt) but finds the row by *email* first and then calls `check`. Our URL has no key to find the row by, and bcrypt is salted so it cannot be looked up. [VERIFIED: vendor/laravel/framework/src/Illuminate/Auth/Passwords/DatabaseTokenRepository.php lines 72, 90, 166] Hashing with sha256 matches what a stolen DB copy needs (no working link) without a second lookup key. Compare with `hash_equals` only if you also keep a plaintext prefix; lookup by exact hash needs no extra compare.
**Row columns (names are the planner's):** `contact_id`, `conversation_id`, `service_slug`, `message_id` (nullable at mint, back-filled after the send, see Pattern 5), `token_hash`, `expires_at`, `superseded_at`, `revoked_at`, `appointment_id` (null until booked; presence = "books at most once"), timestamps.
**State resolution order (page):** unknown hash or `revoked_at` → "does not work" (same page, U-13); `superseded_at` or a newer link exists for the conversation → "replaced"; has `appointment_id` → booked/cancelled/done view per U-14's expiry limit; `expires_at` past → "expired"; room off / service gone / `book_online` false → "cannot book online" (D-02); no times → "no times open"; else picker.
**Superseding:** minting a link sets `superseded_at` on that conversation's older unbooked links in the same transaction.

### Pattern 3: Availability on the business wall clock (D-06 to D-11, BOOK-01)

**What:** One class, `Availability`, returns a list of `CarbonImmutable` start instants for a service, grouped by business-zone day by the caller. It reads hours, closures, lead minutes through `ActiveConfig::hours()`/`BusinessClock`, the service through `ActiveConfig::service($slug)`, the zone through `ActiveConfig::timezone()`, and the booking settings through `ActiveConfig::filesOfType(...)`. It never calls `now()` directly except through an injected `$now` so tests can pin it.
**Algorithm per day D from today through `days_ahead`:**
1. `window = BusinessClock window for D` or skip (closure, closed weekday). `BusinessClock::windowOn()` is `private` (BusinessClock.php:~150). **Make it public (or add a public `openWindowOn(CarbonInterface $day)`)** rather than re-implementing closures; this is an additive change and the existing `tests/Feature/Icm/BusinessClockTest.php` should keep passing. [VERIFIED: app/Icm/BusinessClock.php read this session]
2. `earliest = (isOpenAt(now) ? now : nextOpening(now)) + booking_lead_minutes` (D-10). `nextOpening` looks 13 days ahead only (`DAYS_AHEAD = 13`), which is fine here: it is used only for "now is closed", so the next opening is the next business day.
3. Candidate starts: from `window.open`, stepping by the booking file's `step_minutes` of **elapsed** time (`addMinutes` on a `CarbonImmutable` carrying the zone is elapsed time), keep a start when `start >= earliest`, `start > now`, and `start + duration_minutes <= window.close` (D-07, "a job must end by closing").
4. Drop a start when overlap count `>= capacity` (capacity constant 1, written as a count check): booked appointments and blocks whose span overlaps `[start, start+duration)`, with the drive buffer added to the end of **appointment** spans and to the new span's end (D-08). Overlap in portable SQL with no date math: `existing.starts_at < :newEnd + buffer AND existing.ends_at > :newStart - buffer`, with both bounds computed in PHP and bound as UTC timestamps (works on SQLite and Postgres; no `interval` syntax).
**Verified DST facts (run this session, `America/Chicago`, Carbon 3):**
- `2026-11-01 01:30` (fall back) resolves to the **first** occurrence, `2026-11-01T01:30:00-05:00`.
- `2026-03-08 02:30` (spring forward, a time that does not exist) resolves to `2026-03-08T03:30:00-05:00` (shifted forward).
- `setTimeFromTimeString('08:00')` on the fall-back day gives `08:00-06:00`; open-to-close `08:00`–`17:00` is 540 minutes on that day (the day is 25 h long but business hours after 03:00 are unaffected).
- `addMinutes(120)` from `2026-11-01 00:00 CDT` lands on `01:00 CST`, i.e. elapsed, not wall-clock, arithmetic. So a business that opens before 03:00 and steps through the repeated hour needs elapsed stepping; wall-clock stepping would double-offer or skip. [VERIFIED: php -r run on PHP 8.5.12 with nesbot/carbon 3, 2026-11-02]
- The 2026 transitions to test are **2026-03-08 (spring forward)** and **2026-11-01 (fall back)**; the example's closures are 2026-11-26 and 2026-12-25 (examples/example-hvac/business/hours.md). The example hours (08:00–17:00 Mon–Fri, 09:00–13:00 Sat, Sunday closed) never touch 02:00, so DST tests need a fixture hours file that opens at 00:00 to prove the elapsed-time and nonexistent-time behavior. [VERIFIED: hours.md read this session]
**Store appointments as UTC timestamps** (`timestamp` columns; app timezone is `UTC`, config/app.php:68), render in the business zone from `ActiveConfig::timezone()`. `displayTimezone()` is the safe reader that never throws before the first sync. [VERIFIED: config/app.php, ActiveConfig.php:48-62]

### Pattern 4: Booking under a lock, "never two" on SQLite and Postgres (D-07)

**Recommendation (Claude's discretion area):** `Cache::lock('schedule:book', 10)->block(5)` around a `DB::transaction` that (a) re-runs the same overlap/capacity check the picker used and (b) inserts. This is the house precedent: `OutboundSender` uses `Cache::lock("reply:{$conversation->id}", 30)` (OutboundSender.php:89) and `FolderSync` uses `Cache::lock('icm:sync', 120)`. The production cache store is the `database` store (`.env.example` `CACHE_STORE=database`), which supports atomic locks across workers; the test store is `array`, which supports locks within one process. The lock is one global lock because the example business is one van; it is released in a `finally`.
**Why not the alternatives:**
- `lockForUpdate()` alone: Laravel's SQLite grammar compiles it to `''` (no-op) [VERIFIED: vendor/.../SQLiteGrammar.php `compileLock` returns `''`], so it would be untested on the dev/CI-sqlite leg and only real on Postgres.
- A Postgres exclusion constraint: Postgres-only and encodes capacity 1.
- A unique index on `(start)`: does not catch partially overlapping spans or the buffer.
**Testing the race honestly:** Pest runs one PHP process, so true concurrency cannot be proven in-process. Prove (1) two sequential `BookAppointment` calls for overlapping spans: the second is refused; (2) hold the lock in the test (`Cache::lock('schedule:book', 5)->get()`) and assert the action refuses or waits and does not insert; (3) an architecture-style assertion that the action takes the lock before reading. State in the plan that the cross-process race is covered by the lock design and not by a concurrent test.

### Pattern 5: Appending the link at the send step (D-01, D-02)

**What:** In `RunFrontDesk::sendPassingReply()` (RunFrontDesk.php:455-480 region): when the verdict is Send, the reply's label is `ReplyLabel::Scheduling`, the room is live, the booking file exists, the service resolves (D-02: the reply's `service_slug`, else the conversation's single intake service from `intake_answers.service_slug`) with `book_online: true`, and the contact has no upcoming booked appointment, then mint a link and add a `bookingLine` to the `ReplyParts` that goes to `SendCombinedReply`.
**Why at the send step and not earlier:** the many earlier branches treat `ReplyParts::codeSentences` as "owner price/offer sentences" (held into draft pre-fill, `$answersWaiting && $sentences !== []`, partial-holding). Putting the link into `codeSentences` would drag it through those branches and into held drafts with a minted token nobody sent. Adding a separate `bookingLine` property on `ReplyParts` (`app/SendRule/ReplyParts.php`; `partNames()` gets a `booking_link` name; `body()` appends it after the model words) keeps all of that untouched. While a draft waits, `labelGuardFor()` limits sends to faq/intake labels, so a scheduling reply never sends then and never gets a link.
**Failure handling:** mint → send → on success back-fill `message_id` on the link row (the booked alert's subject, D-33); on `DomainException` from the sender, revoke the minted link. If the back-fill fails, report it; do not raise the booked alert with a null subject (its key would be `m0` and a second booking in the conversation would be dropped, violating 02.1 IN-11).
**Reading rule:** add a `'booking_link'` arm to `OutboundSender::whyReadingBlocks()` that behaves like `code_sentences` (`$reading->allowsOwnerSentences()`), so a complaint reading never lets the link out (Pitfall 9).
**Draft path:** a `scheduling` reply held as a draft and later approved by the owner goes out through the owner's reply path with no link (D-01 says "a reply the agent labels scheduling" that code appends to; an owner-approved draft is the owner's text). Record this as the accepted behavior.

### Pattern 6: The Livewire customer page (UI-SPEC)

**What:** `app/Livewire/BookingPage.php`, full-page via `Route::livewire('/book/{token}', BookingPage::class)->name('booking.show')`, `#[Layout('layouts.customer')]`, `#[Title(...)]` set per state. [VERIFIED: `Route::livewire` and `#[Title]` used in routes/web.php and app/Livewire/DevChat.php]
- **Lock the token.** `#[Locked] public string $token`. Never trust any client-sent slot: the tap handler receives an ISO instant string, and the server accepts it only if it is in the list `Availability` just produced.
- **Keep the picker in the DOM, hidden, while the check step shows** (Pitfall 6): `hidden` attribute (or `x-show`) on the picker wrapper; this preserves native `<details open>` state and lets "Choose a different time" return focus to the tapped button without server bookkeeping. When a fresh list is needed (a taken time), give the wrapper a `wire:key` that includes a version counter so it is re-created and the earliest day opens.
- **Focus moves** by `x-init="$el.focus()"` on the element that appears (h2 "Check your time", h1 "You are booked", the error line), each with `tabindex="-1"`; a Livewire `dispatch` + Alpine listener is the alternative. Alpine ships with Livewire 4 (no extra JS). The UI-SPEC says the behavior is the contract.
- **Busy state:** `wire:loading` is not enough for U-20 (ignore taps, keep focus). Use `aria-disabled="true"` plus a server-side `bool $booking` guard; return early if already set.
- **Rate limit inside the component** (Pitfall 1): `RateLimiter::attempt("booking:{$tokenHash}:{$ip}", 20, fn () => ..., 60)` in the tap and book methods, plus a named limiter `booking` registered next to `leads` in `AppServiceProvider.php:92` for the page GET. Pick numbers at plan time (D-11).
- **Layout:** new `layouts/customer.blade.php` emits `@fonts` and, **only when `public/build/manifest.json` or `public/hot` exists**, `@vite([...])` — the exact guard `welcome.blade.php` uses (`@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))`). Without the guard every feature test that renders the page fails in CI where nothing is built. [VERIFIED: resources/views/welcome.blade.php lines 10-14]
- **Headers:** UI-SPEC specifies `<meta name="robots">` and `<meta name="referrer">`. Also send `X-Robots-Tag: noindex` and `Referrer-Policy: no-referrer` as response headers from a small middleware on the route; meta is advisory and a header also covers the Livewire update responses. (Recommendation; UI-SPEC does not forbid it.)
- **Time display:** format in the business zone from `ActiveConfig::timezone()` only; `Carbon::format('T')` gives only an abbreviation that changes with daylight time ("CST"/"CDT"), so for the generic long name ("Central Time", U-11) use `IntlDateFormatter` with pattern `VVVV` (generic long name; see A5) — `intl` is loaded locally and in CI (`extensions: mbstring, intl, ...`). Fall back to the IANA id (U-11). [VERIFIED: php -m shows intl; tests.yml lists intl] The exact `IntlDateFormatter` output for `America/Chicago` should be confirmed in the first task's test, not assumed.

### Pattern 7: Confirmation and reminders table, claim, sweep (D-26 to D-31)

**What:** a `booking_sends` table, one row per appointment and lead time (confirmation = lead 0), `unique(appointment_id, lead_minutes)`, columns `due_at`, `send_claimed_at`, `sent_at`, `skipped_at`, `failed_at`, `failure`, `message_id`. The claim is the same conditional update `SendOwnerAlert::claimToSend()` uses (whereNull outcome columns, `send_claimed_at` null or older than `SendOwnerAlert::CLAIM_WAIT_MINUTES` = 2) and the sweep is a copy of `SendDueAlerts`' query. Reuse the constant; copy the pattern rather than refactoring `SendOwnerAlert` (shared, hot, out of scope). [VERIFIED: SendOwnerAlert.php:32, 155-183; SendDueAlerts.php:37-58]
**Compute `due_at` once, when the rows are created** (inside the booking transaction): `due = start - lead`; if outside the send window, move to the end of the window on the preceding window (D-29): a due time before the window opens goes to the **previous day's** window end; after it closes goes to **that day's** window end. Do the day arithmetic on the business wall clock (`subDay()` on a zoned `CarbonImmutable` then `setTimeFromTimeString`), not by subtracting 24 elapsed hours. If the moved time is already past at booking, create no row (skipped silently, D-29). If two leads collapse to the same moved `due_at`, keep one row (suppress the duplicate at creation).
**The send step re-checks everything on each attempt** (D-31): still `booked` and `starts_at > now`; `now` inside the send window (both ends inclusive); no customer message with `handled_at` null in the conversation (D-26); `OutboundSender::whyBlocked()` clean. A wait leaves the row unclaimed to be retried; the wait ends (row `skipped_at` with a reason, no text) when the appointment is no longer booked or no longer ahead. A refusal that is not a wait (opted out, switch off, closed, paused) writes one timeline note naming the text and the reason and no owner alert (D-27).
**Conversation (D-30):** send in the contact's open conversation; with none open, create one through the same path an inbound uses for a new conversation (`RecordInbound::openConversationFor()` at RecordInbound.php:369 is private; copy its `UniqueConstraintViolationException`-in-savepoint pattern, or expose a small shared action). The partial unique index `conversations_one_open_per_contact` (database/migrations/2026_10_12_000008...php:47) guarantees one open conversation. Do **not** create a conversation for a paused conversation's contact (that conversation is open and paused: refuse, write the note).
**Scheduler:** `Schedule::job(new SendDueBookingTexts)->everyMinute()->withoutOverlapping();` in `routes/console.php`, with a docblock comment in the file's existing style. The job's first statement is `if (! app(ActiveConfig::class)->hasRoom('schedule_and_booking')) { return; }`. [VERIFIED: routes/console.php existing entries read this session]
**Also send right after commit** (D-28): dispatch/send the confirmation row after the booking transaction commits via `DB::afterCommit(...)`, with the sweep as the backstop, the way alerts are raised. The sweep and the immediate send race on the same claim, which makes them safe.

### Pattern 8: Two new alert reasons (D-33, D-34)

**What:** add two cases to `App\Enums\AlertReason` (names are the planner's; e.g. `AppointmentBooked = 'appointment_booked'`, `ChangeRequest = 'change_request'`) and arms to `plainWords()`. `AlertReason::values()` feeds the Validator's `owner_alert.right_away.*` `Rule::in`, so the new values become legal in a folder automatically. [VERIFIED: AlertReason.php:10-20, 27-30 and Validator.php `owner_alert` block read this session]
- **Booked:** `RaiseOwnerAlert::handle($conversation, AlertReason::AppointmentBooked, $linkMessage, $why)` after the booking commits, page bookings only. The dedupe key is unchanged: `'c'.$conversationId.':m'.($messageId ?? 0).':'.$reason->value.':u'.($userId ?? 0)` (OwnerAlert.php:65-68). Not listed under `right_away`, so `dueAt()` (RaiseOwnerAlert.php:159-174) sends now while open and at the next opening after hours. **`isUrgent()` returns true when the live folder's `owner_alert` cannot be read** (RaiseOwnerAlert.php:144-146); that is existing behavior, leave it.
- **Change request:** `AlertReason::ChangeRequest`, listed under `right_away` in `examples/example-hvac/rules/sending.md`. Subject = the inbound text. The alert names the booked visit (service, day, time) in the `why` string.
- **Body shape (Pitfall 8):** `OwnerAlertText::build()` prints `They wrote: "<lastCustomerText>"` unconditionally and a `Draft:` line for draft subjects. For the booked alert pass a `why` that carries the visit and let the builder's quote line be suppressed for that reason (a small `match` on the reason inside `build()`), or the owner reads a stale customer quote under "Booked". Add a test with an old inbound text.
- **The one-alert order (D-34) lives in `RunFrontDesk`**; see Pitfall 9 below and "Existing tests to sweep".

### Pattern 9: Appointment actions and the architecture test (D-15)

**What:** six plain action classes with one public `handle()` each, a transaction, a row lock where needed (as `CloseProject` does with `lockForUpdate()->find`), one timeline entry written through `$contact->activities()->create([...])` or `RecordRoomEntry` (room entries are written only while the room is live, D-08 of Phase 4; use it for the room's entries so a removed room goes quiet), `CustomerRecordChanged::dispatch($contact->id)` after commit, and recorded actor. Blocks write no timeline entry (UI-SPEC decided gap 2).
**Architecture test:** copy `tests/Unit/ProjectWritesTest.php`'s shape for `Appointment` and `ScheduleBlock`: a source scan of `app/` listing the files allowed to write those tables, positive snippets proving each rule fires, negative snippets proving reads pass. That test demonstrates the pitfall of a vacuous scan; keep its "flags every kind of write" companion.
**Timeline entries:** use `TimelineEntryType` keys registered on the Room (`booked`, `cancelled`, `done`, `visit_note`, `text_sent`, `text_not_sent` — names are the planner's) with `trusted: true` for platform-worded lines. A trusted entry prints an intro such as "System note:"; the visit note itself is owner-typed and must be an *untrusted* quote line (a separate entry or the card's quote path), never inside a trusted summary. [CITED: app/Rooms/TimelineEntryType.php docblock]

### Pattern 10: The two new sync refusals (D-36, D-37)

**D-36 (pure, no DB):** add to `ConnectorChecks::folderErrors()` or `RoomChecks::errors()`: if the room is present (`RoomChecks::isPresent`) and any `type: connector` file's `owns` contains `schedule` (`OwnableKind::Schedule = 'schedule'`), emit one error naming both files, in the Validator error shape `{file, field, message}`. A connector that only *reads* schedule is fine (`reads`). Existing helpers: `ConnectorChecks::kinds($file,'owns')`. [VERIFIED: OwnableKind.php, ConnectorChecks.php:55-140 read this session]
**D-37 (needs the DB — the first such check):** the Validator and the room checks are pure functions of the loaded files. A check that reads appointments belongs in `FolderSync::sync()` just after `$report = $this->validator->validate($folder)` and before the `reject()` decision, or in a dedicated `RoomDropChecks` called by `FolderSync` and merged into the report. Inputs: the live version's `compiled['index']['room']` (is the room live now?) versus the candidate folder (is it absent?), plus a query for booked appointments with `starts_at > now()`. Output: an error in the same shape naming the count and the earliest, plus how to stop new bookings (`book_online: false`). It must not run when there is no live version. Keep the Validator untouched; tests: dropping the room with a future booked appointment is refused and the previous version stays live; past/cancelled/done appointments never block; turning the room off and on without appointments passes.
**Note on `queueCardsForRoomChanges`:** a sync that changes the room set already queues card redraws for contacts holding entries of that room (FolderSync.php). The room's timeline entries therefore hide/show with the room automatically. [VERIFIED: FolderSync.php read this session]

### Pattern 11: The owner surfaces (Filament 5.10.1)

- **Schedule page:** a `Filament\Pages\Page` using `InteractsWithTable`/`HasTable`; `->defaultGroup(Group::make(...))` and `->groupingSettingsHidden()` so the day headings cannot be turned off; `groupingDirectionSettingHidden()` too. [VERIFIED: vendor/filament/tables/src/Table/Concerns/CanGroupRecords.php:67-106 — `groupingSettingsHidden`, `groupingDirectionSettingHidden`, `defaultGroup`, `groupsOnly` exist]. Group by a **business-zone date**, not the stored UTC date: `Group::make('day')->getKeyFromRecordUsing(fn ($record) => $record->starts_at->setTimezone($tz)->toDateString())` and a title closure; a plain `groupBy` column would group on UTC (an 8 PM CST appointment would land on the next UTC day). Sorting must be by `starts_at`. Blocks and appointments are two tables; the schedule shows both, so use one query (a `union`, a SQL view, or one table with `kind`). Decide in the table-design plan; a single `appointments` table with a nullable contact and a `kind` of `appointment|block` makes the grouped list, the overlap query and the "capacity" count trivial, at the cost of the appointment-only architecture test also governing blocks. **Recommendation: one table.** (CONTEXT lists "appointments, blocks" as separate tables in the Room's `tables`, but names the table layout as discretion; see Open Question 3.)
- **Dialog date-times (U-32):** `DateTimePicker::make('starts_at')->native()->timezone($config->timezone())`. Verified mechanics: the picker's state cast (`Filament\Schemas\Components\StateCasts\DateTimeStateCast`) interprets the typed wall time in the given zone and returns the value in `config('app.timezone')` (UTC) in the format; so `Carbon::parse($data['starts_at'], config('app.timezone'))` is the correct instant. A wall time in the spring-forward gap is moved forward (verified above) and a repeated hour resolves to the first occurrence; owner-entry tests must cover both 2026 days. [VERIFIED: vendor/filament/schemas/src/Components/StateCasts/DateTimeStateCast.php:30-95, forms DateTimePicker.php:350-362, 507-511]
- **Close dialog with no confirm button (U-31):** `Action::modalSubmitAction(false)` is supported (`modalSubmitAction(Action | bool | Closure | null $action = null)`), and it accepts a closure, so it can be decided per record when the dialog opens. [VERIFIED: vendor/filament/actions/src/Concerns/CanOpenModal.php:261] The refusal is enforced in `CloseProject` too (D-25), so the dialog is courtesy, the action is the guard.
- **Row action focus (WCAG 2.4.3):** `ProjectsRelationManager` documents UAT gap G-07-2.2: Livewire matches elements by `wire:key`, Filament puts the action name in each row button's key, and two differently named actions in one row meant the morph replaced the focused button and focus fell to the page; the fix was one action with a changing label. The appointments tab and schedule rows have *three* actions that appear and disappear (Mark done hidden until the start passes, Cancel visit, Edit note). After "Cancel visit" the other buttons vanish, so focus will be lost. Plan an explicit focus test and a deliberate focus target (the row's remaining button, or the table heading) instead of assuming the Phase 7 fix generalizes. [CITED: app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php comment block]
- **`$isLazy = false`** on the new relation manager (G-07-2.1), `isReadOnly()` returns false (research Pitfall 10 in that class). [CITED: same file]
- **Add contact (D-19):** flip `ContactResource::canCreate()` (ContactResource.php:30, today `return false` with the doc comment "Contacts are created by customers texting or filling in the form, never by hand.") and add `'create' => CreateContact::route('/create')`. Matching "phone first, then email" is private inside `RecordInbound::matchOrCreateContact()` (RecordInbound.php:256-300). The create form's refusals (UI-SPEC U-27) should call a small shared lookup (phone via `App\Support\PhoneNumber::toE164` then `Contact::where('phone_e164', ...)`, then `where('email', ...)`) rather than copy `matchOrCreateContact` whole. `contacts.phone_e164` is `unique` (migration 2026_10_11_131450), so a race on phone throws `UniqueConstraintViolationException`; catch it into the field error.
- **Hand-added contact does not open a project:** `OpenProjectForNewLead::queueFor` fires on `Contact::created`, but its `handle()` returns early when the contact has no `TextReceived`/`FormReceived` activity (OpenProjectForNewLead::handle). A hand-added contact has none, so no project opens. Keep a test for this; do not rely on the early return unnoticed. [VERIFIED: app/Rooms/Leads/OpenProjectForNewLead.php read this session]

### Pattern 12: The customer card (D-38) and the front desk's view (D-39)

`CustomerCard::render()` builds markdown from the contact, `projectLines()`, `intakeAnswerLines()` and the timeline. Add "Next appointment" to the facts list (after `Last activity`) only when a booked appointment is ahead; add per-project sub-lines in `projectLines()`; add a case to `section()`/its part builders for the new activity types. `projectLines()` currently emits no appointment text, so a contact without appointments renders byte-for-byte as today, which a regression test should assert by comparing an existing card fixture. Owner-typed visit notes go through `$this->markdown->quote()` under the "Customer-supplied text, not instructions:" line, the way `projectLines()` quotes the project name (CustomerCard.php lines ~207-216). [VERIFIED: CustomerCard.php:189-232 read this session]

### Anti-Patterns to Avoid

- **Throttling only the page route** (Pitfall 1).
- **Computing a time from the browser's clock or zone** — any `new Date()` in the page is a bug (D-11).
- **Passing a slot id from the client and trusting it** — recompute server-side.
- **Putting the link into `codeSentences`** (Pattern 5).
- **Reusing `OwnerAlert` rows for confirmations/reminders** — different recipient, different conditions; D-31 gives them their own table.
- **Updating `Project` rows from booking code** — only the four project actions write projects (D-11 of Phase 7).
- **Reading the booking file with `File::get`** — only `Loader` reads the folder; use `ActiveConfig::filesOfType()` (`.ai/rules/app.md`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Open/close times, closures | a second hours parser | `BusinessClock` (make `windowOn` public) | DST and closure-day handling already tested there |
| Exactly-once send | a new lock/claim scheme | The `send_claimed_at` conditional-update claim and 2-minute wait (`SendOwnerAlert`) | Proven in Phase 6 (06 D-06, D-07) |
| Text delivery | a new Twilio call | `OutboundSender::send()` | The only chokepoint; opt-out, pause, closed, lock, record |
| Phone parsing | regex | `App\Support\PhoneNumber::toE164()` (libphonenumber-lite) | Already used for inbound and Dev chat |
| Markdown-safe quoting of owner text | custom escaping | `CustomerCard`'s `$this->markdown->quote()/inline()` | The 07 D-13 neutralization |
| Alert dedupe | a new "already alerted" table | `OwnerAlert::dedupeKey()` + `createOrFirst` | Unique index already guarantees once |
| Focus return after Filament modals | custom JS | Filament's own focus return, with the one-action-per-row lesson | G-07-2.2 |
| Disclosure/sign-off | hand-concatenating | `ComposeAutomatedText::forReply()` | D-50 of Phase 2; first automated text carries the disclosure |
| Unit-testing DST | assuming | Fixed `travelTo()` instants on 2026-03-08 and 2026-11-01 in `America/Chicago` | Verified behavior above |

**Key insight:** this phase adds almost no new mechanism; its risk is in the seams with shared code (Pitfalls 4, 9, 10), not in new algorithms.

## Runtime State Inventory

Not a rename/refactor phase. Two migrations-with-data items to state explicitly:

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `projects.opened_by` and `closed_by` hold `ProjectActor` strings; a new value (customer booking) is only ever written by new code. `owner_alerts.reason` is a plain string column; new reason strings need no migration. `messages.kind` is a nullable string; no constraint. [VERIFIED: migrations read] | Code edit only; no data migration |
| Live service config | A live client folder lacks `rooms/schedule_and_booking/`; the room is optional (D-35) so existing folders keep passing | None |
| OS-registered state | None — the scheduler runs from `schedule:run`/`schedule:work` (`AppServiceProvider.php` registers `schedule:work` locally) | New `Schedule::job` entry only |
| Secrets/env vars | None | None |
| Build artifacts | `public/build` is gitignored; CI must now run `npm run build` (Pitfall 2) | CI step |
| Queued jobs | "A queued job that gains a required field drains the queue first (BLUEPRINT section 10)" (Ian's note). `RunFrontDesk`/`OutboundSender` change signatures only if a plan adds constructor args to a queued job (`ProcessInbound`); `SendDueBookingTexts` is new | If any plan adds a required property to `ProcessInbound`, add a drain step |

## Common Pitfalls

### Pitfall 1: The page throttle does not protect the taps
**What goes wrong:** `throttle:booking` on `Route::livewire('/book/{token}', ...)` limits `GET /book/{token}` only. Taps and "Book this time" are POSTs to Livewire's update endpoint.
**Why:** Livewire re-applies only "persistent" middleware to update requests. The default list contains Sanctum/Jetstream/auth/`SubstituteBindings`/`Authorize` classes and not `ThrottleRequests`; the filter also compares the middleware string before `:` to a class name, so `throttle:booking` (an alias) would not match even if added. [VERIFIED: vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php:16-25, 196-215]
**How to avoid:** call `RateLimiter::attempt()` (or `hit` + `tooManyAttempts`) in the component methods; key by token hash and IP; render the "Please wait a moment" page on trip (U-19). Also keep a named `booking` limiter on the GET.
**Warning signs:** a test that posts 100 taps and sees no refusal.

### Pitfall 2: CI cannot style the page
**What goes wrong:** `.github/workflows/tests.yml` runs `npm ci`, installs Chromium, then `php artisan test`; there is no `npm run build`, and `public/build` is gitignored. A layout that calls `@vite` unguarded throws `ViteManifestNotFoundException` in feature tests; a guarded layout renders unstyled in browser tests, so the 48 px tap-target and contrast/axe checks measure the wrong page. [VERIFIED: tests.yml read this session; .gitignore `/public/build`; vendor Vite.php:966-974]
**How to avoid:** (a) guard `@vite` as `welcome.blade.php` does; (b) add `- name: Build the front end` / `run: npm run build` after `npm ci` in `tests.yml` (Ian pushes); (c) note `@fonts` needs network at build to fetch Instrument Sans from Bunny (vite.config.js `bunny('Instrument Sans', ...)`), which GitHub runners allow. Do the CI edit in an early wave so the first browser test is meaningful. The build is also required locally (`public/build` exists on Ian's machine already).
**Warning signs:** axe passes locally but a tap-target test fails only in CI.

### Pitfall 3: Wall-clock versus elapsed time
**What goes wrong:** adding minutes across a DST change, or building "start + 60 minutes" in UTC, double-offers or skips the repeated hour; "yesterday at the window end" computed as `-24 hours` is wrong on change days.
**How to avoid:** step with elapsed `addMinutes` from the open instant inside the window; compute "previous day's window end" by `subDay()` on the zoned date and setting the time string; store UTC. Tests on 2026-03-08 and 2026-11-01 with an hours fixture that opens before 03:00, plus a reminder due across each. [VERIFIED: runs above]

### Pitfall 4: A third `ProjectActor` mislabels in four places
**Where (all read this session):** `app/Records/CustomerCard.php:200` (`=== ProjectActor::Lead ? 'by a new lead' : 'by the owner'`), `app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php:79` (Lead → 'New lead' else user name or 'A removed user'), `app/Actions/OpenProject.php:101` (`=== ProjectActor::User ? 'Opened by the owner' : 'Opened by a new lead'`), `app/Rooms/Leads/LeadsSection.php:110` (Lead → source name else `'opened by '.($project->openedByUser?->name ?? 'a removed user')`). Also `projects_one_lead_project_per_contact` is a partial unique index `where opened_by = 'lead'`, which a new value does not touch.
**How to avoid:** replace each ternary with a `match` that has an arm for the new value and no default (so a future value is a compile-time-ish failure); fixed timeline sentence "Opened by the customer on the booking page" (D-22). Tests: `ProjectCardTest`, `ProjectsRelationManagerTest`, `LeadsSectionTest`, `ProjectActionsTest`, `ProjectRecordTest`, `OpenOnNewLeadTest` reference `ProjectActor`; read them for count assertions that would not notice the new branch. [VERIFIED: grep of ProjectActor in app and tests]

### Pitfall 5: Hidden a11y traps specific to Livewire + native disclosures
- A morph re-render removes a user-opened `<details>` state unless the element is kept (Pitfall 6).
- The error line must be focused when it appears, but focusing inside a `wire:loading`/morph can be undone by the next morph; set focus in `x-init` of the element that is *added*, and test with Playwright `getByRole(...).waitFor` then `evaluate(() => document.activeElement...)`.
- UI-SPEC says no `role="alert"` and focus-on-appear for errors; keep both, and add the browser test "after a taken time the error line has focus".

### Pitfall 6: The picker removed from the DOM loses open days
**What goes wrong:** UI-SPEC says the check step *replaces* the picker (not stacks). If the server stops rendering the picker, Livewire removes the nodes and `wire:ignore.self` can no longer preserve anything. "Choose a different time" then re-creates all days closed except the first.
**How to avoid:** keep the picker rendered, `hidden` while the check step shows (visually replaced, assistive-tech replaced via `hidden`), or store open days in a component array updated by `@toggle`. The first costs nothing server-side and makes focus return trivial. Give the picker a `wire:key` with a version that increments on a fresh list.

### Pitfall 7: Client-trusted state in Livewire
`public` properties are client-writable unless `#[Locked]`. Lock `token`, `selected`, `step` (or derive step from server state). Never put the tapped time into a property you later trust without re-validating it against `Availability` inside the lock.

### Pitfall 8: Alert body quotes a stale text
See Pattern 8. Also `OwnerAlertText::clean()` swaps links, emails and long digit runs: a service name containing digits like "10 SEER tune-up" is fine, but one written as "AC 2026" is not turned into `[number]` unless seven digits. No action beyond testing.

### Pitfall 9: Shared-code changes that can silently pass
- **`OutboundSender::partsOf()`** returns `[]` for any kind not named (line 398: `default => [],`), so a new `MessageKind` case (booking text) is treated as "a fixed line which needs only the reading record" — and it would then be *refused* for lacking a reading, or, if given one, sent without the D-26 conditions. The new kind needs its own branch in `deliver()`/`send()` ahead of `refuseWithoutReading()` (OutboundSender.php:203-205): require `meta.appointment_id`, appointment `booked` and `starts_at > now`, `meta.parts` present and without `model_words`, and no customer message with `handled_at` null in the conversation; and it must *not* require `gate.customer_reading` (that is the amendment, D-26). Refusals raise `DomainException` with plain words (existing style) and **no** `safety_check_missing` alert (the existing `alertAboutRefusal` is for readings; D-27 says no owner alert).
- **`whyReadingBlocks()`** ignores unknown part names (`default => null`). Add a `'booking_link'` arm (Pattern 5).
- **D-21 in `whyBlocked()`** (OutboundSender.php:138-168) applies to `SendOrigin::Automated` only, after the opt-out check and (recommended) after the paused check. `SendOrigin::LifeSafety` and `Owner` keep today's rules. **Which existing tests send an Automated text to a factory contact who never texted?** Any test that builds `Contact::factory()` + a `Conversation` + `outboundSender->send(... SendOrigin::Automated ...)` with no inbound message will now be refused. Candidate files (all contain `OutboundSender`, `SendOrigin::Automated`, `SendFixedReply` or `SendCombinedReply`): `tests/Feature/FrontDesk/{AnsweredDraftTest,FrontDeskFailureTest,FrontDeskInvariantTest,HoldingReplyTest,LifeSafetyTest,ProcessInboundTest,ReadingRequiredToSendTest,TakeOverTest}.php`, `tests/Feature/Inbound/OptOutTest.php`, `tests/Unit/ArchitectureTest.php`; `ProcessInboundTest.php:399-401`, `TakeOverTest.php:140-141` and `OptOutTest.php:158` assert `whyBlocked()` results directly. The plan must list each failing test by file and line after running the suite, not from this grep; this list is the starting set. Prefer a helper (`textedBusinessBefore($contact)` in `tests/Pest.php` that records one inbound sms) over editing assertions.
- **"Has texted" definition:** derive it — an inbound `MessageChannel::Sms` message from the contact that is not itself the STOP that opted them out (use the `OptedOut` activity whose `subject_id` is that message; `OpenProjectForNewLead::arrivalWasStop` already does exactly this join). No column and no backfill; "the switch" is the only stored state (who, when). Alternative: a stored flag written in `RecordInbound` (hot path, needs backfill). Recommend derived.
- **`RunFrontDesk` alert order (D-34):** the raise sites are scattered (lines 188, 554, 907, 1203, 1210, 1265, 1289, 1305, 1335-1420 and `storeDraft()` at 1553). CONTEXT cites `:505` and `:880`; at the current HEAD `:880` is the docblock of `sendCheckInReply()` and `:505` is mid-docblock of `answerWhileDraftWaits`, so treat those numbers as stale pointers. Where the order is *enforced* is: complaint → `holdsOnlyBecauseWordsAreNotAnAnswer`, `alertsOwner: ! in_array($verdict->reasonCode, COMPLAINT_CODES)` at line 608, and `ownerNeedsAlertFor()`/`alertReasonFor()`. Recommend one new private method `changeRequestAlert(Conversation, Collection $burst, ?ReplyLabel, CustomerReading)` called **once per run** at the point after the agent reply is known and before `storeDraft`/`sendCheckInReply`, which returns whether it raised; then `storeDraft()`'s `ownerNeedsAlertFor` and `sendCheckInReply`'s `$alertsOwner` take that boolean so `draft_waiting` and `check_in` are suppressed when the change request already alerted (same suppression idea as line 608 for complaints). Anything ranked above (complaint, asked_for_person, life_safety, paused_message, front_desk_failed) returns earlier in `handle()` before the point, which is why placing it after those gives the specified order for free. Validate against all four sub-flows (`answerWhileDraftWaits`, `answerTouchingWithWholeThreadDraft`, `coverBothDraft`, plain draft).
- **Reading intent for D-34:** `CustomerReading->answers['intent']` is the owner-defined intent key from `rules/triage.md`; the example defines `scheduling` ("Wants to book, move or confirm a visit"). A folder without that key never matches by reading and only the reply label can trigger it; that is acceptable ("either"). [VERIFIED: examples/example-hvac/rules/triage.md; CustomerReading.php constructor shape]

### Pitfall 10: `ProjectWritesTest` and the arch rule
The scan reads every PHP file under `app/` and, when a file has `use App\Models\Project;`, flags *any* `->save(`, `->update(`, `->delete(`, `->forceFill(` and similar in that file. A `BookAppointment` that imports `Project` (to find the open project) and calls `$appointment->update(...)` will fail the test. Either do not import `Project` in files that write appointments (query via `$contact->projects()` and the `withNumber` scope), or split reading the project into a tiny class. The arch rule `toOnlyBeUsedIn([...'App\Actions','App\Filament\Resources\Contacts\RelationManagers','App\Rooms\Leads','Tests'])` constrains who may call `OpenProject`/`CloseProject`; `App\Livewire\BookingPage` and `App\Rooms\ScheduleAndBooking\...` calling them would fail — the page must call `BookAppointment` (in `App\Actions`), which calls `OpenProject`. A booking by hand in a Filament relation manager also calls `BookAppointment`, not `OpenProject`. [VERIFIED: tests/Unit/ProjectWritesTest.php read in full this session]

### Pitfall 11: "Unread" is `handled_at is null`
`ClaimInboundBurst::handle()` sets `handled_at` when a run *claims* a text, before the run finishes. D-26's "no inbound text waiting for its reading or front-desk run" is satisfied by `Message::where('direction', In)->where('author', Customer)->whereNull('handled_at')`. A text whose run is in progress is `handled_at != null` and `OutboundSender`'s per-conversation `reply:{id}` lock (OutboundSender.php:89) is the only thing that makes a reminder wait behind a running front-desk reply. Document that small window; test the `handled_at null` case, and the case where the run holds the lock (the reminder blocks up to `gate.agent.lock_wait_seconds`, then `DomainException` → retried next minute).

### Pitfall 12: The Filament `DateTimePicker` timezone state cast hides a UTC shift
A bare `DateTimePicker` without `->timezone()` uses `FilamentTimezone::get()` (the panel/app zone, UTC here), so an owner typing 10:00 meant as Chicago time stores 10:00 UTC. U-32 requires `->timezone($config->timezone())` on every owner date-time field and the test above. [VERIFIED: forms DateTimePicker.php:869-871 `getTimezone()` default]

### Pitfall 13: Visit note and service names in markup
`ProjectsRelationManager::rowButtonName()` wraps owner text in `e()` because Filament prints `extraAttributes` unescaped (T-07-25). The new row buttons' `aria-label` naming an appointment (U-33) must do the same, as must any `Notification::make()->title(...)` that interpolates a service name or a visit note (Filament renders titles as HTML). Test with a hostile note (`"><script>`) on the schedule, the tab, the card and the notification.

### Pitfall 14: The unique open conversation and a platform-started conversation
D-30's new conversation for a confirmation/reminder goes through `conversations_one_open_per_contact`; two sweep runs for two sends to one contact could both try to open one. Wrap in a savepoint and re-query on `UniqueConstraintViolationException`, as `OpenProject::forNewLead` does for its index.

## Code Examples

Verified patterns from the repo (values quoted from files read this session).

### The alert dedupe key the booked and change-request alerts keep
```php
// Source: app/Models/OwnerAlert.php:65-68
public static function dedupeKey(int $conversationId, ?int $messageId, AlertReason $reason, ?int $userId): string
{
    return 'c'.$conversationId.':m'.($messageId ?? 0).':'.$reason->value.':u'.($userId ?? 0);
}
```

### The claim a send row copies (condition shape)
```php
// Source: app/Actions/SendOwnerAlert.php:160-169 (same shape for booking_sends)
$claimed = OwnerAlert::query()
    ->whereKey($alert->id)
    ->whereNull('sent_at')
    ->whereNull('skipped_at')
    ->whereNull('failed_at')
    ->where(function (Builder $query): void {
        $query->whereNull('send_claimed_at')
            ->orWhere('send_claimed_at', '<=', now()->subMinutes(self::CLAIM_WAIT_MINUTES));
    })
    ->update(['send_claimed_at' => $claimedAt]);
```

### The scheduler entry style
```php
// Source: routes/console.php (existing entry); the new job follows it and asks hasRoom() inside handle()
Schedule::job(new SendDueAlerts)->everyMinute()->withoutOverlapping();
```

### Span overlap with the buffer, portable (recommended shape)
```php
// New code; start/end/buffer computed in PHP, bound as UTC timestamps. No SQL date math.
$bufferedEnd = $end->copy()->addMinutes($bufferMinutes);
$overlapping = Appointment::query()
    ->where('state', 'booked')                     // blocks included if one table; else union a second count
    ->where('starts_at', '<', $bufferedEnd)
    ->where('ends_at', '>', $start->copy()->subMinutes($bufferMinutes))
    ->count();
$taken = $overlapping >= self::CAPACITY;           // CAPACITY = 1, a count check (D-07)
```

### Business-zone day and elapsed stepping
```php
// New code. $zone = app(ActiveConfig::class)->timezone(); Carbon 3 behavior verified 2026-11-02 above.
$open  = CarbonImmutable::parse($dateString, $zone)->setTimeFromTimeString($entry['open']);
$close = CarbonImmutable::parse($dateString, $zone)->setTimeFromTimeString($entry['close']);
for ($start = $open; $start->addMinutes($duration) <= $close; $start = $start->addMinutes($step)) { /* test earliest, overlap */ }
```

### Locking the whole booking
```php
// New code; precedent: app/Actions/OutboundSender.php:89 Cache::lock("reply:{$conversation->id}", 30)
$lock = Cache::lock('schedule:book', 10);
$lock->block(5);   // LockTimeoutException -> "Try again" refusal, not a 500
try {
    return DB::transaction(function () use (...) { /* re-check, resolve project, insert, link consumed, send rows */ });
} finally {
    $lock->release();
}
```

### Playwright locator wait (the only wait style allowed)
```php
// Source: tests/Feature/Rooms/LeadsBootTest.php (end of file). waitForFunction()/waitForURL() never wait in pest-plugin-browser 5.1 (STATE.md).
$browser->getByRole('button', ['name' => 'New job', 'exact' => true])->waitFor(['state' => 'attached']);
```
Phone-width tests: the plugin offers `resize(int $width, int $height)` (`Api/Concerns/InteractsWithViewPort.php:12`), `->on()->mobile()` (`Api/On.php:61`) and `->withTimezone($tz)`/`->withLocale($l)` on the pending page (`Api/PendingAwaitablePage.php:102,135`). [VERIFIED: vendor/pestphp/pest-plugin-browser/src grep]

## Existing tests the shared-code changes force (starting list; each plan confirms by running the suite)

Per Ian's planning note: sweep first, re-point in the same plan, each named by file and line. Candidates found by grep this session:

| Change | Existing tests to read first |
|--------|-----------------------------|
| D-21 `whyBlocked()` | the 11 files in Pitfall 9; `ProcessInboundTest.php:399-401`, `TakeOverTest.php:140-141`, `OptOutTest.php:158`; any test using `Contact::factory()` + `SendOrigin::Automated` with no inbound message |
| D-26 new kind in `OutboundSender` | `tests/Unit/ArchitectureTest.php` (references `OutboundSender`), `tests/Feature/FrontDesk/ReadingRequiredToSendTest.php`, `FrontDeskInvariantTest.php` (the "only OutboundSender sends" invariants) |
| D-34 alert order | `tests/Feature/Alerts/OwnerAlertTimingTest.php`, `tests/Feature/FrontDesk/HeldDraftTest.php`, `AnsweredDraftTest.php`, any test asserting `OwnerAlert::count()` after a customer text; tests asserting the exact set of `AlertReason::cases()` |
| New `AlertReason` cases | any test iterating `AlertReason::cases()` or `AlertReason::values()`; `tests/Feature/Icm` owner_alert validation tests |
| `ProjectActor` third value | `tests/Feature/Projects/{ProjectRecordTest,OpenOnNewLeadTest,ProjectActionsTest,ProjectCardTest}.php`, `tests/Feature/Filament/ProjectsRelationManagerTest.php`, `tests/Feature/Rooms/LeadsSectionTest.php` |
| `CloseProject` refusal (D-25) | `ProjectActionsTest.php`, `ProjectsRelationManagerTest.php`, `ProjectsRelationManagerKeyboardTest.php` |
| `CustomerCard` change | `tests/Feature/Records/{CustomerCardTest,CardInjectionTest,RenderCustomerCardJobTest,RecordsRebuildTest}.php`, `tests/Feature/Projects/ProjectCardTest.php`, `RoomTimelineTest.php` |
| `ContactResource::canCreate()` | no test asserts it (grep for `canCreate` found none); add one that the create page renders and a duplicate phone is refused |
| Example folder | `exampleFolderFileCount()` 26 → 28 in `tests/Pest.php`; `tests/Feature/Rooms/{ExampleRouterTest,RoomOwnershipTest,SpecTablesTest,RoomContractTest,PretendRoomBootTest}.php`; `exampleRoomKeys()` consumers; front-desk intake tests do not churn because services are unchanged (D-42) |
| `front-desk.md` line (D-05) | `tests/Feature/FrontDesk/FrontDeskPromptTest.php` (the agent body is part of the prompt) and any test snapshotting the example agent body |
| `ReplyParts` new field | no `ReplyParts` unit test found under `tests/Unit/SendRule`; grep `ReplyParts` in `tests/Feature/FrontDesk` and check `SendCombinedReply` callers |
| `BusinessClock::windowOn` public | `tests/Feature/Icm/BusinessClockTest.php` (additive) |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Livewire 2/3 `wire:ignore` for state kept across morphs | Livewire 4 `wire:ignore.self`, `#[Locked]`, `Route::livewire` | Livewire 3/4 | Used here; `Route::livewire` already in `routes/web.php` |
| Passport-style bcrypt tokens | sha256 of a high-entropy random token for bearer links | long-standing | Pattern 2 |
| `$request->throttle` on the page route | In-component `RateLimiter` for Livewire actions | Livewire 3+ | Pitfall 1 |
| SQLite `BEGIN DEFERRED` assumed serial | `transaction_mode` is `'DEFERRED'` in `config/database.php:44` | — | Do not rely on SQLite for race proof (Pattern 4) |

**Deprecated/outdated in this repo:** the CONTEXT line pointers `RunFrontDesk.php:505` and `:880` (see Pitfall 9); the comment in `LeadsSection::SHOWN` ("closing is by hand until Phase 8") — closing stays by hand in Phase 8 (D-24), so it stays true.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Confirmations and reminders should be wrapped with `ComposeAutomatedText::forReply()` (sign-off always, AI disclosure when it is the conversation's first automated text), because Phase 2 D-50 says every automated text carries them | Pattern 7 | If Ian wants reminders without the AI disclosure/sign-off, the body shape and a test change; also changes the 160-character budget of the text. CONTEXT D-26 only says "an owner-written line from the live booking file"; ask Ian |
| A2 | The drive buffer (D-08) applies to the end of *appointment* spans (and the new span) but not to a block's own span, so a customer can start immediately after a block ends but cannot end flush against a block's start | Pattern 3 | If Ian means blocks carry a buffer too, availability is stricter by `buffer` minutes after each block. D-08 says "added to the end of every appointment's span"; blocks are not appointments |
| A3 | One `appointments` table with `kind` (appointment/block) is allowed; CONTEXT's Room table list names "appointments, blocks, booking links and the confirmation and reminder sends" but gives tables/columns as Claude's discretion | Pattern 11 | If separate tables are required, the grouped schedule needs a union and the overlap check two counts; either works |
| A4 | `Cache::lock` is sufficient for "never two" because production uses the `database` cache store (`.env.example`) and the lock plus the in-transaction re-check serialises bookings | Pattern 4 | If production changes cache to a per-process store, two workers could double-book; recommend a note in `docs/SETUP.md` that `CACHE_STORE` must be shared |
| A5 | `IntlDateFormatter` pattern `VVVV` yields "Central Time" for `America/Chicago`; not run this session | Pattern 6 | Wording of the zone line; fall back to IANA id per U-11. Confirm in the first test |
| A6 | A `scheduling` draft approved by the owner and sent through the owner reply path carries no link (D-01 appends only to a reply the agent sends) | Pattern 5 | If Ian wants the link on approved drafts too, the owner reply path needs a link step |
| A7 | Derive "has texted" from inbound SMS messages excluding the STOP that opted the contact out, rather than storing a flag | Pitfall 9 | If a stored flag is wanted for the card ("On, the customer texted") a backfill migration is needed |
| A8 | `X-Robots-Tag` / `Referrer-Policy` response headers via route middleware are acceptable in addition to the UI-SPEC meta tags | Pattern 6 | None; additive hardening |
| A9 | No schema "enum" constraint on `appointments.state`; use a string with a PHP backed enum like the rest of the app | Pattern 2 | Consistency only |

## Open Questions

1. **Does the AI disclosure/sign-off wrap a confirmation or reminder?** (A1) What we know: D-50 (Phase 2) puts them on every automated text; D-26 calls the body "an owner-written line". Recommend yes (use `ComposeAutomatedText`), and confirm with Ian before the wave that builds the sender, because it changes every reminder's text and length.
2. **Does a block count toward the drive buffer?** (A2) Recommend the reading in A2. Ask Ian only if he drives the van between blocks.
3. **One table or two for appointments and blocks?** (A3) Recommend one table with `kind`. The Room's `tables` list is a documentation list (`Room::$tables`), so either satisfies ROOM-12.
4. **Should the phase be split?** Ian's note: if the planner recommends splitting, stop and bring it to him. Assessment: no split recommended. The pieces are coupled (token → Book → alert → texts → room) and splitting would leave a booking page without its reminders. Plan count is high (expect roughly 10 to 12 plans in about 6 waves). If Ian prefers a split, the only clean seam is "texts and alerts plumbing" (D-21, D-26, D-33, D-34 plus tables) versus "page, owner screens, room", but BOOK-02's success criterion needs both. Surface this to Ian; do not decide it here.
5. **UI-SPEC "unresolved" row:** appointments tab paging is unspecified; use Filament's stock relation-manager pagination and add a three-row-plus test; no decision needed.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | everything | ✓ | 8.5.12 | — |
| Node | Vite build, Playwright | ✓ | v24.12.0 | — |
| Composer packages listed | all | ✓ | see Standard Stack | — |
| ext-intl | zone display name | ✓ | loaded locally; listed in CI `extensions` | IANA id (U-11) |
| ext-pdo_sqlite / pdo_pgsql | tests on both DBs | ✓ | loaded locally | — |
| PostgreSQL server | prove Postgres locally | ✗ | `pg_isready` not found | CI runs `postgres:17` (tests.yml); Ian pushes between waves per his note |
| Playwright Chromium | browser tests | ✓ | playwright 1.63.0; Chromium builds present in `~/Library/Caches/ms-playwright` | — |
| Vite build output (`public/build`) | styled booking page in tests | ✓ locally / ✗ in CI | built locally | add `npm run build` to CI (Pitfall 2) |
| Twilio | live texting | not needed (fake driver) | — | `FakeTextChannel`; DEC-05 |
| Outside network (Bunny fonts) | `npm run build` | needed at build | — | none; fonts fall back to the stack in `--font-sans` |

**Missing dependencies with no fallback:** none block coding. Postgres proof depends on CI.
**Missing with fallback:** local Postgres (use CI); CI `npm run build` (add it).

## Validation Architecture

> `workflow.nyquist_validation` is enabled (Ian's instruction). Config file not re-read; treated as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 5.3.1 on PHPUnit; pest-plugin-browser 5.1.2 + Playwright 1.63.0 for browser tests |
| Config file | `phpunit.xml` (suites Unit, Feature); `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `Feature` and copies `examples/example-hvac` to a temp folder per test |
| Quick run command | `php artisan test --compact tests/Feature/Booking/BookAppointmentTest.php` (narrowest file; Boost rule) |
| Full suite command | `php artisan test --compact` (about 2272 tests before this phase per STATE.md; ask Ian to run it) |
| Database legs | SQLite `:memory:` locally; CI matrix runs SQLite and Postgres 17 for every push |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| BOOK-01 | Only times inside hours/closures/lead/duration/zone are offered; none in the past; fall-back and spring-forward days | unit/feature | `php artisan test --compact tests/Feature/Booking/AvailabilityTest.php` | ❌ Wave 0 |
| BOOK-01 | Lead counted from the later of now and next opening (10 PM → 10:00) | feature | same file | ❌ |
| BOOK-01 | Overlap + buffer + capacity count; block counts; end by closing | feature | same file | ❌ |
| BOOK-02 | Link appended only for scheduling label, book_online service, room live, no upcoming booking; never written by the model | feature | `php artisan test --compact tests/Feature/Booking/BookingLinkTest.php` | ❌ |
| BOOK-02 | Token stored hashed; superseded/revoked/expired/single-use states | feature | `tests/Feature/Booking/BookingLinkTokenTest.php` | ❌ |
| BOOK-02 | Page renders each state; tap → check → book; taken time → fresh list | Livewire feature | `tests/Feature/Booking/BookingPageTest.php` (`Livewire::test`) | ❌ |
| BOOK-02 | Rate limit trips on taps, not only GET | feature | `tests/Feature/Booking/BookingPageThrottleTest.php` | ❌ |
| BOOK-03 | Appointment on card ("Next appointment", project sub-lines), timeline, schedule page, section; two on one project show both | feature | `tests/Feature/Booking/AppointmentCardTest.php`, `SchedulePageTest.php` | ❌ |
| BOOK-03 | Contact with no appointment renders exactly as before | feature | `tests/Feature/Records/CustomerCardTest.php` (existing) stays green | ✅ |
| BOOK-04 | Reminder `due_at` moves to window end; skipped silently if past; same-moment dedupe; DST | unit | `tests/Unit/Booking/ReminderScheduleTest.php` | ❌ |
| BOOK-04 | Claim exactly-once; released row re-checks; cancelled never sends | feature | `tests/Feature/Booking/BookingSendTest.php` | ❌ |
| BOOK-04 | New kind: refused without appointment id / not booked / not ahead / unread text; opt-out first; switch off refused | feature | `tests/Feature/Booking/OutboundBookingKindTest.php` | ❌ |
| BOOK-04 | Scheduler entry registered and asks `hasRoom` | feature | `tests/Feature/Booking/BookingScheduleTest.php` (`Schedule` events listing) | ❌ |
| BOOK-05 | Booked alert once, after hours waits to next opening; none for hand booking; change-request ranks above check_in/draft_waiting, below complaint etc. | feature | `tests/Feature/Alerts/BookingAlertTest.php`, `ChangeRequestAlertTest.php` | ❌ |
| D-04 | No inbound text creates or changes an appointment | feature | `tests/Feature/Booking/NoTextBookingTest.php` | ❌ |
| D-41 | A booking leaves contact status unchanged | feature | in `BookAppointmentTest.php` | ❌ |
| D-15 | Only named actions write appointments/blocks | arch scan | `php artisan test --compact tests/Unit/AppointmentWritesTest.php` | ❌ |
| ROOM-12 | Boot test (empty and example), registered room, contract vs SPEC rows, axe on section, D-36 and D-37 refusals | feature | `tests/Feature/Rooms/ScheduleAndBookingBootTest.php`, `ScheduleAndBookingSyncTest.php` | ❌ |
| A11Y-02 | axe (wcag2a/aa/21/22aa + target-size) on the booking page in each state; Tab order at desktop and 375; time button accessible names "10:00 AM, Tuesday, November 3"; focus to h1 after booking and to error line after taken time; ≥48 px targets at 375 | browser | `php artisan test --compact tests/Feature/Booking/BookingPageBrowserTest.php` | ❌ |
| A11Y-02 | Contract checks: no `style=`/`<style>`, no hex/raw palette, size/weight/arbitrary-value classes, no `<x-heroicon-`, no exclamation/em dash, `sms:` number from `ActiveConfig` | feature (file scan) | `tests/Feature/Booking/BookingPageContractTest.php` | ❌ |
| A11Y-02 | Owner screens: axe + keyboard/focus after Cancel visit / Mark done / dialogs | browser | `tests/Feature/Filament/AppointmentsKeyboardTest.php`, `ScheduleBootTest` | ❌ |
| D-43 | IN-08, IN-09, IN-11 land: focus wait rethrows non-timeouts; `LeadsBootTest` Tab cap measured; key-press helper moved to `tests/Pest.php` | refactor | `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php`, `tests/Feature/Rooms/LeadsBootTest.php` (existing) | ✅ edit |
| D-19/D-20 | Add contact; duplicate phone/email refused beside field; hand-added contact opens no project; switch on/off recorded; STOP never turns it on | feature | `tests/Feature/Booking/ContactByHandTest.php`, `AutomaticTextsSwitchTest.php` | ❌ |
| D-25 | Close refused with a booked visit ahead | feature | `ProjectActionsTest.php` (existing, extend) | ✅ |

### Sampling Rate
- **Per task commit:** the narrowest test file for the task, `php artisan test --compact <file>`; Pint on dirty files.
- **Per wave merge:** all of `tests/Feature/Booking`, `tests/Unit/AppointmentWritesTest.php`, plus each existing file in the "forced" table touched in the wave; CI proves both databases after Ian pushes.
- **Phase gate:** full suite green on SQLite and Postgres in CI before `/gsd-verify-work`; then UAT: keyboard-only and VoiceOver walk of the booking page, 200% zoom, 24/48 px targets, Dev chat checks for D-05 (offer comes back labeled `scheduling` with the link) and D-39 ("what time are you coming?"). Per Ian's memory note, executors record live hand checks for end-of-phase UAT; they never pause for them.

### Wave 0 Gaps
- [ ] `tests/Pest.php` helpers: `textedBusinessBefore(Contact)`, a booking-file fixture writer, an hours fixture that opens at 00:00 for DST, a `bookingLinkFor(...)` factory path; move the key-press helper here (IN-11)
- [ ] `tests/Fixtures/` booking and DST hours files (the pretend-room pattern, `layFixtureFolder`)
- [ ] Factories for the new models (Boost rule); `database/factories/AppointmentFactory.php` etc.
- [ ] CI: `npm run build` step in `.github/workflows/tests.yml`
- [ ] Browser-test pattern: no `waitForFunction()`/`waitForURL()` anywhere; use `getByRole(...)->waitFor(['state' => 'attached'])`; `evaluate()` for `document.activeElement`

## Security Domain

> `security_enforcement` is not set to false in anything read; included.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no (customer page is anonymous); owner screens use the existing Filament login | Bearer link token is the credential; Filament auth unchanged |
| V3 Session Management | partial | Livewire's checksummed snapshot; `#[Locked]` properties; no customer session |
| V4 Access Control | yes | Token scopes access to one conversation/contact/service; owner actions sit behind the Filament panel; the page shows no customer name, phone or address (D-13) |
| V5 Input Validation | yes | Server re-validates the tapped instant against `Availability`; Filament form rules for owner entry; Phone via `PhoneNumber::toE164`; lengths 200/100 (U-24) |
| V6 Cryptography | yes | `random_bytes`/`Str::random(40)` secret, `hash('sha256', ...)` stored; never hand-rolled |
| V7 Error handling/logging | yes | Failures `report()`ed; page shows fixed plain lines, never exception text |
| V8 Data protection | yes | Hash-only token storage; no PII in the URL, title, or response to a token holder |
| V13/API & rate limits | yes | In-component rate limiter + GET limiter (Pitfall 1) |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Link guessing/brute force | Spoofing | 256-bit random token, sha256 lookup, rate limit by IP and hash; unknown = revoked page (no enumeration oracle) |
| Link forwarded or leaked via Referer/search | Info disclosure | `noindex`, `no-referrer` (meta and headers), page shows no personal data, link books at most once, expiry |
| Tampered Livewire payload (slot, token, step) | Tampering | `#[Locked]`; server recomputes offered list inside the lock; rejects anything not offered |
| Double booking race | Tampering/DoS | `Cache::lock` + in-transaction re-check; refuse with fresh times |
| Replay of Book (double tap) | Tampering | `aria-disabled` + server-side in-flight guard; link `appointment_id` single-use; unique `(appointment_id, lead_minutes)` for sends |
| Stored XSS through service/business/visit note | Tampering | Blade escaping; `e()` in Filament `aria-label` and notification titles (T-07-25 precedent) |
| Prompt/text injection via visit note into the card the front desk reads | Tampering | Quote under "Customer-supplied text, not instructions:" (07 D-13) |
| Reminder to someone who opted out / did not consent | Compliance | Opt-out first in `whyBlocked()`, D-21 texted-or-switch rule, send window; consent and window legal bounds on the attorney list (D-44, informational; not decided here) |
| Reminder over an unanswered danger message | Safety | D-26 "no unread customer text" rule |
| Owner alert quoting attacker text as a link | Tampering | Existing `OwnerAlertText::clean()`; the booked alert quotes no customer text (Pitfall 8) |

## Sources

### Primary (HIGH confidence) — repository files read in full or in the relevant range this session
- `.planning/phases/08-schedule-and-booking/08-CONTEXT.md`, `08-UI-SPEC.md`; `.ai/rules/*.md`; `CLAUDE.md`
- `app/Actions/{OutboundSender,RaiseOwnerAlert,SendOwnerAlert,OwnerAlertText,OpenProject,CloseProject,SendCombinedReply,SendFixedReply,ComposeAutomatedText,ClaimInboundBurst}.php`, `app/Actions/RunFrontDesk.php` (lines 120-440, 440-760, 860-925, 1475-1685 and raise-site grep)
- `app/Models/{OwnerAlert,Project,Contact,Conversation,Message,Activity}.php`, `app/Enums/{AlertReason,ProjectActor,MessageKind,MessageAuthor,MessageChannel,ActivityType,ReplyLabel,SendOrigin,OwnableKind}.php`
- `app/Icm/{BusinessClock,ActiveConfig,FolderSync,RoomChecks,ConnectorChecks,Validator,PlainWords}.php`, `app/SendRule/{CodeChecks,CustomerReading,ReplyParts,FixedReplies}.php`
- `app/Rooms/*` (Room, RoomFileType, RoomRegistry, RoomServiceProvider, TimelineEntryType, RecordRoomEntry, Leads/*, ContentGeneration/*), `app/Records/CustomerCard.php`, `app/Filament/Resources/Contacts/*`
- `app/Jobs/{SendDueAlerts,ProcessInbound,SweepUndispatched}.php`, `app/Livewire/DevChat.php`, `app/Providers/AppServiceProvider.php`
- `routes/{web,console}.php`, `bootstrap/providers.php`, `config/{app,database}.php`, `.env.example`, `phpunit.xml`, `.github/workflows/tests.yml`, `vite.config.js`, `package.json`, `resources/css/app.css`, `resources/views/{layouts/app,welcome}.blade.php`
- `tests/Pest.php`, `tests/Unit/ProjectWritesTest.php`, `tests/Feature/Rooms/{LeadsBootTest,MoneyBootTest}.php`, `tests/Feature/Filament/ProjectsRelationManagerKeyboardTest.php` (head), `tests/Rooms/{RoomSectionAxe,SpecTables}.php`
- `examples/example-hvac/{business/hours.md,business/profile.md,rules/sending.md,rules/triage.md,agents/front-desk.md,rooms/leads/CONTEXT.md,rooms/money/*}`
- `docs/SPEC.md` (ownership table, line 237), `.planning/PROJECT.md` (DEC-06, DEC-08), `.planning/REQUIREMENTS.md`, `.planning/STATE.md` (browser wait note)
- Vendor source read: `vendor/laravel/framework/.../DatabaseTokenRepository.php`, `SQLiteGrammar.php`, `Foundation/Vite.php`; `vendor/livewire/livewire/.../PersistentMiddleware.php`; `vendor/filament/{actions CanOpenModal, tables CanGroupRecords, forms DateTimePicker, schemas DateTimeStateCast}`; `vendor/pestphp/pest-plugin-browser/src` (grep)
- Runtime checks: PHP 8.5.12 `php -r` Carbon 3 DST run; `composer show --direct`; `npm ls`

### Secondary (MEDIUM confidence)
- None used; Context7 requires authentication in this session and was not available, so framework behavior was verified against installed vendor source instead.

### Tertiary (LOW confidence)
- `IntlDateFormatter` `VVVV` output for `America/Chicago` (A5) — not run.
- Sanctum's token storage as a precedent for sha256 (not installed here; training knowledge, so not relied on — the repo-verified reasoning in Pattern 2 stands alone).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — read from `composer show` and `package.json`; no new package.
- Architecture: HIGH for the seams (read the code); MEDIUM for the three discretionary choices (A2, A3, A4) which Ian may want to confirm.
- Pitfalls: HIGH for 1 to 4, 9, 10, 12 (verified in source); MEDIUM for 5, 6, 11, 14 (reasoned from verified mechanics, not run).
- Validation: MEDIUM — commands are right; test files do not exist yet.

**Research date:** 2026-11-02
**Valid until:** 2026-11-30 for the repo facts (the code moves with each plan); the Livewire 4.4.7 / Filament 5.10.1 facts hold until a package bump.
