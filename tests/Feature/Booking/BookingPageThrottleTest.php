<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Livewire\BookingPage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Livewire\Livewire;

it('limits the page GET (D-11)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    foreach (range(1, 30) as $try) {
        $this->get(route('booking.show', $token))->assertOk();
    }

    $response = $this->get(route('booking.show', $token));

    $response->assertStatus(429);

    $html = $response->getContent();
    $xpath = bookingPageXpath($html);

    expect($xpath->query('//h1')->length)->toBe(1)
        ->and(trim($xpath->query('//h1')->item(0)->textContent))->toBe('Please wait a moment')
        ->and(bookingPageText($html))->toContain('There have been too many tries. Wait a minute, then try again.')
        ->and($xpath->query('//meta[@name="robots"]')->length)->toBe(1)
        ->and($xpath->query('//header')->length)->toBe(1)
        ->and($xpath->query('//header')->item(0)->textContent)->toContain('Example Heating & Air');
});

it('limits the taps inside the page (research Pitfall 1)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $other = Contact::factory()->create(['name' => 'Sam Rivera', 'phone_e164' => '+12025550188']);
    $otherConversation = Conversation::factory()->for($other)->create();
    Message::factory()->for($otherConversation)->create(['body' => 'My furnace is loud']);
    [, $otherToken] = bookingPageMint($otherConversation);

    $tapped = bookingPageChicago('2026-11-03 10:00')->toIso8601String();
    $page = Livewire::test(BookingPage::class, ['token' => $token]);

    foreach (range(1, 20) as $tap) {
        $page->call('choose', $tapped);
    }

    expect(bookingPageText($page->html()))->not->toContain('Please wait a moment');

    $page->call('choose', $tapped);

    expect(bookingPageText($page->html()))
        ->toContain('Please wait a moment')
        ->toContain('There have been too many tries. Wait a minute, then try again.');

    $page->call('book');

    expect(bookingPageText($page->html()))->toContain('Please wait a moment');

    $fresh = Livewire::test(BookingPage::class, ['token' => $otherToken])->call('choose', $tapped);

    expect(bookingPageText($fresh->html()))->not->toContain('Please wait a moment');
});
