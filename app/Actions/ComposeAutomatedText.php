<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;

/**
 * Puts the AI disclosure and the sign-off around the words an agent wrote (D-50).
 *
 * The disclosure opens the first automated text in each conversation and the sign-off closes every one. Both
 * come from the voice file through ActiveConfig, so the owner changes them in the folder.
 */
class ComposeAutomatedText
{
    public function __construct(private readonly ActiveConfig $activeConfig) {}

    /**
     * The text as the customer receives it: the disclosure and a blank line when this is the first automated
     * text of the conversation, then the words, then a new line and the sign-off.
     */
    public function forReply(Conversation $conversation, string $text): string
    {
        $voice = $this->activeConfig->voice();
        $composed = trim($text)."\n".$voice['sign_off'];

        if ($this->isFirstAutomatedText($conversation)) {
            return $voice['ai_disclosure']."\n\n".$composed;
        }

        return $composed;
    }

    /**
     * True when no automated text has reached, or is on its way to, the customer in this conversation. Drafts
     * and failed sends do not count, because the customer never saw them.
     */
    public function isFirstAutomatedText(Conversation $conversation): bool
    {
        return ! $conversation->messages()
            ->where('direction', MessageDirection::Out)
            ->where('author', MessageAuthor::Agent)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->exists();
    }
}
