<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
