<?php

use App\SendRule\FixedReplies;
use App\Icm\ActiveConfig;
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

it('fills the next opening alone', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        "he'll get back to you {next_open}.",
        "he'll get back to you at {next_opening}.",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("he'll get back to you at 8 am tomorrow.")
        ->not->toContain('{');
});

it('fills the check-in time when the owner has set check_in_hours', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        ["owner_name: Mike\n", "he'll get back to you {next_open}."],
        ["owner_name: Mike\ncheck_in_hours: 2\n", "If you haven't heard back by {check_in_time}, text us."],
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("If you haven't heard back by 10 am tomorrow, text us.")
        ->not->toContain('{');
});

it('never lets a literal check-in time reach a customer when an old version has no check_in_hours', function () {
    $version = activateExampleFolder();
    $compiled = $version->compiled;
    $compiled['files']['rules/replies.md']['front_matter']['holding_reply'] = 'We will check in by {check_in_time}.';
    $version->update(['compiled' => $compiled]);

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toBe('We will check in by 8 am tomorrow.')
        ->not->toContain('{');
});

it('fills the complaint reply with the owner name and today or the next opening', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->complaintReply($moment))->toBe(
        "I'm sorry about this. I've passed your message straight to Mike, and he'll get back to you today if possible, or after we open at 8 am tomorrow."
    );
});

it('falls back to the holding reply when a live version has no complaint reply', function () {
    $version = activateExampleFolder();
    $compiled = $version->compiled;
    unset($compiled['files']['rules/replies.md']['front_matter']['complaint_reply']);
    $version->update(['compiled' => $compiled]);
    app(ActiveConfig::class)->refresh();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->complaintReply($moment))
        ->toBe(app(FixedReplies::class)->holdingReply($moment));
});
