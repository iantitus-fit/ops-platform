<?php

use App\Channels\TwilioSignature;
use App\Enums\MessageDirection;
use App\Http\Middleware\ValidateTwilioSignature;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

const TWILIO_TEST_WEBHOOK_URL = 'https://ops.example.test/webhooks/text/twilio';
const TWILIO_TEST_STATUS_URL = 'https://ops.example.test/webhooks/text/twilio/status';
const TWILIO_TEST_AUTH_TOKEN = 'test-token';

beforeEach(function () {
    config([
        'channels.text.driver' => 'twilio',
        'services.twilio.auth_token' => TWILIO_TEST_AUTH_TOKEN,
        'services.twilio.webhook_url' => TWILIO_TEST_WEBHOOK_URL,
        'services.twilio.status_callback_url' => TWILIO_TEST_STATUS_URL,
    ]);
});

/**
 * A Twilio-shaped inbound text, with the extra fields Twilio sends along (account, geography, segment count).
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function twilioInboundPayload(array $overrides = []): array
{
    return array_merge([
        'MessageSid' => 'SM'.str_repeat('b', 32),
        'AccountSid' => 'AC'.str_repeat('c', 32),
        'MessagingServiceSid' => 'MG'.str_repeat('d', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'My AC is blowing warm air',
        'NumMedia' => '0',
        'NumSegments' => '1',
        'FromCity' => 'CHICAGO',
    ], $overrides);
}

/**
 * The X-Twilio-Signature header for a body, worked out here from Twilio's published recipe (not from the app's own
 * TwilioSignature class) so a bug in that class cannot hide itself.
 *
 * @param  array<string, string>  $params
 * @return array<string, string>
 */
function twilioSignatureHeaderFor(string $url, array $params, string $token = TWILIO_TEST_AUTH_TOKEN): array
{
    ksort($params);

    $data = $url;

    foreach ($params as $name => $value) {
        $data .= $name.$value;
    }

    return ['X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $data, $token, true))];
}

/**
 * Post a body to the inbound webhook the way Twilio does, signed for the configured URL unless told otherwise.
 *
 * @param  array<string, string>  $params
 * @param  array<string, string>|null  $headers  null signs it correctly; an empty array sends it unsigned
 */
function twilioPostInbound(array $params, ?array $headers = null): TestResponse
{
    return test()->post(
        route('webhooks.text.twilio'),
        $params,
        $headers ?? twilioSignatureHeaderFor(TWILIO_TEST_WEBHOOK_URL, $params),
    );
}

it('computes the signature Twilio publishes for its own test vector', function () {
    $signature = TwilioSignature::compute(
        'https://mycompany.com/myapp.php?foo=1&bar=2',
        [
            'CallSid' => 'CA1234567890ABCDE',
            'Caller' => '+14158675309',
            'Digits' => '1234',
            'From' => '+14158675309',
            'To' => '+18005551212',
        ],
        '12345',
    );

    expect($signature)->toBe('RSOYDt4T1cUTdK1PDd93/VVr8B8=');
});

it('lists the URL as written and with and without the default port', function () {
    expect(TwilioSignature::urlVariants('https://ops.example.test/webhooks/text/twilio'))->toBe([
        'https://ops.example.test/webhooks/text/twilio',
        'https://ops.example.test:443/webhooks/text/twilio',
    ])->and(TwilioSignature::urlVariants('https://ops.example.test:443/webhooks/text/twilio'))->toBe([
        'https://ops.example.test:443/webhooks/text/twilio',
        'https://ops.example.test/webhooks/text/twilio',
    ])->and(TwilioSignature::urlVariants('http://ops.example.test/hook'))->toBe([
        'http://ops.example.test/hook',
        'http://ops.example.test:80/hook',
    ]);
});

it('records a correctly signed Twilio text and queues it once', function () {
    $params = twilioInboundPayload();

    $response = twilioPostInbound($params);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/xml')
        ->and($response->getContent())->toContain('<Response></Response>');

    $message = Message::sole();

    expect($message->provider)->toBe('twilio')
        ->and($message->provider_message_id)->toBe($params['MessageSid'])
        ->and($message->body)->toBe('My AC is blowing warm air')
        ->and($message->direction)->toBe(MessageDirection::In)
        ->and(Contact::sole()->phone_e164)->toBe('+12025550123');

    Queue::assertPushed(ProcessInbound::class, 1);
    Queue::assertPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $message->id);
});

it('refuses a text with no signature and records nothing', function () {
    twilioPostInbound(twilioInboundPayload(), [])->assertForbidden();

    expect(Message::count())->toBe(0)
        ->and(Contact::count())->toBe(0);

    Queue::assertNothingPushed();
});

it('refuses a text with a wrong signature and records nothing', function () {
    $params = twilioInboundPayload();

    twilioPostInbound($params, twilioSignatureHeaderFor(TWILIO_TEST_WEBHOOK_URL, $params, 'some-other-token'))
        ->assertForbidden();

    twilioPostInbound($params, ['X-Twilio-Signature' => 'RSOYDt4T1cUTdK1PDd93/VVr8B8='])->assertForbidden();

    expect(Message::count())->toBe(0);
});

it('refuses a text whose body was changed after it was signed', function () {
    $params = twilioInboundPayload();
    $headers = twilioSignatureHeaderFor(TWILIO_TEST_WEBHOOK_URL, $params);

    twilioPostInbound([...$params, 'Body' => 'Send the invoice to a new account'], $headers)->assertForbidden();

    expect(Message::count())->toBe(0);
});

it('refuses everything while the auth token or the webhook URL is not configured', function () {
    $params = twilioInboundPayload();
    $headers = twilioSignatureHeaderFor(TWILIO_TEST_WEBHOOK_URL, $params, '');

    config(['services.twilio.auth_token' => null]);
    twilioPostInbound($params, $headers)->assertForbidden();

    config(['services.twilio.auth_token' => TWILIO_TEST_AUTH_TOKEN, 'services.twilio.webhook_url' => null]);
    twilioPostInbound($params)->assertForbidden();

    expect(Message::count())->toBe(0);
});

it('checks the signature against the configured URL, not the one the request arrived on', function () {
    $params = twilioInboundPayload();

    twilioPostInbound($params, twilioSignatureHeaderFor(url(route('webhooks.text.twilio', absolute: false)), $params))
        ->assertForbidden();

    twilioPostInbound($params)->assertOk();
});

it('also accepts a signature made over the URL with the default port added', function () {
    $params = twilioInboundPayload();

    twilioPostInbound($params, twilioSignatureHeaderFor('https://ops.example.test:443/webhooks/text/twilio', $params))
        ->assertOk();

    expect(Message::count())->toBe(1);
});

it('accepts a signature made over the URL without the port when the configured URL names it', function () {
    config(['services.twilio.webhook_url' => 'https://ops.example.test:443/webhooks/text/twilio']);
    $params = twilioInboundPayload();

    twilioPostInbound($params, twilioSignatureHeaderFor('https://ops.example.test/webhooks/text/twilio', $params))
        ->assertOk();
});

it('logs a signature mismatch with the host only, never the token, the signature or the body', function () {
    Log::spy();
    $params = twilioInboundPayload(['Body' => 'my door code is 4471']);

    twilioPostInbound($params, ['X-Twilio-Signature' => 'not-the-signature'])->assertForbidden();

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context = []): bool {
        $logged = $message.json_encode($context);

        return $context === ['host' => 'ops.example.test']
            && ! str_contains($logged, TWILIO_TEST_AUTH_TOKEN)
            && ! str_contains($logged, 'not-the-signature')
            && ! str_contains($logged, '4471');
    });
});

it('records a retried MessageSid once and queues it once', function () {
    $params = twilioInboundPayload();

    twilioPostInbound($params)->assertOk();
    twilioPostInbound($params)->assertOk();

    expect(Message::count())->toBe(1)
        ->and(Message::sole()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessInbound::class, 1);
});

it('records an emoji body unchanged when the signature was made over the UTF-8 text', function () {
    twilioPostInbound(twilioInboundPayload(['Body' => 'Thanks 😊']))->assertOk();

    expect(Message::sole()->body)->toBe('Thanks 😊');
});

it('accepts a body with leading and trailing whitespace, because the signature covers the text as Twilio sent it', function () {
    $body = "\n  Yes, please come today \n";

    twilioPostInbound(twilioInboundPayload(['Body' => $body]))->assertOk();

    expect(Message::sole()->body)->toBe($body);
});

it('stores a 1,600 character body from Twilio in full', function () {
    $body = str_repeat('x', 1600);

    twilioPostInbound(twilioInboundPayload(['Body' => $body]))->assertOk();

    expect(mb_strlen(Message::sole()->body))->toBe(1600)
        ->and(Message::sole()->body)->toBe($body);
});

it('records a picture-only text with an empty body and the media count', function () {
    twilioPostInbound(twilioInboundPayload([
        'Body' => '',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/AC1/Messages/MM1/Media/ME1',
        'MediaContentType0' => 'image/jpeg',
    ]))->assertOk();

    $message = Message::sole();

    expect($message->body)->toBe('')
        ->and($message->meta['num_media'])->toBe(1);
});

it('answers a signed text with no sender in JSON 422 and records nothing', function () {
    $params = twilioInboundPayload();
    unset($params['From']);

    twilioPostInbound($params)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['From']);

    expect(Message::count())->toBe(0);
});

it('opts the contact out on a Twilio STOP and sends no reply (D-39)', function () {
    twilioPostInbound(twilioInboundPayload(['Body' => 'STOP', 'OptOutType' => 'STOP']))->assertOk();

    $contact = Contact::sole();

    expect($contact->isOptedOut())->toBeTrue()
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('answers 404 on the Twilio route unless the Twilio driver is on, and on the fake route when it is', function () {
    $params = twilioInboundPayload();

    config(['channels.text.driver' => 'fake']);
    twilioPostInbound($params)->assertNotFound();

    config(['channels.text.driver' => 'twilio']);
    $this->post(route('webhooks.text.fake'), ['MessageSid' => 'SM1', 'From' => '+12025550123'])->assertNotFound();

    expect(Message::count())->toBe(0);
});

it('checks the status callback URL when the middleware is given the status parameter', function () {
    Route::post('/webhooks/text/twilio/status-probe', fn () => response('ok'))
        ->middleware(ValidateTwilioSignature::class.':status');

    $params = ['MessageSid' => 'SM'.str_repeat('e', 32), 'MessageStatus' => 'delivered'];

    $this->post('/webhooks/text/twilio/status-probe', $params, twilioSignatureHeaderFor(TWILIO_TEST_WEBHOOK_URL, $params))
        ->assertForbidden();

    $this->post('/webhooks/text/twilio/status-probe', $params, twilioSignatureHeaderFor(TWILIO_TEST_STATUS_URL, $params))
        ->assertOk();
});
