<?php

use App\Enums\ActivityType;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Testing\TestResponse;

const TWILIO_STATUS_TEST_URL = 'https://ops.example.test/webhooks/text/twilio/status';
const TWILIO_STATUS_TEST_TOKEN = 'status-test-token';
const TWILIO_STATUS_TEST_SID = 'SM'.'1111111111111111111111111111111a';

beforeEach(function () {
    config([
        'channels.text.driver' => 'twilio',
        'services.twilio.auth_token' => TWILIO_STATUS_TEST_TOKEN,
        'services.twilio.status_callback_url' => TWILIO_STATUS_TEST_URL,
    ]);
});

/**
 * A text we sent to a customer, in the given state, carrying Twilio's id for it.
 */
function twilioStatusSentMessage(MessageStatus $status = MessageStatus::Sent, string $sid = TWILIO_STATUS_TEST_SID): Message
{
    $contact = Contact::factory()->create(['phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();

    return Message::factory()->for($conversation)->outbound()->create([
        'status' => $status,
        'provider' => 'twilio',
        'provider_message_id' => $sid,
    ]);
}

/**
 * Post a status callback the way Twilio does, signed for the status URL (worked out here from Twilio's published
 * recipe, not from the app's own class) unless told otherwise.
 *
 * @param  array<string, string>  $params
 * @param  bool  $signed  false sends the callback with no signature
 */
function twilioStatusPost(array $params, bool $signed = true): TestResponse
{
    $headers = [];

    if ($signed) {
        $sorted = $params;
        ksort($sorted);

        $data = TWILIO_STATUS_TEST_URL;

        foreach ($sorted as $name => $value) {
            $data .= $name.$value;
        }

        $headers['X-Twilio-Signature'] = base64_encode(hash_hmac('sha1', $data, TWILIO_STATUS_TEST_TOKEN, true));
    }

    return test()->post(route('webhooks.text.twilio.status'), $params, $headers);
}

/**
 * A Twilio-shaped status callback, with the extra fields Twilio sends along.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function twilioStatusPayload(string $status, array $overrides = []): array
{
    return array_merge([
        'MessageSid' => TWILIO_STATUS_TEST_SID,
        'SmsSid' => TWILIO_STATUS_TEST_SID,
        'AccountSid' => 'AC'.str_repeat('a', 32),
        'MessagingServiceSid' => 'MG'.str_repeat('d', 32),
        'From' => '+13125550147',
        'To' => '+12025550123',
        'MessageStatus' => $status,
        'SmsStatus' => $status,
    ], $overrides);
}

it('sets the message delivered on a delivered callback and answers 200 with empty TwiML', function () {
    $message = twilioStatusSentMessage();

    $response = twilioStatusPost(twilioStatusPayload('delivered'));

    $response->assertOk();
    expect($response->getContent())->toContain('<Response></Response>')
        ->and($message->refresh()->status)->toBe(MessageStatus::Delivered);
});

it('moves a queued message to sent on a sent callback', function () {
    $message = twilioStatusSentMessage(MessageStatus::Queued);

    twilioStatusPost(twilioStatusPayload('sent'))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('sets the message failed with the error code in meta on a failed callback', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('failed', ['ErrorCode' => '30003']))->assertOk();

    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Failed)
        ->and($message->meta['error_code'])->toBe('30003');
});

it('keeps any meta the message already has when it records the error code', function () {
    $message = twilioStatusSentMessage();
    $message->update(['meta' => ['in_reply_to' => [7]]]);

    twilioStatusPost(twilioStatusPayload('undelivered', ['ErrorCode' => '30005']))->assertOk();

    expect($message->refresh()->meta)->toMatchArray(['in_reply_to' => [7], 'error_code' => '30005']);
});

it('leaves a delivered message delivered when a late sent callback arrives', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('delivered'))->assertOk();
    twilioStatusPost(twilioStatusPayload('sent'))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Delivered);
});

it('does not move a failed message back to sent or on to delivered', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('failed', ['ErrorCode' => '30003']))->assertOk();
    twilioStatusPost(twilioStatusPayload('sent'))->assertOk();
    twilioStatusPost(twilioStatusPayload('delivered'))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Failed);
});

it('ignores the statuses that carry no news, such as queued, accepted and sending', function (string $status) {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload($status))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
})->with(['queued', 'accepted', 'sending', 'read', 'something-new']);

it('answers 200 and changes nothing for a sid it does not know', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('delivered', [
        'MessageSid' => 'SM'.str_repeat('9', 32),
        'SmsSid' => 'SM'.str_repeat('9', 32),
    ]))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('compares provider message ids as exact strings, so the same letters in another case match nothing', function () {
    $message = twilioStatusSentMessage();
    $shouted = strtoupper(TWILIO_STATUS_TEST_SID);

    twilioStatusPost(twilioStatusPayload('delivered', ['MessageSid' => $shouted, 'SmsSid' => $shouted]))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('reads the older SmsSid when the callback carries no MessageSid', function () {
    $message = twilioStatusSentMessage();
    $params = twilioStatusPayload('delivered');
    unset($params['MessageSid']);

    twilioStatusPost($params)->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Delivered);
});

it('answers 200 and changes nothing when the callback carries no sid at all', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(['MessageStatus' => 'delivered'])->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('never touches a text the customer sent, even when the sid matches', function () {
    $message = twilioStatusSentMessage();
    $message->update(['direction' => 'in', 'status' => MessageStatus::Received]);

    twilioStatusPost(twilioStatusPayload('delivered'))->assertOk();

    expect($message->refresh()->status)->toBe(MessageStatus::Received);
});

it('marks the customer opted out when an undelivered callback carries error 21610', function () {
    $message = twilioStatusSentMessage();
    $contact = $message->conversation->contact;

    twilioStatusPost(twilioStatusPayload('undelivered', ['ErrorCode' => '21610']))->assertOk();

    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Failed)
        ->and($message->meta['error_code'])->toBe('21610')
        ->and($contact->refresh()->isOptedOut())->toBeTrue()
        ->and($contact->activities()->where('type', ActivityType::Note)->count())->toBe(1);
});

it('keeps the original opt-out date when error 21610 arrives for a customer already opted out', function () {
    $message = twilioStatusSentMessage();
    $contact = $message->conversation->contact;
    $contact->update(['sms_opted_out_at' => now()->subDays(3)->startOfSecond()]);
    $optedOutAt = $contact->refresh()->sms_opted_out_at;

    twilioStatusPost(twilioStatusPayload('undelivered', ['ErrorCode' => '21610']))->assertOk();

    expect($contact->refresh()->sms_opted_out_at->equalTo($optedOutAt))->toBeTrue()
        ->and($contact->activities()->where('type', ActivityType::Note)->count())->toBe(0);
});

it('does not mark the customer opted out for any other error code', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('failed', ['ErrorCode' => '30003']))->assertOk();

    expect($message->conversation->contact->refresh()->isOptedOut())->toBeFalse();
});

it('answers 403 and changes nothing for an unsigned callback', function () {
    $message = twilioStatusSentMessage();

    twilioStatusPost(twilioStatusPayload('delivered'), signed: false)->assertForbidden();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('answers 403 for a callback signed for the inbound URL instead of the status URL', function () {
    config(['services.twilio.webhook_url' => 'https://ops.example.test/webhooks/text/twilio']);
    $message = twilioStatusSentMessage();
    $params = twilioStatusPayload('delivered');
    ksort($params);

    $data = 'https://ops.example.test/webhooks/text/twilio';

    foreach ($params as $name => $value) {
        $data .= $name.$value;
    }

    $this->post(route('webhooks.text.twilio.status'), $params, [
        'X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $data, TWILIO_STATUS_TEST_TOKEN, true)),
    ])->assertForbidden();

    expect($message->refresh()->status)->toBe(MessageStatus::Sent);
});

it('answers 404 when the text driver is not Twilio', function () {
    config(['channels.text.driver' => 'fake']);

    twilioStatusPost(twilioStatusPayload('delivered'))->assertNotFound();
});
