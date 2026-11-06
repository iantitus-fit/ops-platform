<?php

use App\Models\BookingLink;
use App\Models\Message;
use Carbon\CarbonImmutable;

/**
 * Dana asks to book AC repair on Tuesday 2026-11-03 at 11:00 in the shop's zone, and the front desk's scripted reply is
 * labeled scheduling for ac-repair. The queued job runs, so the reply is sent or held by the real send path.
 *
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the scheduling defaults.
 */
function bookingLinkRunsScheduling(array $reply = [], string $text = 'Can someone come look at my AC this week?'): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    frontDeskCustomerTexts($text);
    scriptFrontDeskReplies([...[
        'reply' => 'We can get a tech out this week.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply]);
    runQueuedFrontDeskJobs();
}

/**
 * The token in the one booking address the text carries, or null when it carries none.
 */
function bookingLinkTokenIn(string $body): ?string
{
    return preg_match('~/book/([0-9a-f]{64})~', $body, $match) === 1 ? $match[1] : null;
}

it('sends the scheduling reply with the owner\'s link line (D-01)', function () {
    bookingLinkRunsScheduling();

    $token = bookingLinkTokenIn(textsSentToCustomers()[0]['body'] ?? '');

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($token)->not->toBeNull()
        ->and(BookingLink::query()->count())->toBe(1);

    $link = BookingLink::sole();
    $sent = Message::query()->where('meta->booking_link_id', $link->id)->sole();

    expect($token)->not->toBeNull()
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('We can get a tech out this week. Pick a time that works for you here: '.route('booking.show', $token)))
        ->and($link->service_slug)->toBe('ac-repair')
        ->and($link->conversation_id)->toBe($sent->conversation_id)
        ->and($link->message_id)->toBe($sent->id)
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($sent->meta['parts'])->toBe(['model_words', 'booking_link']);
});

it('opens the page from the link in the text', function () {
    bookingLinkRunsScheduling();

    $token = bookingLinkTokenIn(textsSentToCustomers()[0]['body'] ?? '');

    expect($token)->not->toBeNull();

    $this->get(route('booking.show', $token))
        ->assertOk()
        ->assertSee('Book AC repair');
});
