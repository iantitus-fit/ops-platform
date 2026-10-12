<?php

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('fails any test that makes a real web request without faking it first', function () {
    expect(fn () => Http::get('https://example.com'))->toThrow(RuntimeException::class);
});

it('still answers a request that a test faked on purpose', function () {
    Http::fake(['example.com/*' => Http::response(['ok' => true])]);

    expect(Http::get('https://example.com/ping')->json('ok'))->toBeTrue();
});

it('runs on the database the run asked for, so Postgres is never silently SQLite', function () {
    expect(DB::connection()->getDriverName())->toBe(env('DB_CONNECTION', 'sqlite'));
});

it('runs every Feature test on the fake text driver with no live Twilio or Jev keys', function () {
    expect(config('channels.text.driver'))->toBe('fake')
        ->and(config('services.twilio.account_sid'))->toBeNull()
        ->and(config('services.twilio.auth_token'))->toBeNull()
        ->and(config('services.twilio.messaging_service_sid'))->toBeNull()
        ->and(config('services.twilio.webhook_url'))->toBeNull()
        ->and(config('services.twilio.status_callback_url'))->toBeNull()
        ->and(config('services.jev.key'))->toBeNull()
        ->and(config('services.jev.model'))->toBe('typesafe/jev-1.13')
        ->and(config('services.jev.base_url'))->toBe('https://openrouter.ai/api/v1')
        ->and(app(TextChannel::class))->toBeInstanceOf(FakeTextChannel::class);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
});

it('keeps the fake driver and blank keys when the environment says twilio and holds live-looking keys', function () {
    $junitLog = sys_get_temp_dir().'/safety-net-'.uniqid('', true).'.xml';

    try {
        $result = Process::path(base_path())
            ->env([
                'TEXT_CHANNEL' => 'twilio',
                'TWILIO_ACCOUNT_SID' => 'AC0000live',
                'TWILIO_AUTH_TOKEN' => 'live-token',
                'TWILIO_MESSAGING_SERVICE_SID' => 'MG0000live',
                'TWILIO_WEBHOOK_URL' => 'https://live.example.test/webhooks/text/twilio',
                'TWILIO_STATUS_CALLBACK_URL' => 'https://live.example.test/webhooks/text/twilio/status',
                'JEV_API_KEY' => 'live-key',
                'JEV_MODEL' => 'typesafe/jev-latest',
                'JEV_BASE_URL' => 'https://live.example.test/v1',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
            ])
            ->timeout(120)
            ->run([
                PHP_BINARY,
                'vendor/bin/pest',
                'tests/Feature/Quality/TestSafetyNetTest.php',
                '--filter=runs every Feature test on the fake text driver',
                '--log-junit',
                $junitLog,
            ]);

        $explanation = "The child Pest run saw the hostile environment:\n".$result->output().$result->errorOutput();

        expect($result->successful())->toBeTrue($explanation)
            ->and(is_file($junitLog))->toBeTrue($explanation);

        $suite = simplexml_load_file($junitLog)->testsuite;

        expect((int) $suite['tests'])->toBe(1, $explanation)
            ->and((int) $suite['failures'])->toBe(0, $explanation)
            ->and((int) $suite['errors'])->toBe(0, $explanation);
    } finally {
        if (is_file($junitLog)) {
            unlink($junitLog);
        }
    }
});

it('keeps typesafe/jev-1.13 as the Jev model default in config/services.php', function () {
    expect(file_get_contents(config_path('services.php')))->toContain("env('JEV_MODEL', 'typesafe/jev-1.13')");
});
