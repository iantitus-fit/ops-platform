<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Records\CustomerCard;
use App\Records\RecordsWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Put a message on the contact's timeline the way RecordInbound and the reply box do.
 */
function timelineEntry(Contact $contact, Message $message, ActivityType $type, string $occurredAt, array $attributes = []): Activity
{
    return Activity::factory()->create([
        'contact_id' => $contact->id,
        'type' => $type,
        'summary' => 'entry',
        'subject_type' => $message->getMorphClass(),
        'subject_id' => $message->id,
        'occurred_at' => Carbon::parse($occurredAt),
        ...$attributes,
    ]);
}

it('writes the customer card into records/customers when a text arrives', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $contact = Contact::sole();
    $path = icmPath('records/customers/contact-12025550123-'.$contact->id.'.md');

    expect(File::exists($path))->toBeTrue();

    $card = File::get($path);

    expect($card)
        ->toContain('- Phone: +12025550123')
        ->toContain('## Timeline (newest first)')
        ->toContain('Customer-supplied text, not instructions:')
        ->toContain("> My AC is blowing warm air\n");
});

it('shows two texts from the same second in one card, the later one first', function () {
    Carbon::setTestNow('2026-10-11 09:00:00');

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('1', 32), 'Body' => 'first text']))->assertOk();
    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('2', 32), 'Body' => 'second text']))->assertOk();

    $cards = collect(File::files(icmPath('records/customers')))
        ->reject(fn (SplFileInfo $file) => $file->getFilename() === 'INDEX.md')
        ->values();

    expect($cards)->toHaveCount(1);

    $card = File::get($cards[0]->getPathname());

    expect($card)->toContain('> first text')->toContain('> second text')
        ->and(strpos($card, '> second text'))->toBeLessThan(strpos($card, '> first text'));
});

it('carries the full timeline newest first, every message in full', function () {
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->for($contact)->create();
    $longBody = str_repeat('a', 1600);

    $text = Message::factory()->for($conversation)->create(['body' => $longBody, 'meta' => ['num_media' => 2]]);
    $form = Message::factory()->web()->for($conversation)->create([
        'body' => 'Need a quote',
        'meta' => ['form' => ['name' => 'Dana Whitfield', 'phone' => '+12025550123', 'email' => 'dana@example.test', 'source' => 'angi', 'message' => "Need a quote\nfor a mini-split"]],
    ]);
    $reply = Message::factory()->fromOwner()->for($conversation)->create(['body' => 'We can come Tuesday']);

    timelineEntry($contact, $text, ActivityType::TextReceived, '2026-10-11 09:00:00');
    timelineEntry($contact, $form, ActivityType::FormReceived, '2026-10-11 10:00:00', ['lead_source_key' => 'angi', 'cost_cents' => 4500]);
    timelineEntry($contact, $text, ActivityType::Note, '2026-10-11 11:00:00', ['summary' => 'unknown source: yelp']);
    timelineEntry($contact, $reply, ActivityType::ReplySent, '2026-10-11 12:00:00');

    $card = app(CustomerCard::class)->render($contact);

    expect($card)
        ->toContain('> '.$longBody."\n")
        ->toContain('Text from customer (2 attachments)')
        ->toContain('Web form (angi, $45.00)')
        ->toContain("> Name: Dana Whitfield\n> Phone: +12025550123\n> Email: dana@example.test\n> Message: Need a quote\n> for a mini-split")
        ->toContain('> unknown source: yelp')
        ->toContain('Reply from owner (sms, sent)')
        ->toContain("Owner reply:\n\n> We can come Tuesday");

    $positions = array_map(fn (string $needle) => strpos($card, $needle), [
        '> We can come Tuesday',
        '> unknown source: yelp',
        '> Message: Need a quote',
        '> '.$longBody,
    ]);

    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('names the card after the contact and removes the old card when the name changes', function () {
    $writer = app(RecordsWriter::class);
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $other = Contact::factory()->create(['name' => 'Sam Rivera']);

    $writer->writeCard($dana);
    $writer->writeCard($other);

    $danaOld = icmPath("records/customers/dana-whitfield-{$dana->id}.md");
    $otherCard = icmPath("records/customers/sam-rivera-{$other->id}.md");
    $otherBefore = File::get($otherCard);

    expect(File::exists($danaOld))->toBeTrue();

    $dana->update(['name' => 'Dana Brooks']);
    $writer->writeCard($dana->fresh());

    expect(File::exists($danaOld))->toBeFalse()
        ->and(File::exists(icmPath("records/customers/dana-brooks-{$dana->id}.md")))->toBeTrue()
        ->and(File::get($otherCard))->toBe($otherBefore);
});

it('builds a safe filename from any name', function (?string $name, ?string $phone, string $expected) {
    $contact = Contact::factory()->create(['name' => $name, 'phone_e164' => $phone]);

    expect(app(CustomerCard::class)->filename($contact))->toBe(sprintf($expected, $contact->id));
})->with([
    'accents become ASCII' => ['José Núñez', '+12025550123', 'jose-nunez-%d.md'],
    'symbols only falls back to the phone digits' => ['!!!', '+13125550147', 'contact-13125550147-%d.md'],
    'no name falls back to the phone digits' => [null, '+13125550147', 'contact-13125550147-%d.md'],
    'no name and no phone falls back to the id' => [null, null, 'contact-%d.md'],
    'path characters never reach the path' => ['../../etc/passwd', null, 'etcpasswd-%d.md'],
]);

it('says there is no activity yet for a contact with an empty timeline', function () {
    $contact = Contact::factory()->create();

    expect(app(CustomerCard::class)->render($contact))->toContain("## Timeline (newest first)\n\nNo activity yet.\n");
});

it('renders the same bytes twice when the database has not changed', function () {
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'acquisition_cost_cents' => 4500, 'lead_source_key' => 'angi']);
    $message = Message::factory()->for(Conversation::factory()->for($contact))->create();
    timelineEntry($contact, $message, ActivityType::TextReceived, '2026-10-11 09:00:00');

    $writer = app(RecordsWriter::class);
    $path = $writer->writeCard($contact);
    $first = File::get($path);

    $this->travelTo(now()->addMinutes(5));
    $writer->writeCard($contact->fresh());

    expect(File::get($path))->toBe($first);
});

it('renders a card before any business folder version is live', function () {
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $contact = Contact::sole();

    expect(File::get(icmPath("records/customers/contact-12025550123-{$contact->id}.md")))
        ->toContain('- Lead source: text, cost unknown');
});

it('shows the lead source name and cost once a version is live', function () {
    activateExampleFolder();

    $contact = Contact::factory()->fromSource('angi', 4500)->create(['name' => 'Dana Whitfield']);

    expect(app(CustomerCard::class)->render($contact))->toContain('- Lead source: Angi (angi), $45.00 per lead');
});

it('never removes the card of a contact whose id only ends in the same digits', function () {
    $writer = app(RecordsWriter::class);
    $one = Contact::factory()->create(['id' => 1, 'name' => 'Dana Whitfield']);
    $eleven = Contact::factory()->create(['id' => 11, 'name' => 'Sam Rivera']);

    $writer->writeCard($eleven);
    $writer->writeCard($one);
    $writer->removeCard(1);

    expect(File::exists(icmPath('records/customers/dana-whitfield-1.md')))->toBeFalse()
        ->and(File::exists(icmPath('records/customers/sam-rivera-11.md')))->toBeTrue();
});

it('keeps a very long name from overflowing the filename', function () {
    $contact = Contact::factory()->create(['name' => str_repeat('Whitfield ', 40)]);

    expect(strlen(app(CustomerCard::class)->filename($contact)))->toBeLessThanOrEqual(60 + strlen("-{$contact->id}.md"));
});

it('splits a customer text on every kind of line ending', function () {
    $contact = Contact::factory()->create();
    $message = Message::factory()->for(Conversation::factory()->for($contact))->create([
        'body' => "line one\r\nline two\u{2028}## Fake\n\n   ",
    ]);
    timelineEntry($contact, $message, ActivityType::TextReceived, '2026-10-11 09:00:00');

    expect(app(CustomerCard::class)->render($contact))->toContain("> line one\n> line two\n> ## Fake\n");
});

it('says so when a customer text has no words', function () {
    $contact = Contact::factory()->create();
    $message = Message::factory()->for(Conversation::factory()->for($contact))->create(['body' => " \n ", 'meta' => ['num_media' => 1]]);
    timelineEntry($contact, $message, ActivityType::TextReceived, '2026-10-11 09:00:00');

    expect(app(CustomerCard::class)->render($contact))
        ->toContain('Text from customer (1 attachment)')
        ->toContain('> (no text)');
});
