<?php

require_once __DIR__.'/BookingPageHelpers.php';

use App\Livewire\BookingPage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

/**
 * The limiter key BookingPage keeps for a token and an address (127.0.0.1 in a test).
 */
function bookingPageThrottleKey(string $token): string
{
    return 'booking:'.hash('sha256', $token).':127.0.0.1';
}

/**
 * Whether the element the query finds carries what puts keyboard focus on it when it appears.
 */
function bookingPageTakesFocus(string $html, string $query): bool
{
    $node = bookingPageXpath($html)->query($query)->item(0);

    return $node !== null
        && $node->getAttribute('tabindex') === '-1'
        && $node->getAttribute('x-init') === '$el.focus()';
}

it('offers Try again while limited and brings the times back after the minute (IN-05)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $tapped = bookingPageChicago('2026-11-03 10:00')->toIso8601String();
    $key = bookingPageThrottleKey($token);
    $page = Livewire::test(BookingPage::class, ['token' => $token]);

    foreach (range(1, 20) as $tap) {
        $page->call('choose', $tapped);
    }

    $page->call('back')->call('choose', $tapped);

    $xpath = bookingPageXpath($page->html());

    expect(bookingPageText($page->html()))->toContain('Please wait a moment')
        ->and($xpath->query('//button[normalize-space()="Try again"]')->length)->toBe(1)
        ->and(bookingPageTakesFocus($page->html(), '//h1[normalize-space()="Please wait a moment"]'))->toBeTrue();

    $attempts = RateLimiter::attempts($key);

    $page->call('tryAgain');

    expect(bookingPageText($page->html()))->toContain('Please wait a moment')
        ->and(RateLimiter::attempts($key))->toBe($attempts);

    $this->travel(61)->seconds();

    $page->call('tryAgain');

    expect(bookingPageText($page->html()))
        ->toContain('Pick a time that works for you.')
        ->not->toContain('Please wait a moment')
        ->and($page->get('rateLimited'))->toBeFalse()
        ->and(RateLimiter::attempts($key))->toBe(1)
        ->and(bookingPageTakesFocus($page->html(), '//h1[normalize-space()="Book AC repair"]'))->toBeTrue();
});

it('keeps Try again from letting a limited address tap (T-08-89)', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    $page = Livewire::test(BookingPage::class, ['token' => $token]);

    foreach (range(1, 21) as $tap) {
        $page->call('choose', bookingPageChicago('2026-11-03 10:00')->toIso8601String());
    }

    foreach (range(1, 5) as $press) {
        $page->call('tryAgain');
    }

    expect($page->get('rateLimited'))->toBeTrue()
        ->and(RateLimiter::attempts(bookingPageThrottleKey($token)))->toBe(20)
        ->and(fn () => $page->set('retried', true))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps the 429 page as it was', function () {
    [, $conversation] = bookingPageSetUp();
    [, $token] = bookingPageMint($conversation);

    foreach (range(1, 30) as $try) {
        $this->get(route('booking.show', $token))->assertOk();
    }

    $html = $this->get(route('booking.show', $token))->assertStatus(429)->getContent();
    $xpath = bookingPageXpath($html);

    expect(bookingPageText($html))->not->toContain('Try again')
        ->and($xpath->query('//button')->length)->toBe(0)
        ->and($xpath->query('//h1')->item(0)->hasAttribute('tabindex'))->toBeFalse();
});
