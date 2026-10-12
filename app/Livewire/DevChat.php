<?php

namespace App\Livewire;

use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Channels\InboundText;
use App\Channels\TextKeywords;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Contact;
use App\Models\Message;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A local-only page where Ian texts the business as a customer would.
 *
 * Texts go through the same InboundData and RecordInbound path as the fake webhook, in process
 * and never as an HTTP request to ourselves.
 */
#[Title('Dev chat')]
class DevChat extends Component
{
    public string $from = '+12025550123';

    public string $body = '';

    public function mount(): void
    {
        abort_unless(app()->environment('local'), 404);
    }

    /**
     * Record what was typed as a text from the given number, then clear the box.
     */
    public function send(RecordInbound $recordInbound, ActiveConfig $activeConfig): void
    {
        abort_unless(app()->environment('local'), 404);

        $this->validate(
            ['body' => 'required|string|max:1600'],
            [
                'body.required' => 'Type a message first.',
                'body.max' => 'Keep texts to 1,600 characters or fewer.',
            ],
        );

        $from = PhoneNumber::toE164($this->from);

        if ($from === null) {
            $this->addError('from', 'Use a real-format number like +12025550123.');

            return;
        }

        $inboundText = new InboundText(
            provider: 'fake',
            providerMessageId: 'dev-'.Str::uuid(),
            from: $from,
            to: $this->businessPhone($activeConfig),
            body: $this->body,
            numMedia: 0,
            optOutType: TextKeywords::twilioOptOutType($this->body),
        );

        $recordInbound->handle(InboundData::fromText($inboundText));

        $this->reset('body');
    }

    /**
     * The messages of the sender's open conversation, or of the latest one when none is open.
     * Empty when the number has never texted.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function thread(): Collection
    {
        $from = PhoneNumber::toE164($this->from);
        $contact = $from === null ? null : Contact::query()->where('phone_e164', $from)->first();
        $conversation = $contact?->openConversation ?? $contact?->conversations()->latest('id')->first();

        return $conversation === null ? new Collection : $conversation->messages()->get();
    }

    public function render(ActiveConfig $activeConfig): View
    {
        return view('livewire.dev-chat', [
            'timezone' => $activeConfig->displayTimezone(),
        ]);
    }

    /**
     * The business's own number, or null while no version is live.
     */
    private function businessPhone(ActiveConfig $activeConfig): ?string
    {
        try {
            return $activeConfig->business()['phone'] ?? null;
        } catch (NoActiveVersion) {
            return null;
        }
    }
}
