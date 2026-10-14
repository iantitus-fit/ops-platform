<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

const HOLDING_REPLY_ENDING = "this to {owner_name} and you're on his list. He'll get back to you {today_or_next_open}.\"";

/*
| Each row: text to find in rules/replies.md, text to put in its place, and the exact line the owner must see.
*/
dataset('broken replies', [
    'a price in the holding reply' => ["Thanks. I've passed this to {owner_name}", "Thanks. It is only $50. I've passed this to {owner_name}", 'rules/replies.md: holding_reply mentions a price ($50); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a discount in the holding reply' => ["Thanks. I've passed this to {owner_name}", "Thanks. We offer a discount. I've passed this to {owner_name}", 'rules/replies.md: holding_reply offers a discount (discount); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a promise in the handoff reply' => ['Of course.', 'Of course. We promise.', 'rules/replies.md: handoff_reply makes a promise (promise); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a never_say phrase in the handoff reply' => ['Of course.', 'Of course. No problem.', 'rules/replies.md: handoff_reply uses a phrase the voice file says never to use (No problem)'],
    'a fill-in nobody knows' => ["Thanks. I've passed this to {owner_name}", "Thanks {customer_name}. I've passed this to {owner_name}", 'rules/replies.md: holding_reply uses {customer_name}; the only fill-ins are {owner_name}, {business_name}, {next_open}, {today_or_next_open}, {next_opening} and {check_in_time}'],
    'a missing handoff reply' => ["handoff_reply: \"Of course. I've let {owner_name} know you'd like to talk with a person, and he'll reach out {next_open}.\"\n", '', 'rules/replies.md: handoff_reply is missing, and it is required'],
    'a missing owner name' => ["owner_name: Mike\n", '', 'rules/replies.md: owner_name is missing, and it is required'],
    'check_in_hours of zero' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: 0\n", 'rules/replies.md: check_in_hours must be 1 or more (you wrote 0)'],
    'check_in_hours of thirteen' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: 13\n", 'rules/replies.md: check_in_hours must be 12 or less (you wrote 13)'],
    'check_in_hours in words' => ["owner_name: Mike\n", "owner_name: Mike\ncheck_in_hours: two\n", 'rules/replies.md: check_in_hours must be a whole number (you wrote two)'],
    'a check-in time while check_in_hours is off' => [HOLDING_REPLY_ENDING, "this to {owner_name} and you're on his list. He'll get back to you by {check_in_time}.\"", 'rules/replies.md: holding_reply uses {check_in_time}, but check_in_hours is not set; set it or take the fill-in out'],
    'a missing complaint reply' => ["complaint_reply: \"I'm sorry about this. I've passed your message straight to {owner_name}, and he'll get back to you {today_or_next_open}.\"\n", '', 'rules/replies.md: complaint_reply is missing, and it is required'],
    'a promise in the complaint reply' => ["I'm sorry about this.", "I'm sorry about this. We promise to fix it.", 'rules/replies.md: complaint_reply makes a promise (promise); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a never_say phrase in the complaint reply' => ["I'm sorry about this.", "I'm sorry about this. No problem.", 'rules/replies.md: complaint_reply uses a phrase the voice file says never to use (No problem)'],
    'a missing partial holding reply' => ["partial_holding_reply: \"I've passed your other question to {owner_name}, and he'll follow up on it.\"\n", '', 'rules/replies.md: partial_holding_reply is missing, and it is required'],
    'a missing check-in reply' => ["check_in_reply: \"Thanks for checking in. {owner_name} still has your question and you're on his list. He'll get back to you {today_or_next_open}.\"\n", '', 'rules/replies.md: check_in_reply is missing, and it is required'],
    'a missing added-info reply' => ["added_info_reply: \"Got it, I've passed that along to {owner_name} with your question.\"\n", '', 'rules/replies.md: added_info_reply is missing, and it is required'],
    'a discount in the partial holding reply' => ["I've passed your other question", "We can offer a discount. I've passed your other question", 'rules/replies.md: partial_holding_reply offers a discount (discount); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a price in the discount reply' => ['We like to take care of our customers.', 'We like to take care of our customers. $20 off.', 'rules/replies.md: discount_reply mentions a price ($20); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a promise in a price lead' => ["Here's that estimate again:", "Here's that guaranteed estimate again:", 'rules/replies.md: price_leads same makes a promise (guaranteed); a fixed reply goes out without you, so it cannot mention a price, offer a discount or make a promise'],
    'a price lead that is missing' => ["  lower: \"Thanks for clarifying. That brings the estimate down:\"\n", '', 'rules/replies.md: price_leads needs same, higher, lower and changed; lower is missing'],
    'a utility phone that is not a number' => ['utility_phone: "+13125550188"', 'utility_phone: "call the gas company"', 'rules/replies.md: utility_phone must be a phone number such as +13125550188 (you wrote call the gas company)'],
]);

it('puts the owner-written fixed replies live with the example folder', function () {
    activateExampleFolder();

    $replies = app(ActiveConfig::class)->replies();

    expect($replies['owner_name'])->toBe('Mike')
        ->and($replies['utility_phone'])->toBe('+13125550188')
        ->and($replies['holding_reply'])->toContain('{today_or_next_open}')
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
    breakFile('rules/replies.md', fn (string $text) => str_replace("Thanks. I've passed this to {owner_name}", 'Thanks. {business_name} has passed this to {owner_name}', $text));
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
        ["owner_name: Mike\n", HOLDING_REPLY_ENDING],
        ["owner_name: Mike\ncheck_in_hours: 2\n", "this to {owner_name} and you're on his list. He'll get back to you by {check_in_time}.\""],
        $text,
    ));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->toHaveKey('check_in_hours', 2);
});

it('treats check_in_hours as off when the owner leaves it out', function () {
    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->not->toHaveKey('check_in_hours');
});

it('puts every new reply and lead live with the example folder', function () {
    activateExampleFolder();

    $replies = app(ActiveConfig::class)->replies();

    expect($replies)->toHaveKeys(['partial_holding_reply', 'check_in_reply', 'added_info_reply', 'complaint_reply', 'discount_reply', 'price_leads'])
        ->and($replies['price_leads'])->toHaveKeys(['same', 'higher', 'lower', 'changed']);
});

it('syncs without a discount reply, because it is optional', function () {
    breakFile('rules/replies.md', fn (string $text) => preg_replace('/^discount_reply: .*\n/m', '', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->not->toHaveKey('discount_reply');
});

it('lets the discount reply name a discount, the one reply that may', function () {
    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies()['discount_reply'])->toContain('discount');
});

it('requires the price leads once a service has price sentences, and names the service', function () {
    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace(
        'price_cents: 8900',
        "price_cents: 8900\nprice_sentences:\n  - sentence: \"AC repair starts with an \$89 diagnostic visit.\"",
        $text,
    ));
    breakFile('rules/replies.md', fn (string $text) => preg_replace('/^price_leads:\n(  .*\n){4}/m', '', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rules/replies.md: price_leads is missing, and it is required because business/services/ac-repair.md has price sentences']);
});

it('syncs without price leads while no service has price sentences', function () {
    breakFile('rules/replies.md', fn (string $text) => preg_replace('/^price_leads:\n(  .*\n){4}/m', '', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->replies())->not->toHaveKey('price_leads');
});
