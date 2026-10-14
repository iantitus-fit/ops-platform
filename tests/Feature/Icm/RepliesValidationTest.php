<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

/*
| Each row: text to find in rules/replies.md, text to put in its place, and the exact line the owner must see.
*/
dataset('broken replies', [
    'a price in the holding reply' => ['he\'ll get back to you {next_open}."', 'he\'ll get back to you {next_open}. Ask about our $50 off deal."', 'rules/replies.md: holding_reply mentions a price ($50); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a discount in the holding reply' => ['Thanks! {owner_name}', 'Thanks! We offer a discount. {owner_name}', 'rules/replies.md: holding_reply offers a discount (discount); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a promise in the handoff reply' => ['Of course.', 'Of course. We promise.', 'rules/replies.md: handoff_reply makes a promise (promise); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a never_say phrase in the handoff reply' => ['Of course.', 'Of course. No problem.', 'rules/replies.md: handoff_reply uses a phrase the voice file says never to use (No problem)'],
    'a fill-in nobody knows' => ['Thanks! {owner_name}', 'Thanks {customer_name}! {owner_name}', 'rules/replies.md: holding_reply uses {customer_name}; the only fill-ins are {owner_name}, {business_name}, {next_open}, {today_or_next_open}, {next_opening} and {check_in_time}'],
    'a missing handoff reply' => ["handoff_reply: \"Of course. I've let {owner_name} know you'd like to talk with a person, and he'll reach out {next_open}.\"\n", '', 'rules/replies.md: handoff_reply is missing, and it is required'],
    'a missing owner name' => ["owner_name: Mike\n", '', 'rules/replies.md: owner_name is missing, and it is required'],
    'check_in_hours of zero' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: 0\n", 'rules/replies.md: check_in_hours must be 1 or more (you wrote 0)'],
    'check_in_hours of thirteen' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: 13\n", 'rules/replies.md: check_in_hours must be 12 or less (you wrote 13)'],
    'check_in_hours in words' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: two\n", 'rules/replies.md: check_in_hours must be a whole number (you wrote two)'],
    'a check-in time while check_in_hours is off' => ['he\'ll get back to you {next_open}."', 'he\'ll get back to you by {check_in_time}."', 'rules/replies.md: holding_reply uses {check_in_time}, but check_in_hours is not set; set it or take the fill-in out'],
    'a utility phone that is not a number' => ['utility_phone: "+13125550188"', 'utility_phone: "call the gas company"', 'rules/replies.md: utility_phone must be a phone number such as +13125550188 (you wrote call the gas company)'],
]);

it('puts the owner-written fixed replies live with the example folder', function () {
    activateExampleFolder();

    $replies = app(ActiveConfig::class)->replies();

    expect($replies['owner_name'])->toBe('Mike')
        ->and($replies['utility_phone'])->toBe('+13125550188')
        ->and($replies['holding_reply'])->toContain('{next_open}')
        ->and($replies['handoff_reply'])->toContain('{owner_name}')
        ->and($replies['body'])->not->toBe('');
});

it('rejects a fixed reply that would send something without the owner and keeps nothing live', function (string $search, string $replace, string $line) {
    expect(File::get(icmPath('rules/replies.md')))->toContain($search);

    breakFile('rules/replies.md', fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('broken replies');

it('lets a reply use the three known fill-ins and a missing utility phone', function () {
    breakFile('rules/replies.md', fn (string $text) => str_replace('Thanks! {owner_name}', 'Thanks! {business_name} and {owner_name}', $text));
    breakFile('rules/replies.md', fn (string $text) => str_replace("utility_phone: \"+13125550188\"\n", '', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->not->toHaveKey('utility_phone');
});

it('requires the folder to hold a fixed replies file', function () {
    File::delete(icmPath('rules/replies.md'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rules/replies.md: no file has type: set_replies, and the folder needs exactly one']);
});

it('still runs the other code checks when the never_say list is empty', function () {
    breakFile('voice/how-we-talk.md', fn (string $text) => str_replace('never_say: [guaranteed, cheapest, no problem]', 'never_say: []', $text));
    breakFile('rules/replies.md', fn (string $text) => str_replace('Of course.', 'Of course, no problem.', $text));

    activateExampleFolder();

    breakFile('rules/replies.md', fn (string $text) => str_replace('Of course, no problem.', 'Of course, it is only $20.', $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain('rules/replies.md: handoff_reply mentions a price ($20)')
        ->assertExitCode(1);
});

it('lets a reply use the check-in time once check_in_hours is set', function () {
    breakFile('rules/replies.md', fn (string $text) => str_replace(
        ["owner_name: Mike\n", 'he\'ll get back to you {next_open}."'],
        ["owner_name: Mike\ncheck_in_hours: 2\n", 'he\'ll get back to you by {check_in_time}."'],
        $text,
    ));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->toHaveKey('check_in_hours', 2);
});

it('treats check_in_hours as off when the owner leaves it out', function () {
    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->not->toHaveKey('check_in_hours');
});
