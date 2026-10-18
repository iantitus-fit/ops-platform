# Writing price sentences

A price sentence is words you write yourself, in a service file, that the system sends to a customer on its own. Nobody reads it first, so the sync checks it hard before it can go live. These are the rules, in plain words.

## Put it in the service file

In the front matter of the service file, add `price_sentences:` with one entry for each sentence. Each entry has a `sentence`, and the amounts in it as whole cents in `amounts_cents` ($89 is `8900`, $2,000 is `200000`).

A service with one price that applies to everyone, like the $89 diagnostic visit, needs only the sentence. The amount is checked against the service's `price_cents`.

## Say the service's name every time

Every sentence has to contain the service's name exactly as it is in the file: "AC repair starts with...", "a replacement system typically runs...". If the system picks the wrong service, the customer sees the wrong name and can say so. The sync refuses a sentence without the name.

## Write every amount as $ and digits

Write "$5,000", never "5k", "five thousand" or "5 grand". The sync compares each $ amount in the sentence with the amounts you listed, and it can only read dollars written with a $ sign and digits. Every sentence needs at least one amount, and the amounts in the sentence and in `amounts_cents` have to match exactly, in both directions.

## Sound like an estimate, never a promise

Use wording such as "typically runs", "starts with" or "usually". Never write "guaranteed", "for sure" or "definitely": a price sentence goes out without you, so it must not read as a promise. The sync refuses those, and anything your voice file says never to say.

You can say "we take the $89 off" or that a fee is "waived". Those are your own terms and they pass.

## Key a sentence to an answer, and name the answer in it

If the price depends on what the customer tells us, add `answers:` to the entry, with an intake question and one of its options exactly as written in the file:

```
price_sentences:
  - answers:
      unit_size: "Large (3 tons and up)"
    sentence: "For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed."
    amounts_cents: [500000, 1000000]
```

Say the option in the sentence ("For a larger system, 3 tons and up, ..."), so if the system matches the wrong option the customer notices. Give each option your own meaning in the file ("Small (under 3 tons)"), so a customer who says "about 3 tons" is matched the first time.

Keyed sentences must all use the same questions, and no two can have the same answers. A sentence with no `answers` has to be the only one for that service.

## Write a default for "I'm not sure"

Add `price_default:` with a `sentence` and `amounts_cents` for a customer who cannot or will not answer. Open by putting them at ease ("That's fine if you're not sure what you have."), then give the ranges. The default needs keyed sentences beside it, and it names the service like any other sentence.

## A service you always quote yourself

If a service has `price_policy: none`, it has no price sentences. You quote it, every time. The sync refuses a sentence on a service like that.
