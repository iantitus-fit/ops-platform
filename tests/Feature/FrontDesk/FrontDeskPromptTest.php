<?php

use App\Ai\AgentFile;
use App\Ai\FrontDeskPrompt;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * The live example folder, the example sms agent, and Dana with an open conversation and no messages yet.
 *
 * @return array{AgentFile, Conversation, Contact}
 */
function promptTestThread(): array
{
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();

    return [AgentFile::forChannel(app(ActiveConfig::class), 'sms'), $conversation, $contact];
}

/**
 * The messages with the given ids as a collection, the way ClaimInboundBurst hands over a burst.
 *
 * @return Collection<int, Message>
 */
function promptTestBurst(Message ...$messages): Collection
{
    return Message::query()->whereKey(array_map(fn (Message $message) => $message->id, $messages))->orderBy('id')->get();
}

/**
 * The history as a list of role and content pairs, easier to compare than message objects.
 *
 * @param  list<Laravel\Ai\Messages\Message>  $history
 * @return list<array{string, string}>
 */
function promptTestTurns(array $history): array
{
    return array_map(fn ($turn) => [$turn->role->value, $turn->content], $history);
}

it('puts the whole routed files, the agent body and the rules into the instructions', function () {
    [$agent, , $contact] = promptTestThread();

    $instructions = app(FrontDeskPrompt::class)->instructions($agent, $contact);

    expect($instructions)
        ->toContain('The diagnostic fee is $89.')
        ->toContain('What is the system doing?')
        ->toContain('A tune-up covers cleaning, a safety check and a written report on the furnace')
        ->toContain('Example Heating & Air is a family-run heating and cooling company')
        ->toContain("It asks the matching service's intake questions one at a time.")
        ->toContain('Friendly, plain and short.')
        ->and(strtolower($instructions))->toContain('never state or estimate a price');
});

it('never reads records/ or a file the agent does not load', function () {
    [$agent, , $contact] = promptTestThread();

    writeIcmFile('records/marker.md', 'RECORDS-MARKER');

    $instructions = app(FrontDeskPrompt::class)->instructions($agent, $contact);

    expect($instructions)
        ->not->toContain('RECORDS-MARKER')
        ->not->toContain('These two texts go out without you.');
});

it('renders the customer card fresh from the database on every run', function () {
    [$agent, , $contact] = promptTestThread();
    $contact->update(['email' => 'before@example.com']);

    $prompt = app(FrontDeskPrompt::class);

    expect($prompt->instructions($agent, $contact))->toContain('before@example.com');

    $contact->update(['email' => 'after@example.com']);

    $instructions = $prompt->instructions($agent, $contact->fresh());

    expect($instructions)->toContain('after@example.com')->not->toContain('before@example.com');
});

it('shows the thread as alternating user and assistant turns, joining same-role texts', function () {
    [, $conversation] = promptTestThread();

    Message::factory()->for($conversation)->create(['body' => 'Hi, is anyone there?']);
    Message::factory()->for($conversation)->create(['body' => 'My AC stopped']);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'What is the system doing?']);
    Message::factory()->for($conversation)->fromOwner()->create(['body' => 'Mike here, I can look at it']);
    $new = Message::factory()->for($conversation)->create(['body' => 'It is blowing warm air']);

    $history = app(FrontDeskPrompt::class)->history($conversation, promptTestBurst($new));

    expect(promptTestTurns($history))->toBe([
        ['user', "Hi, is anyone there?\n\nMy AC stopped"],
        ['assistant', "What is the system doing?\n\nMike here, I can look at it"],
    ]);
});

it('leaves drafts, failed sends and texts not yet sent out of the history', function () {
    [, $conversation] = promptTestThread();

    Message::factory()->for($conversation)->create(['body' => 'My AC stopped']);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'A held draft', 'status' => MessageStatus::Draft]);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'A failed send', 'status' => MessageStatus::Failed]);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'A queued send', 'status' => MessageStatus::Queued]);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'A delivered reply', 'status' => MessageStatus::Delivered]);
    $new = Message::factory()->for($conversation)->create(['body' => 'Still warm']);

    $history = app(FrontDeskPrompt::class)->history($conversation, promptTestBurst($new));

    expect(promptTestTurns($history))->toBe([
        ['user', 'My AC stopped'],
        ['assistant', 'A delivered reply'],
    ]);
});

it('starts with a user turn when the business texted first', function () {
    [, $conversation] = promptTestThread();

    Message::factory()->for($conversation)->fromOwner()->create(['body' => 'Your tune-up is due']);
    $new = Message::factory()->for($conversation)->create(['body' => 'Yes please']);

    $history = app(FrontDeskPrompt::class)->history($conversation, promptTestBurst($new));

    expect(promptTestTurns($history))->toBe([
        ['user', '(The business texted first.)'],
        ['assistant', 'Your tune-up is due'],
    ]);
});

it('keeps the turns alternating when an earlier customer text got no reply', function () {
    [, $conversation] = promptTestThread();

    Message::factory()->for($conversation)->create(['body' => 'My AC stopped']);
    Message::factory()->for($conversation)->outbound()->create(['body' => 'A held draft', 'status' => MessageStatus::Draft]);
    $new = Message::factory()->for($conversation)->create(['body' => 'Hello?']);

    $history = app(FrontDeskPrompt::class)->history($conversation, promptTestBurst($new));

    expect(promptTestTurns($history))->toBe([
        ['user', 'My AC stopped'],
        ['assistant', '(No reply was sent.)'],
    ]);
});

it('has no history for the first text in a conversation', function () {
    [, $conversation] = promptTestThread();

    $new = Message::factory()->for($conversation)->create(['body' => 'Hello']);

    expect(app(FrontDeskPrompt::class)->history($conversation, promptTestBurst($new)))->toBe([]);
});

it('joins a burst into one prompt in arrival order', function () {
    [, $conversation] = promptTestThread();

    $first = Message::factory()->for($conversation)->create(['body' => 'Hi']);
    $second = Message::factory()->for($conversation)->create(['body' => 'My AC stopped']);
    $third = Message::factory()->for($conversation)->create(['body' => 'It is 90 degrees']);

    expect(app(FrontDeskPrompt::class)->promptText(promptTestBurst($first, $second, $third)))
        ->toBe("Hi\nMy AC stopped\nIt is 90 degrees");
});

it('tells the model when a text was only a picture or was empty', function () {
    [, $conversation] = promptTestThread();

    $picture = Message::factory()->for($conversation)->create(['body' => '', 'meta' => ['num_media' => 1]]);
    $empty = Message::factory()->for($conversation)->create(['body' => '   ', 'meta' => ['num_media' => 0]]);
    $none = Message::factory()->for($conversation)->create(['body' => '', 'meta' => null]);

    $prompt = app(FrontDeskPrompt::class);

    expect($prompt->promptText(promptTestBurst($picture)))->toBe('(The customer sent a picture with no text.)')
        ->and($prompt->promptText(promptTestBurst($empty)))->toBe('(The customer sent an empty text.)')
        ->and($prompt->promptText(promptTestBurst($none)))->toBe('(The customer sent an empty text.)');
});

it('passes emoji and accented text through unchanged', function () {
    [, $conversation] = promptTestThread();

    $message = Message::factory()->for($conversation)->create(['body' => '¿Pueden venir mañana? 😊']);

    expect(app(FrontDeskPrompt::class)->promptText(promptTestBurst($message)))->toBe('¿Pueden venir mañana? 😊');
});

it('expands a folder entry to every typed file under it and keeps the others as written', function () {
    [$agent] = promptTestThread();

    expect(app(FrontDeskPrompt::class)->loadedPaths($agent))->toBe([
        'business/profile.md',
        'business/hours.md',
        'business/faq.md',
        'business/services/ac-repair.md',
        'business/services/furnace-tune-up.md',
        'voice/how-we-talk.md',
        'rules/sending.md',
    ]);
});

it('keeps a router file out of a folder entry', function () {
    [$agent] = promptTestThread();

    expect(app(FrontDeskPrompt::class)->loadedPaths($agent))->not->toContain('business/services/CONTEXT.md');
    expect(File::exists(icmPath('business/services/CONTEXT.md')))->toBeTrue();
});
