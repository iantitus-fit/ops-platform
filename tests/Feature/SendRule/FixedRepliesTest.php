<?php

use App\Icm\ActiveConfig;
use App\SendRule\FixedReplies;
use Carbon\CarbonImmutable;

it('fills the holding reply with the owner name and the next opening', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))->toBe(
        "Thanks. I've passed this to Mike and you're on his list. He'll get back to you after we open at 8 am tomorrow."
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
        "I've passed this to {owner_name} and",
        "I've passed this to {owner_name} at {business_name} and",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toContain('at Example Heating & Air and')
        ->not->toContain('{');
});

it('says the time the shop reopens in the holding reply after hours', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-17 14:00', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("He'll get back to you after we open at 8 am Monday.");
});

it('fills the today-or-next-open words in the holding reply', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 10:00', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("He'll get back to you today if possible, or after we open at 8 am tomorrow.")
        ->not->toContain('{');
});

it('still fills the plain next-open words when the owner prefers them', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        "He'll get back to you {today_or_next_open}.",
        "He'll get back to you {next_open}.",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("He'll get back to you when we open at 8 am tomorrow.")
        ->not->toContain('{');
});

it('fills the next opening alone', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        "He'll get back to you {today_or_next_open}.",
        "He'll get back to you at {next_opening}.",
        $replies,
    ));
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');

    expect(app(FixedReplies::class)->holdingReply($moment))
        ->toEndWith("He'll get back to you at 8 am tomorrow.")
        ->not->toContain('{');
});

it('fills the check-in time when the owner has set check_in_hours', function () {
    breakFile('rules/replies.md', fn (string $replies) => str_replace(
        ["owner_name: Mike\n", "He'll get back to you {today_or_next_open}."],
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

it('fills the other new replies and the price leads', function () {
    activateExampleFolder();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');
    $replies = app(FixedReplies::class);

    expect($replies->partialHoldingReply($moment))->toBe("I've passed your other question to Mike, and he'll follow up on it.")
        ->and($replies->checkInReply($moment))->toBe("Thanks for checking in. Mike still has your question and you're on his list. He'll get back to you after we open at 8 am tomorrow.")
        ->and($replies->addedInfoReply($moment))->toBe("Got it, I've passed that along to Mike with your question.")
        ->and($replies->discountReply($moment))->toBe("We like to take care of our customers. I'll make sure Mike knows you asked about a discount and he'll pick up the thread.")
        ->and($replies->priceLead('same', $moment))->toBe("Here's that estimate again:")
        ->and($replies->priceLead('higher', $moment))->toBe('Thanks for clarifying. That puts the estimate higher:')
        ->and($replies->priceLead('lower', $moment))->toBe('Thanks for clarifying. That brings the estimate down:')
        ->and($replies->priceLead('changed', $moment))->toBe('Thanks for clarifying. That changes the estimate:')
        ->and($replies->priceLead('sideways', $moment))->toBeNull();
});

it('falls back to the holding reply for every new reply when a live version has none of them', function () {
    $version = activateExampleFolder();
    $compiled = $version->compiled;

    foreach (['partial_holding_reply', 'check_in_reply', 'added_info_reply', 'complaint_reply', 'discount_reply', 'price_leads'] as $field) {
        unset($compiled['files']['rules/replies.md']['front_matter'][$field]);
    }

    $version->update(['compiled' => $compiled]);
    app(ActiveConfig::class)->refresh();

    $moment = CarbonImmutable::parse('2026-10-13 16:30', 'America/Chicago');
    $replies = app(FixedReplies::class);
    $holding = $replies->holdingReply($moment);

    expect($replies->partialHoldingReply($moment))->toBe($holding)
        ->and($replies->checkInReply($moment))->toBe($holding)
        ->and($replies->addedInfoReply($moment))->toBe($holding)
        ->and($replies->complaintReply($moment))->toBe($holding)
        ->and($replies->discountReply($moment))->toBe($holding)
        ->and($replies->priceLead('same', $moment))->toBeNull();
});
