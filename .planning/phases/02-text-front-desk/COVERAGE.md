# API Coverage — Phase 2 Text Front Desk

> Full coverage by default. Opt-outs are explicit, reasoned decisions. Three external surfaces: Twilio Programmable Messaging (plans 02-24 to 02-27), Jev through TypeSafe's System One API on OpenRouter (plans 02-06, 02-07), and the reply models through laravel/ai (plans 02-13 to 02-15).

## Twilio Programmable Messaging

| capability | decision | reason |
|---|---|---|
| twilio: send SMS through a Messaging Service | INTEGRATE | |
| twilio: inbound SMS webhook | INTEGRATE | |
| twilio: webhook signature validation | INTEGRATE | |
| twilio: delivery status callbacks | INTEGRATE | |
| twilio: Advanced Opt-Out OptOutType on inbound | INTEGRATE | |
| twilio: error 21610 unsubscribed recipient | INTEGRATE | |
| twilio: receive MMS media files | OPT-OUT | not needed yet: the number of attachments is recorded and the model is told a picture arrived; downloading media waits for a client who needs it |
| twilio: send MMS media | OPT-OUT | not needed: the front desk sends text only |
| twilio: fetch or list messages | OPT-OUT | not needed yet: status callbacks keep delivery state; no reconciliation job in Phase 2 |
| twilio: delete or redact messages | OPT-OUT | not needed yet: no retention policy has been decided |
| twilio: scheduled messages (SendAt) | OPT-OUT | explicitly out of scope: reminders are Phase 3 and use the app's scheduler |
| twilio: Messaging Service management API | OPT-OUT | not needed: one-time console setup by Ian (plan 02-26 user_setup) |
| twilio: phone number provisioning API | OPT-OUT | not needed: Ian's test number is registered by hand |
| twilio: A2P 10DLC registration API | OPT-OUT | explicitly out of scope: done in the console; it gates going live, not the build (DEC-05) |
| twilio: Lookup API | OPT-OUT | not needed: libphonenumber already normalises to E.164 |
| twilio: Conversations API | OPT-OUT | not needed: the app keeps its own conversations in the database (DEC-03) |
| twilio: Verify API | OPT-OUT | not needed: no one-time-code flows |
| twilio: voice calls | OPT-OUT | explicitly out of scope: Voice is Backlog 999.2 |
| twilio: WhatsApp and RCS channels | OPT-OUT | explicitly out of scope: the agent channel is sms only (D-35) |
| twilio: link shortening and click tracking | OPT-OUT | not needed: alerts carry our own dashboard link last (D-21) |
| twilio: Content API templates | OPT-OUT | not needed: every automated text is built by code or written by the owner in the folder |
| twilio: Event Streams | OPT-OUT | not needed: per-message status callbacks are enough |

## Jev (TypeSafe System One via OpenRouter)

| capability | decision | reason |
|---|---|---|
| jev: choice question | INTEGRATE | |
| jev: noul yes/no question | INTEGRATE | |
| jev: state as named fields | INTEGRATE | |
| jev: pinned model version | INTEGRATE | |
| jev: served model string in the response | INTEGRATE | |
| jev: TypeSafe direct API route | INTEGRATE | |
| jev: usage figures in the response | OPT-OUT | not needed yet: cost tracking per call is not part of Phase 2 |
| jev: other System One primitives beyond choice and noul | OPT-OUT | not needed: the send rule only needs a pick from a list and yes/no answers (D-04) |
| jev: per-request zero data retention flag | OPT-OUT | not needed yet: unverified on /v1/systemone; the ZDR check is a gate before a real client (CONTEXT Deferred Ideas) |
| jev: laravel/ai Classification client | OPT-OUT | explicitly out of scope: D-06 chose a plain HTTP driver; the SDK feature is experimental |
| jev: batched or asynchronous requests | OPT-OUT | not needed: one call per reply (D-04) |

## Reply models through laravel/ai (Anthropic, OpenAI)

| capability | decision | reason |
|---|---|---|
| llm: text generation with structured output | INTEGRATE | |
| llm: provider and model per call with a fallback chain | INTEGRATE | |
| llm: streaming responses | OPT-OUT | not needed: replies are short texts sent whole |
| llm: tools and function calling | OPT-OUT | explicitly out of scope: the reply model must have no tool that could send (D-01, D-02) |
| llm: SDK conversation memory | OPT-OUT | not needed: the thread comes from the messages table |
| llm: SDK queued prompts and broadcasting | OPT-OUT | not needed: ProcessInbound already runs on the queue |
| llm: image and file attachments to the model | OPT-OUT | not needed yet: pictures are described as text in the prompt |
| llm: embeddings and vector stores | OPT-OUT | explicitly out of scope: DEC-11 route, don't retrieve |
| llm: audio, transcription and image generation | OPT-OUT | explicitly out of scope: Voice is Backlog 999.2 |
