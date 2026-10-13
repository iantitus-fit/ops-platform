<?php

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Channels\TwilioTextChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('resolves the fake channel when the driver is fake', function () {
    config(['channels.text.driver' => 'fake']);

    expect(app(TextChannel::class))->toBeInstanceOf(FakeTextChannel::class);
});

it('resolves the Twilio channel when the driver is twilio', function () {
    config(['channels.text.driver' => 'twilio']);

    expect(app(TextChannel::class))->toBeInstanceOf(TwilioTextChannel::class);
});

it('refuses an unknown driver and names it', function () {
    config(['channels.text.driver' => 'carrier-pigeon']);

    expect(fn () => app(TextChannel::class))
        ->toThrow(InvalidArgumentException::class, 'Unknown text channel driver [carrier-pigeon]');
});

it('refuses to send through Twilio until it is configured, and sends nothing', function () {
    config([
        'services.twilio.account_sid' => null,
        'services.twilio.auth_token' => null,
        'services.twilio.messaging_service_sid' => null,
    ]);
    Http::fake();

    $channel = new TwilioTextChannel;

    expect($channel->name())->toBe('twilio')
        ->and(fn () => $channel->send('+12025550123', 'On our way'))
        ->toThrow(RuntimeException::class, 'Twilio is not configured');

    Http::assertNothingSent();
});

it('reads a Twilio webhook into the same inbound text the fake channel produces', function () {
    $request = Request::create('/webhooks/text/twilio', 'POST', [
        'MessageSid' => 'SM'.str_repeat('a', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'STOP',
        'NumMedia' => '2',
        'OptOutType' => 'stop',
    ]);

    $text = (new TwilioTextChannel)->parseInbound($request);

    expect($text->provider)->toBe('twilio')
        ->and($text->providerMessageId)->toBe('SM'.str_repeat('a', 32))
        ->and($text->from)->toBe('+12025550123')
        ->and($text->to)->toBe('+13125550147')
        ->and($text->body)->toBe('STOP')
        ->and($text->numMedia)->toBe(2)
        ->and($text->optOutType)->toBe('STOP');
});

it('reads the older SmsSid when Twilio sends no MessageSid, and leaves OptOutType empty when none arrives', function () {
    $request = Request::create('/webhooks/text/twilio', 'POST', [
        'SmsSid' => 'SM'.str_repeat('f', 32),
        'From' => '+12025550123',
        'Body' => 'My AC is blowing warm air',
    ]);

    $text = (new TwilioTextChannel)->parseInbound($request);

    expect($text->providerMessageId)->toBe('SM'.str_repeat('f', 32))
        ->and($text->to)->toBeNull()
        ->and($text->numMedia)->toBe(0)
        ->and($text->optOutType)->toBeNull();
});

it('sends through the fake channel and remembers what it sent', function () {
    $channel = new FakeTextChannel;

    $id = $channel->send('+12025550123', 'On our way');

    expect($id)->toStartWith('fake-')
        ->and($channel->sent())->toBe([
            ['to' => '+12025550123', 'body' => 'On our way', 'provider_message_id' => $id],
        ]);
});

it('logs a fake send without the message body outside the local environment', function () {
    Log::spy();

    $id = (new FakeTextChannel)->send('+12025550123', 'On our way');

    Log::shouldHaveReceived('info')->once()->with('Fake text sent', [
        'to' => '+12025550123',
        'provider_message_id' => $id,
        'length' => 10,
    ]);
});

it('logs the message body in the local environment', function () {
    $this->app['env'] = 'local';
    Log::spy();

    $id = (new FakeTextChannel)->send('+12025550123', 'On our way');

    Log::shouldHaveReceived('info')->once()->with('Fake text sent', [
        'to' => '+12025550123',
        'provider_message_id' => $id,
        'length' => 10,
        'body' => 'On our way',
    ]);
});
