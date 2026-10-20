# Phase 2 owner input (2026-10-11)

Input for `/gsd-discuss-phase 2`. Ian's answers to the owner-morning sketch, plus research and recommendations.
Ian's answers are his stated direction. Items marked **Recommendation** are proposals to decide in discuss, not locked decisions.

**Scene:** an HVAC owner, 7:15 am, in the truck between jobs. Three texts came in overnight. The agent answered two and held one as a draft because the customer asked "how much for a new unit?"

## 1. How the owner finds out a draft is waiting (GATE-01 `owner_alert`)

**Ian:** a text to the owner's phone, and an email as well. When the draft is handled on one channel, the other gets a follow-up saying it was handled. Example: someone in the office watches email; the owner answers from the truck by text; the office gets an email saying it has been answered. Same in reverse.

**Recommendation:**
- `owner_alert` in `rules/sending.md` is free text today ("Text the owner when a draft is waiting or a customer asks for a person"). Make it structured: channels (sms, email), recipients (owner phone, office email), and whether the "handled" follow-up is sent. The validator and the example folder change with it.
- The "handled" follow-up names who handled it and how ("Answered by Mike by text at 7:22"). Cheap, and it stops two people answering the same customer.
- **Open:** a draft nobody acts on. Owners who are slow to answer non-urgent messages are the normal case, not the exception. Decide a re-alert after a set time, a backup recipient, and an update to the customer if the wait runs past what the holding reply said (My AI Front Desk sends to a backup manager when a ticket sits untouched).
- **Ian (decided):** more than one person can approve. Owners will hand some decisions to someone trained to answer for them, and each business differs, so the owner adds whoever they want. This pulls the deferred "admin access rules when staff logins appear" into Phase 2.
- **Recommendation (minimum for Phase 2):** the owner invites users from the dashboard; each user has a phone, an email, their alert choices and a "can approve drafts" switch; every approval, edit and discard records who did it, which is also what the "handled" follow-up names. Keep user management and Sync Now owner-only, the first real role split. Fuller roles wait for a client who needs them.

## 2. A holding reply before the draft

**Ian:** when a question like "how much for a new unit?" comes in, the customer still gets a reply right away saying the answer should come from the owner, that he has been notified, and when he will respond. Exact wording later.

**Recommendation:**
- "He'll respond first thing in the morning" is a time promise, and a promise is one of the four things GATE-01 never sends on its own. Keep the holding reply safe by making it a fixed template the owner writes once in the business folder, with the timing computed in code from `business/hours.md` (next opening time, closures included), so the promise is always true. Example: "Mike handles pricing himself. I've sent him your question and he'll get back to you when we open at 8 tomorrow."
- The holding reply sends on its own; the real answer is the draft.

## 3. What the owner sees, and how many taps

**Ian:** the whole thread, to get oriented. Two taps to send, with the option to edit the draft first.

**Recommendation:** a text can't hold a whole thread. The alert carries a short summary, the draft, and a link. The link opens a phone-friendly thread page (not the dashboard home) with the draft already in the reply box: tap the link, tap Send, or edit then Send.

**Ian (decided):** a remembered login. Staff phones and laptops stay signed in.

**Recommendation:** Filament's login with "remember me" and a long lifetime; when the session has lapsed, the link goes to the login page and returns to the thread after sign-in. The owner can sign a user out everywhere or remove them, for a lost phone or someone who leaves. Replying to the alert text (section 4) stays the no-login path.

## 4. Approve by replying to the alert, without the dashboard

**Ian:** on the phone, the owner doesn't need the dashboard; reading a whole dashboard on a phone is hard. If the thread is in front of them, replying is enough, as long as everything is captured in the system. Ian's own preference; worth checking with owners in the field.

**Recommendation:**
- Reply-by-text is a scope addition to GATE-04 (which says review in the dashboard). Worth it, and it fits the market pattern below.
- Each alert carries a short random code, for example K7. Reply "K7" to send the draft as written, or "K7" followed by your own words to send those instead. A reply with no valid code gets a short help text back and sends nothing. The code settles which draft when two are waiting, and makes a spoofed or mistyped reply harmless.
- Only the owner's registered phone can approve. Every reply-approval is recorded on the timeline like a dashboard approval.
- Email reply-to-approve needs inbound email parsing, a bigger build. Phase 2: email carries the link only.
- Real texting waits on A2P for Ian's number; the fake channel and /dev/chat can test the whole loop locally.

## 5. What's urgent and what can wait (Jev)

**Ian:** a complaint is urgent; pricing less so, but still gets a "we're getting back to you" message. Consider a classifier like Jev.

**Recommendation** (from `Titus HQ/AI Practice/METHOD-using-a-classifier-in-a-build.md`):
- Triage passes Ian's own Jev test: a pick from a list, on every inbound text, and code cannot decide it exactly.
- Code first: STOP/opt-out words, dollar amounts and obvious keywords stay code.
- The questions live in a file in the business folder (method rule 7), for example intent [scheduling, faq, price_question, complaint, emergency, other] and urgency [now, today, can_wait], always with an "other".
- Thresholds: for anything that could send, the safe direction is hold. Only a high score sends on its own; everything else becomes a draft.
- The owner's approve, edit and discard decisions are free labels for calibration (method rules 4 and 5). Phase 2 should record them in a form that can be exported.
- titus-site already calls Jev 1.13 through OpenRouter's Decisions endpoint over plain HTTP, so Laravel can call it without the AI SDK. Put it behind an interface with a fake for tests and a language-model fallback driver (DEC-14, no lock-in).
- **Zero data retention (researched 2026-10-11):** Jev on OpenRouter is served by one provider, TypeSafe. OpenRouter keeps no prompts unless prompt logging is turned on, and can enforce ZDR account-wide or per request (`provider.zdr: true`). TypeSafe's legal page says it does not train on user data, but ZDR is offered to enterprise customers only; otherwise retention follows its Data Processing Agreement, so default retention is not zero. On Vercel AI Gateway, Jev can also be served by DigitalOcean, which is not ZDR; pin the provider if that route is used.
  - Before a real client: read TypeSafe's DPA for the retention period, ask sales@typesafe.ai about ZDR for a small account, and test by turning on OpenRouter's ZDR enforcement and calling Jev (if the call is refused, the route is not ZDR).
  - Send Jev only the message text, never the customer's name or phone. It needs nothing else to sort a message.
  - The classifier interface keeps a fallback: a language-model driver on a ZDR route can stand in if TypeSafe can't offer it.
- **Ian (decided):** after-hours emergencies are configurable per business, an on/off switch, with each owner setting up their own process. Businesses that take emergency calls will want to set it themselves.
- **Recommendation:** the switch and who is on call live in the business folder. One exception that is not optional: life-safety situations (gas smell, carbon monoxide alarm, sparking or burning smell, active flooding) always get an immediate safety line telling the customer what to do now, worded once by the owner, triggered by code keywords first. The switch only decides whether someone is woken up. **Ian:** the safety line tells the customer to call the authorities (911) for gas smell, fire or smoke and the like. Recommendation: it says get to safety and call 911 from outside; the owner may add the gas utility's number; it never gives repair or shut-off steps; it sends before any other reply; and when unsure whether a message is an emergency, it sends (opposite of the price check, which holds when unsure).

**Ian (Jev call details, given at the start of Phase 2 planning; the repo he refers to is titus-site):**

> Jev is real and newer than model training (TypeSafe's classifier); do not substitute another classifier. The working call, from a repo you cannot see: `POST https://openrouter.ai/api/v1/systemone` with headers `Authorization: Bearer <OpenRouter key>` and `Content-Type: application/json`, body `{"model":"typesafe/jev-1.13","state":"<message text>","questions":{"<key>":{"type":"noul","instructions":"<yes/no question>"}}}`, response `{"answers":{"<key>":{"noul":<0 to 1>}}}`. Keys are letters, digits and underscores. That code only uses noul (yes/no) questions. Confirm from docs.typesafe.ai: (1) the request and response shape for a choice question, which D-04 needs for intent; (2) whether jev-1.13.0 can be pinned on this route (D-05) or only typesafe/jev-1.13; (3) how timeouts and errors come back. Keep its completeness check: every question asked must come back with a number, otherwise treat the call as failed. Do not copy its error handling: that code fails open, this phase fails closed (D-03), so an HTTP error, a timeout or a missing answer holds the draft.

## 6. When the agent backs off (GATE-03)

**Ian:** some kind of choice for the owner, but not a button on every message. Will differ by business. Override the agent without stopping its involvement entirely. Discuss further.

**Recommendation:**
- When the owner replies in a conversation, the agent switches to **assist**: it stops sending on its own in that conversation but keeps drafting suggestions the owner can send with one tap.
- It returns to normal after a quiet period set per business in the folder (for example "4 hours" or "next business day").
- One **Take over** button per conversation, not per message, for a full stop until handed back. Assist mode still satisfies GATE-03 as written ("while taken over the agent sends nothing").

## 7. Home screen

**Ian:** phone or desktop? The likely candidates (drafts waiting, new leads, today's conversations) cover it for now.

**Recommendation:** the phone path never lands on the home screen; alerts deep-link to the thread. The home screen is the desktop and office view: drafts waiting, new leads, today's conversations.

## Also bring to discuss

- WR-02 (dispatch after commit, leaning `dispatched_at`) and WR-13 (sending authority, SMS consent on the form). See `.planning/milestones/v1.0-phases/01-foundation/01-REVIEW-DISPOSITION.md`.
- Confirm derived requirements AGNT-03 and GATE-04.
- Phase 1 UAT notes, the empty-name fill and IN-03, carried in `.planning/STATE.md`.
- If a real owner will answer, ask questions 1 and 4. If not, ship sensible defaults as settings in the business folder (alert channels, reply-by-text on or off, quiet period) and let the first client's use decide. The approve, edit and discard log is that research.

## Research notes

- Jobber Receptionist: keyword-triggered text alert to the owner; the owner can watch text conversations live and take over anytime. https://agilebrandguide.com/jobber-launches-ai-powered-receptionist-to-answer-calls-and-texts-for-busy-home-service-businesses/
- My AI Front Desk escalation: the on-duty manager gets a text and an email with a summary and transcript link; a backup manager gets it if untouched; the customer gets a text saying who is on it and when to expect contact. https://www.myaifrontdesk.com/use-cases/for/home-services/escalation-button
- DealerAI: explicit Take Over and Pass Back, no auto-resume; a held conversation stays held until passed back. https://docs.dealerai.com/docs/portal/conversations/pause-and-resume-ai
- Chatfuel: automation pauses when an agent replies; resumes when reassigned or after a configurable inactivity timeout. https://chatfuel.com/docs/ai-features/how-to-stop-ai-during-active-conversations
- GoHighLevel: a Human Handover workflow action stops the AI in that conversation until resolved or closed. https://consultevo.com/gohighlevel-conversation-ai-human-handover/
- Signpost (Luke Wilson, Endless Customers podcast, in `AI Practice/_sources/new-speed-to-lead-from-first-reply-to-booked-appointment.md`): owners live on their phones, build mobile-first; handing control to the technology is the owner's biggest fear.
