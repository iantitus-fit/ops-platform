<?php

use App\Jobs\ProcessInbound;
use App\Jobs\RenderCustomerCard;
use App\Models\Contact;
use App\Records\RecordsWriter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

it('queues one render when two events arrive before the job starts', function () {
    Queue::fake();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('1', 32), 'Body' => 'first']))->assertOk();
    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('2', 32), 'Body' => 'second']))->assertOk();

    $contact = Contact::sole();

    Queue::assertCount(3);
    Queue::assertPushed(ProcessInbound::class, 2);
    Queue::assertPushed(RenderCustomerCard::class, 1);
    Queue::assertPushed(RenderCustomerCard::class, fn (RenderCustomerCard $job) => $job->contactId === $contact->id);
});

it('queues a render for each customer', function () {
    Queue::fake();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('1', 32)]))->assertOk();
    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('2', 32), 'From' => '+13125550148']))->assertOk();

    Queue::assertCount(4);
    Queue::assertPushed(ProcessInbound::class, 2);
    Queue::assertPushed(RenderCustomerCard::class, 2);
});

it('is unique per contact until it starts, for at most five minutes', function () {
    $job = new RenderCustomerCard(17);
    $unique = (new ReflectionClass($job))->getAttributes(UniqueFor::class);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe('17')
        ->and($unique)->toHaveCount(1)
        ->and($unique[0]->newInstance()->uniqueFor)->toBe(300);
});

it('never overlaps another render of the same contact', function () {
    $middleware = (new RenderCustomerCard(17))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('17')
        ->and($middleware[0]->releaseAfter)->toBe(5)
        ->and($middleware[0]->expiresAfter)->toBe(60);
});

it('keeps retrying for ten minutes because every release uses an attempt', function () {
    $this->freezeTime();

    expect((new RenderCustomerCard(17))->retryUntil()->getTimestamp() - now()->getTimestamp())->toBe(600);
});

it('gives up after three real failures and waits longer between retries', function () {
    $job = new RenderCustomerCard(17);

    expect($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([5, 30, 120]);
});

it('removes the card files of a contact that no longer exists', function () {
    $writer = app(RecordsWriter::class);
    $gone = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $kept = Contact::factory()->create(['name' => 'Sam Rivera']);

    $writer->writeCard($gone);
    $writer->writeCard($kept);

    $goneId = $gone->id;
    $gone->delete();

    app()->call([new RenderCustomerCard($goneId), 'handle']);

    expect(File::exists(icmPath("records/customers/dana-whitfield-{$goneId}.md")))->toBeFalse()
        ->and(File::exists(icmPath("records/customers/sam-rivera-{$kept->id}.md")))->toBeTrue();
});

it('writes the card from the database as it is when the job runs', function () {
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $job = new RenderCustomerCard($contact->id);

    $contact->update(['name' => 'Dana Brooks']);

    app()->call([$job, 'handle']);

    expect(File::exists(icmPath("records/customers/dana-brooks-{$contact->id}.md")))->toBeTrue()
        ->and(File::exists(icmPath("records/customers/dana-whitfield-{$contact->id}.md")))->toBeFalse();
});
