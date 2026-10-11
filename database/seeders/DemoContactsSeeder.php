<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Four fictional customers for trying the dashboard. Names and addresses are made up, the phone
 * numbers are real-format reserved 555-01XX lines, and every cost is integer cents from the
 * example business's lead sources.
 */
class DemoContactsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDana();
        $this->seedMarcus();
        $this->seedPriya();
        $this->seedSam();
    }

    /**
     * An Angi lead with an open text thread: two customer texts and one reply from the owner.
     */
    private function seedDana(): void
    {
        $dana = Contact::factory()->fromSource('angi', 4500)->create([
            'name' => 'Dana Whitfield',
            'phone_e164' => '+12025550123',
            'email' => 'dana.whitfield@example.com',
        ]);
        $conversation = Conversation::factory()->for($dana)->create();

        $first = $this->addMessage($conversation, 'My AC is blowing warm air', now()->subHours(5));
        $this->addActivity($dana, ActivityType::TextReceived, 'Text received', $first, 'angi', 4500);

        $reply = $this->addMessage($conversation, 'Sorry to hear that. Can we send someone tomorrow morning?', now()->subHours(4), fromOwner: true);
        $this->addActivity($dana, ActivityType::ReplySent, 'Reply sent by owner', $reply);

        $second = $this->addMessage($conversation, 'Tomorrow at 9 works. It is a Carrier unit, about eight years old.', now()->subHours(3));
        $this->addActivity($dana, ActivityType::TextReceived, 'Text received', $second, 'text');
    }

    /**
     * A Thumbtack lead who filled in the web form and gave a phone and an email.
     */
    private function seedMarcus(): void
    {
        $marcus = Contact::factory()->fromSource('thumbtack', 3200)->create([
            'name' => 'Marcus Lee',
            'phone_e164' => '+13125550162',
            'email' => 'marcus.lee@example.com',
        ]);
        $conversation = Conversation::factory()->for($marcus)->create();

        $message = $this->addMessage(
            $conversation,
            'Furnace is making a loud banging noise when it starts up.',
            now()->subDay(),
            web: true,
            meta: ['form' => [
                'name' => 'Marcus Lee',
                'phone' => '+13125550162',
                'email' => 'marcus.lee@example.com',
                'source' => 'thumbtack',
                'message' => 'Furnace is making a loud banging noise when it starts up.',
            ]],
        );
        $this->addActivity($marcus, ActivityType::FormReceived, 'Form received from Thumbtack', $message, 'thumbtack', 3200);
    }

    /**
     * A referral who became a customer; her conversation is finished and closed.
     */
    private function seedPriya(): void
    {
        $priya = Contact::factory()->customer()->fromSource('referral', 0)->create([
            'name' => 'Priya Shah',
            'phone_e164' => '+12025550188',
            'email' => 'priya.shah@example.com',
        ]);
        $conversation = Conversation::factory()->closed()->for($priya)->create();

        $question = $this->addMessage($conversation, 'Can I book the furnace tune-up for next week?', now()->subDays(9));
        $this->addActivity($priya, ActivityType::TextReceived, 'Text received', $question, 'referral', 0);

        $answer = $this->addMessage($conversation, 'You are booked for Tuesday at 2. See you then.', now()->subDays(9)->addMinutes(12), fromOwner: true);
        $this->addActivity($priya, ActivityType::ReplySent, 'Reply sent by owner', $answer);
        $this->addActivity($priya, ActivityType::Note, 'Conversation closed by owner', null, occurredAt: now()->subDays(8));
    }

    /**
     * A direct web lead with an email address and no phone, so the owner cannot text back.
     */
    private function seedSam(): void
    {
        $sam = Contact::factory()->withoutPhone()->fromSource('direct')->create([
            'name' => 'Sam Ortiz',
            'email' => 'sam.ortiz@example.com',
        ]);
        $conversation = Conversation::factory()->for($sam)->create();

        $message = $this->addMessage($conversation, 'Do you service mini-splits in Evanston?', now()->subHours(20), web: true);
        $this->addActivity($sam, ActivityType::FormReceived, 'Form received from direct', $message, 'direct');
    }

    /**
     * Put one message in a conversation at the given time, and move the conversation's and the
     * contact's last-activity markers forward.
     *
     * @param  array<string, mixed>|null  $meta
     */
    private function addMessage(Conversation $conversation, string $body, Carbon $at, bool $fromOwner = false, bool $web = false, ?array $meta = null): Message
    {
        $factory = Message::factory()->for($conversation);

        if ($fromOwner) {
            $factory = $factory->fromOwner();
        }

        if ($web) {
            $factory = $factory->web();
        }

        $message = $factory->create([
            'body' => $body,
            'meta' => $meta,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $conversation->update(['last_message_at' => $at]);
        $conversation->contact->update(['last_activity_at' => $at]);

        return $message;
    }

    /**
     * Add a timeline entry about a message, or a free-standing note when there is no message.
     * Only a first touch carries a lead source with a cost.
     */
    private function addActivity(Contact $contact, ActivityType $type, string $summary, ?Message $message, ?string $sourceKey = null, ?int $costCents = null, ?Carbon $occurredAt = null): Activity
    {
        return Activity::factory()->for($contact)->create([
            'type' => $type,
            'summary' => $summary,
            'subject_type' => $message?->getMorphClass(),
            'subject_id' => $message?->id,
            'lead_source_key' => $sourceKey,
            'cost_cents' => $costCents,
            'occurred_at' => $occurredAt ?? $message?->created_at ?? now(),
        ]);
    }
}
