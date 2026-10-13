<?php

use App\Enums\ContactStatus;
use App\Enums\DraftResolution;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Records\RecordsWriter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;

/**
 * The table rows of a summary file, header and separator left out.
 *
 * @return list<string>
 */
function tableRows(string $path): array
{
    $rows = array_values(array_filter(
        explode("\n", File::get($path)),
        fn (string $line) => str_starts_with($line, '|'),
    ));

    return array_slice($rows, 2);
}

/**
 * A lead with an open conversation holding the given messages, oldest first.
 *
 * @param  array<string, mixed>  $contact
 * @param  list<array{string, string, string}>  $messages  [direction: in|out|owner, created_at, body]
 */
function openLead(array $contact, array $messages): Contact
{
    $contact = Contact::factory()->create($contact);
    $conversation = Conversation::factory()->for($contact)->create();

    foreach ($messages as [$kind, $createdAt, $body]) {
        $factory = Message::factory()->for($conversation);
        $factory = $kind === 'in' ? $factory : $factory->fromOwner();

        $factory->create(['body' => $body, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
    }

    return $contact;
}

it('writes empty summaries when there are no contacts', function () {
    app(RecordsWriter::class)->writeSummaries();

    expect(File::get(icmPath('records/customers/INDEX.md')))
        ->toContain('# Customers')
        ->toContain('Generated from the database. Read-only: edits here are overwritten by the next render.')
        ->toContain('No customers yet.')
        ->and(File::get(icmPath('records/open-leads.md')))
        ->toContain('# Open leads')
        ->toContain('Generated from the database. Read-only: edits here are overwritten by the next render.')
        ->toContain('No open leads.');
});

it('lists every customer in INDEX.md by lowercased name then id', function () {
    $sam = Contact::factory()->create(['name' => 'sam Rivera', 'phone_e164' => '+12025550101']);
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550102']);
    $nameless = Contact::factory()->create(['name' => null, 'phone_e164' => '+12025550103']);
    $pipe = Contact::factory()->create(['name' => 'Dana | x', 'phone_e164' => null]);
    $secondSam = Contact::factory()->create(['name' => 'Sam Rivera', 'phone_e164' => '+12025550104']);

    app(RecordsWriter::class)->writeSummaries();

    $lines = explode("\n", File::get(icmPath('records/customers/INDEX.md')));

    expect($lines)->toContain('| Name | Phone | File |');

    expect(tableRows(icmPath('records/customers/INDEX.md')))->toBe([
        "| (no name) | +12025550103 | [contact-12025550103-{$nameless->id}.md](contact-12025550103-{$nameless->id}.md) |",
        "| Dana Whitfield | +12025550102 | [dana-whitfield-{$dana->id}.md](dana-whitfield-{$dana->id}.md) |",
        "| Dana \\| x | none | [dana-x-{$pipe->id}.md](dana-x-{$pipe->id}.md) |",
        "| sam Rivera | +12025550101 | [sam-rivera-{$sam->id}.md](sam-rivera-{$sam->id}.md) |",
        "| Sam Rivera | +12025550104 | [sam-rivera-{$secondSam->id}.md](sam-rivera-{$secondSam->id}.md) |",
    ]);

    foreach (tableRows(icmPath('records/customers/INDEX.md')) as $row) {
        expect(preg_split('/(?<!\\\\)\|/', $row))->toHaveCount(5);
    }
});

it('lists only leads with an open conversation in open-leads.md, newest activity first', function () {
    activateExampleFolder();

    openLead(
        ['name' => 'Ann Lee', 'last_activity_at' => '2026-10-11 15:00:00', 'lead_source_key' => 'angi'],
        [['in', '2026-10-11 14:00:00', 'Hello'], ['in', '2026-10-11 14:30:00', "Is anyone there? | urgent\nplease"]],
    );
    openLead(
        ['name' => 'Bea Adams', 'last_activity_at' => '2026-10-11 16:00:00', 'lead_source_key' => 'thumbtack'],
        [['in', '2026-10-11 13:00:00', 'Need a tune-up'], ['owner', '2026-10-11 13:30:00', 'We can come Tuesday']],
    );
    openLead(
        ['name' => 'Cy Park', 'last_activity_at' => '2026-10-11 11:00:00', 'lead_source_key' => 'referral'],
        [['in', '2026-10-11 10:00:00', 'First'], ['owner', '2026-10-11 10:30:00', 'Reply'], ['in', '2026-10-11 11:00:00', str_repeat('long ', 40)]],
    );
    openLead(
        ['name' => null, 'phone_e164' => '+12025550177', 'last_activity_at' => '2026-10-11 15:00:00', 'lead_source_key' => 'text'],
        [['in', '2026-10-11 14:45:00', 'Also me']],
    );
    $customer = openLead(['name' => 'Dee Customer', 'last_activity_at' => '2026-10-11 17:00:00'], [['in', '2026-10-11 09:00:00', 'Hi']]);
    $customer->update(['status' => ContactStatus::Customer]);
    $closed = Contact::factory()->create(['name' => 'Eve Closed', 'last_activity_at' => '2026-10-11 18:00:00']);
    Conversation::factory()->closed()->for($closed)->create();

    app(RecordsWriter::class)->writeSummaries();

    $lines = explode("\n", File::get(icmPath('records/open-leads.md')));
    $rows = tableRows(icmPath('records/open-leads.md'));

    expect($lines)->toContain('| Name | Source | Waiting since | Last message |')
        ->and($rows)->toBe([
            '| Bea Adams | thumbtack | replied 2026-10-11 08:30 | Owner: We can come Tuesday |',
            '| +12025550177 | text | 2026-10-11 09:45 | Also me |',
            '| Ann Lee | angi | 2026-10-11 09:00 | Is anyone there? \\| urgent please |',
            '| Cy Park | referral | 2026-10-11 06:00 | '.rtrim(str_repeat('long ', 16)).'… |',
        ]);
});

it('reports a draft the owner sent later as the last reply, at its send time', function () {
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'last_activity_at' => '2026-10-11 14:10:00', 'lead_source_key' => 'angi']);
    $conversation = Conversation::factory()->for($contact)->create();

    Message::factory()->for($conversation)->create(['body' => 'How much for a new unit?', 'created_at' => '2026-10-11 14:00:00', 'updated_at' => '2026-10-11 14:00:00']);
    Message::factory()->for($conversation)->outbound()->create([
        'kind' => MessageKind::HoldingReply,
        'body' => 'Mike will get back to you.',
        'created_at' => '2026-10-11 14:00:01',
        'sent_at' => '2026-10-11 14:00:01',
    ]);
    Message::factory()->for($conversation)->outbound()->create([
        'kind' => MessageKind::AgentReply,
        'resolution' => DraftResolution::Approved,
        'body' => 'Mike can come Tuesday.',
        'created_at' => '2026-10-11 14:00:00',
        'sent_at' => '2026-10-11 14:10:00',
    ]);

    app(RecordsWriter::class)->writeSummaries();

    expect(tableRows(icmPath('records/open-leads.md')))->toBe([
        '| Dana Whitfield | angi | replied 2026-10-11 09:10 | Mike can come Tuesday. |',
    ]);
});

it('ignores a held draft, a withdrawn draft and a failed text when reading who spoke last', function () {
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'last_activity_at' => '2026-10-11 14:10:00', 'lead_source_key' => 'angi']);
    $conversation = Conversation::factory()->for($contact)->create();

    Message::factory()->for($conversation)->create(['body' => 'How much for a new unit?', 'created_at' => '2026-10-11 14:00:00', 'updated_at' => '2026-10-11 14:00:00']);
    Message::factory()->for($conversation)->withdrawn()->create(['body' => 'About $9,000.', 'created_at' => '2026-10-11 14:05:00', 'updated_at' => '2026-10-11 14:05:00']);
    Message::factory()->for($conversation)->draft()->create(['body' => 'About $8,500, installed.', 'created_at' => '2026-10-11 14:06:00', 'updated_at' => '2026-10-11 14:06:00']);
    Message::factory()->for($conversation)->outbound()->create([
        'status' => MessageStatus::Failed,
        'body' => 'Mike will be in touch.',
        'created_at' => '2026-10-11 14:07:00',
        'updated_at' => '2026-10-11 14:07:00',
    ]);

    app(RecordsWriter::class)->writeSummaries();

    expect(tableRows(icmPath('records/open-leads.md')))->toBe([
        '| Dana Whitfield | angi | 2026-10-11 09:00 | How much for a new unit? |',
    ]);
});

it('updates INDEX.md and open-leads.md in the same render as the card', function () {
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $contact = Contact::sole();

    expect(File::exists(icmPath('records/customers/INDEX.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/open-leads.md')))->toBeTrue()
        ->and(File::get(icmPath('records/customers/INDEX.md')))->toContain("[contact-12025550123-{$contact->id}.md]")
        ->and(File::get(icmPath('records/open-leads.md')))->toContain('| +12025550123 | text |')->toContain('| My AC is blowing warm air |');
});

it('writes the summaries only while it holds the records lock, and reads the database inside it', function () {
    Contact::factory()->create();
    $lock = Cache::lock('records:summaries', 30);

    expect($lock->get())->toBeTrue();

    Sleep::fake(syncWithCarbon: true);
    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(fn () => app(RecordsWriter::class)->writeSummaries())->toThrow(LockTimeoutException::class)
        ->and(collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'contacts')))->toBeEmpty()
        ->and(File::exists(icmPath('records/open-leads.md')))->toBeFalse();

    $lock->release();
    app(RecordsWriter::class)->writeSummaries();

    expect(File::exists(icmPath('records/open-leads.md')))->toBeTrue();
});
