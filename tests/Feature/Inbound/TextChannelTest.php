<?php

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Channels\TwilioTextChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

const TWILIO_NOT_BUILT_MESSAGE = 'The Twilio text channel is built in Phase 2 (TXT-01). Set TEXT_CHANNEL=fake.';

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

it('refuses to send through Twilio until Phase 2', function () {
    $channel = new TwilioTextChannel;

    expect($channel->name())->toBe('twilio')
        ->and(fn () => $channel->send('+12025550123', 'On our way'))
        ->toThrow(LogicException::class, TWILIO_NOT_BUILT_MESSAGE);
});

it('refuses to read a Twilio webhook until Phase 2', function () {
    $channel = new TwilioTextChannel;

    expect(fn () => $channel->parseInbound(Request::create('/webhooks/text/twilio', 'POST')))
        ->toThrow(LogicException::class, TWILIO_NOT_BUILT_MESSAGE);
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
