<?php

use App\SendRule\FixedReplies;
use Carbon\CarbonImmutable;

it('fills the holding reply with the owner name and the next opening', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))->toBe(
        "Thanks! Mike answers questions like this himself. I've sent him your message, and he'll get back to you when we open at 8 am tomorrow."
    );
});

it('fills the handoff reply with today while the shop is open', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->handoffReply($moment))->toBe(
        "Of course. I've let Mike know you'd like to talk with a person, and he'll reach out today."
    );
});

it('fills the business name wherever the owner wrote it', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        "I've sent him your message,",
        "I've sent him your message at {business_name},",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toContain('at Example Heating & Air,')
        ->not->toContain('{');
});

it('says the time the shop reopens in the holding reply after hours', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-17 14:00', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith('get back to you when we open at 8 am Monday.');
});

it('fills the today-or-next-open words in a holding reply', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        "he'll get back to you {next_open}.",
        "he'll get back to you {today_or_next_open}.",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("he'll get back to you after we open at 8 am tomorrow.")
        ->not->toContain('{');
});
