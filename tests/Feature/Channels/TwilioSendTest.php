<?php

use App\Actions\SendOwnerReply;
use App\Channels\RecipientOptedOut;
use App\Channels\TwilioTextChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const TWILIO_SEND_TEST_ACCOUNT_SID = 'AC'.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const TWILIO_SEND_TEST_AUTH_TOKEN = 'secret-token-that-must-never-leak';
const TWILIO_SEND_TEST_SERVICE_SID = 'MG'.'dddddddddddddddddddddddddddddddd';
const TWILIO_SEND_TEST_STATUS_URL = 'https://ops.example.test/webhooks/text/twilio/status';
const TWILIO_SEND_TEST_MESSAGE_SID = 'SM'.'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

beforeEach(function () {
    config([
        'channels.text.driver' => 'twilio',
        'services.twilio.account_sid' => TWILIO_SEND_TEST_ACCOUNT_SID,
        'services.twilio.auth_token' => TWILIO_SEND_TEST_AUTH_TOKEN,
        'services.twilio.messaging_service_sid' => TWILIO_SEND_TEST_SERVICE_SID,
        'services.twilio.status_callback_url' => TWILIO_SEND_TEST_STATUS_URL,
    ]);
});

/**
 * An open conversation with a customer who has a phone number and one question waiting.
 */
function twilioSendConversation(): Conversation
{
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'Is Tuesday OK?']);

    return $conversation;
}

/**
 * The one message the owner's reply created.
 */
function twilioSendReply(): Message
{
    return Message::query()->where('direction', MessageDirection::Out)->sole();
}

it('posts the text to the Messages API through the Messaging Service and stores the returned sid', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => TWILIO_SEND_TEST_MESSAGE_SID, 'status' => 'accepted'], 201)]);

    app(SendOwnerReply::class)->handle(twilioSendConversation(), 'Tuesday at 9 works');

    $reply = twilioSendReply();

    expect($reply->status)->toBe(MessageStatus::Sent)
        ->and($reply->provider)->toBe('twilio')
        ->and($reply->provider_message_id)->toBe(TWILIO_SEND_TEST_MESSAGE_SID);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.TWILIO_SEND_TEST_ACCOUNT_SID.'/Messages.json'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(TWILIO_SEND_TEST_ACCOUNT_SID.':'.TWILIO_SEND_TEST_AUTH_TOKEN))
            && $request->isForm()
            && $request->data() === [
                'To' => '+12025550123',
                'MessagingServiceSid' => TWILIO_SEND_TEST_SERVICE_SID,
                'Body' => 'Tuesday at 9 works',
                'StatusCallback' => TWILIO_SEND_TEST_STATUS_URL,
            ];
    });
});

it('leaves StatusCallback out when no status callback URL is configured', function () {
    config(['services.twilio.status_callback_url' => null]);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => TWILIO_SEND_TEST_MESSAGE_SID], 201)]);

    $id = app(TwilioTextChannel::class)->send('+12025550123', 'On our way');

    expect($id)->toBe(TWILIO_SEND_TEST_MESSAGE_SID);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('StatusCallback', $request->data()));
});

it('throws RecipientOptedOut when Twilio refuses with HTTP 400 and code 21610', function () {
    Http::fake(['api.twilio.com/*' => Http::response([
        'code' => 21610,
        'message' => 'Attempt to send to unsubscribed recipient',
        'more_info' => 'https://www.twilio.com/docs/errors/21610',
        'status' => 400,
    ], 400)]);

    expect(fn () => app(TwilioTextChannel::class)->send('+12025550123', 'On our way'))
        ->toThrow(RecipientOptedOut::class);
});

it('refuses the owner reply and marks the customer opted out when Twilio answers 21610', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21610, 'message' => 'Attempt to send to unsubscribed recipient'], 400)]);
    $conversation = twilioSendConversation();

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works'))
        ->toThrow(DomainException::class, 'The customer has opted out of texts.');

    expect(twilioSendReply()->status)->toBe(MessageStatus::Failed)
        ->and($conversation->contact->refresh()->isOptedOut())->toBeTrue();
});

it('treats a 400 with any other code as an ordinary failure, not an opt-out', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => 'Invalid To number'], 400)]);

    expect(fn () => app(TwilioTextChannel::class)->send('+12025550123', 'On our way'))
        ->toThrow(RuntimeException::class, 'Twilio send failed (HTTP 400, code 21211)');
});

it('stores a 500 as failed with an error that names the status and never contains the auth token', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 20500, 'message' => 'Internal Server Error'], 500)]);
    $conversation = twilioSendConversation();

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works'))
        ->toThrow(DomainException::class, 'HTTP 500');

    $reply = twilioSendReply();

    expect($reply->status)->toBe(MessageStatus::Failed)
        ->and($reply->provider_message_id)->toBeNull()
        ->and($reply->meta['error'])->toContain('HTTP 500')
        ->and($reply->meta['error'])->not->toContain(TWILIO_SEND_TEST_AUTH_TOKEN)
        ->and($conversation->contact->refresh()->isOptedOut())->toBeFalse();
});

it('stores a connection failure as failed with an error that never contains the auth token', function () {
    Http::fake(['api.twilio.com/*' => Http::failedConnection()]);
    $conversation = twilioSendConversation();

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works'))
        ->toThrow(DomainException::class, 'Twilio send failed');

    $reply = twilioSendReply();

    expect($reply->status)->toBe(MessageStatus::Failed)
        ->and($reply->meta['error'])->toContain('Twilio send failed')
        ->and($reply->meta['error'])->not->toContain(TWILIO_SEND_TEST_AUTH_TOKEN)
        ->and($reply->meta['error'])->not->toContain(TWILIO_SEND_TEST_ACCOUNT_SID);
});

it('fails a 2xx answer that carries no sid instead of recording a message with no id', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['status' => 'accepted'], 201)]);

    expect(fn () => app(TwilioTextChannel::class)->send('+12025550123', 'On our way'))
        ->toThrow(RuntimeException::class, 'Twilio send failed');
});

it('fails with "Twilio is not configured" and sends nothing when a setting is missing', function (string $setting) {
    config(["services.twilio.{$setting}" => '']);
    Http::fake();
    $conversation = twilioSendConversation();

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Tuesday at 9 works'))
        ->toThrow(DomainException::class, 'Twilio is not configured');

    Http::assertNothingSent();

    expect(twilioSendReply()->status)->toBe(MessageStatus::Failed);
})->with(['account_sid', 'auth_token', 'messaging_service_sid']);
