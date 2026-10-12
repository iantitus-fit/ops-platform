<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Contact;

/**
 * The summaries of the note entries on the timeline, oldest first.
 *
 * @return list<string>
 */
function nameNotes(): array
{
    return Activity::query()->where('type', ActivityType::Note)->orderBy('id')->pluck('summary')->all();
}

/**
 * A web form post with a name, a phone and a message.
 */
function formFrom(object $test, string $name, string $phone = '(202) 555-0123'): void
{
    $test->postJson(route('leads.store'), [
        'name' => $name,
        'phone' => $phone,
        'message' => 'Need AC looked at this week',
        'source' => 'angi',
    ])->assertOk();
}

it('fills an empty name from the name the customer gave in their text (D-43)', function () {
    frontDeskCustomerTexts("Hi, it's Dana and my AC stopped");
    scriptFrontDeskReplies(['customer_name' => 'Dana']);

    runQueuedFrontDeskJobs();

    expect(Contact::sole()->name)->toBe('Dana')
        ->and(nameNotes())->toBe(["Name from the customer's text: Dana"]);
});

it('keeps the name on one trimmed line of at most 120 characters (D-43)', function () {
    frontDeskCustomerTexts('This is Dana');
    scriptFrontDeskReplies(['customer_name' => "  Dana\nWhitfield ".str_repeat('x', 200)]);

    runQueuedFrontDeskJobs();

    $name = (string) Contact::sole()->name;

    expect($name)->toStartWith('Dana Whitfield xxx')
        ->and(mb_strlen($name))->toBe(120)
        ->and($name)->not->toContain("\n");
});

it('never overwrites a stored name with the name the customer gave the agent (D-43)', function () {
    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    frontDeskCustomerTexts('Hi, this is Dee');
    scriptFrontDeskReplies(['customer_name' => 'Dee']);

    runQueuedFrontDeskJobs();

    expect(Contact::sole()->name)->toBe('Dana Whitfield')
        ->and(nameNotes())->toBe([]);
});

it('gives a contact the form created the name on the form (D-43)', function () {
    activateExampleFolder();

    formFrom($this, 'Dana Whitfield');

    expect(Contact::sole()->name)->toBe('Dana Whitfield')
        ->and(nameNotes())->toBe([]);
});

it('keeps the stored name and notes a different name a form gave for a contact it matched by phone (D-43, T-02-35)', function () {
    activateExampleFolder();
    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    formFrom($this, 'Someone Else');

    expect(Contact::sole()->name)->toBe('Dana Whitfield')
        ->and(nameNotes())->toBe(['form gave the name: Someone Else']);
});

it('leaves an empty name empty and notes the name a form gave for a contact it matched by phone (D-43, T-02-35)', function () {
    activateExampleFolder();
    Contact::factory()->create(['name' => null, 'phone_e164' => '+12025550123']);

    formFrom($this, 'Someone Else');

    expect(Contact::sole()->name)->toBeNull()
        ->and(nameNotes())->toBe(['form gave the name: Someone Else']);
});

it('adds no note when the form gives the name already stored (D-43)', function () {
    activateExampleFolder();
    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    formFrom($this, 'Dana Whitfield');

    expect(Contact::sole()->name)->toBe('Dana Whitfield')
        ->and(nameNotes())->toBe([]);
});
