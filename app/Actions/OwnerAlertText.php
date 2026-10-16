<?php

namespace App\Actions;

use App\Enums\AlertReason;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\Message;
use Filament\Facades\Filament;

/**
 * Builds the text an owner alert carries, in code with no model call (D-21).
 *
 * An alert quotes words a stranger typed and then travels to the owner's phone next to a link
 * the owner is meant to tap. If the stranger's words could carry a tappable link, a customer
 * could write an "alert" of their own (Pitfall 16). So everything quoted goes through clean(),
 * which swaps links, email addresses and long digit runs for placeholders, and the dashboard
 * link is added last, on its own line, by us.
 */
class OwnerAlertText
{
    /**
     * The longest stretch of the contact's name an alert shows.
     */
    public const int NAME_LENGTH = 40;

    /**
     * The longest stretch of the customer's last text an alert quotes.
     */
    public const int CUSTOMER_LENGTH = 120;

    /**
     * The longest stretch of the waiting draft an alert quotes.
     */
    public const int DRAFT_LENGTH = 160;

    /**
     * Endings a phone turns into a tappable link even without "www." or "http".
     */
    private const string COMMON_ENDINGS = 'com|net|org|io|co|us|info|biz|app|dev|ly|me|xyz|gov|edu|ca|uk|online|site|shop|link|click|top';

    /**
     * Assemble the alert: who and why, what the customer last wrote, the draft when there is
     * one, a blank line, and the link to the conversation (D-22: the conversation, never a
     * draft, so an older alert still opens the newest draft).
     */
    public function build(Conversation $conversation, AlertReason $reason, ?Message $subject, ?string $why): string
    {
        $contact = $conversation->contact;

        $name = $this->cut($this->clean((string) $contact->name), self::NAME_LENGTH);
        $who = $name !== '' ? $name : ($contact->phone_e164 ?: 'A customer');
        $lines = ["{$who}: ".(filled($why) ? trim($why) : $reason->plainWords())];

        $customerText = $this->cut($this->clean($this->lastCustomerText($conversation)), self::CUSTOMER_LENGTH);

        if ($customerText !== '') {
            $lines[] = "They wrote: \"{$customerText}\"";
        }

        if ($subject !== null && $subject->status === MessageStatus::Draft) {
            $draft = $this->cut($this->clean($subject->body), self::DRAFT_LENGTH);

            if ($draft !== '') {
                $lines[] = "Draft: \"{$draft}\"";
            }
        }

        $lines[] = '';
        $lines[] = ConversationResource::getUrl('view', ['record' => $conversation]);

        return implode("\n", $lines);
    }

    /**
     * Assemble an alert about the business rather than one conversation (D-50): the reason's
     * words, the why, a blank line, and the link to the dashboard added last. It quotes nothing
     * a customer wrote, so it needs no clean().
     */
    public function forBusiness(AlertReason $reason, string $why): string
    {
        return $reason->plainWords().': '.trim($why)."\n\n".Filament::getUrl();
    }

    /**
     * Make words a stranger typed safe to quote in an alert: links become [link], email
     * addresses become [email], runs of seven or more digits (with the spaces, dashes, dots,
     * brackets and plus signs a phone number is written with) become [number], and all
     * whitespace collapses to single spaces.
     *
     * Order matters: a web address with a user name in it is swapped whole before emails are
     * looked for, and emails go before bare domains so "dana@example.com" is not left as
     * "dana@[link]".
     */
    public function clean(string $text): string
    {
        $text = mb_scrub($text);
        $endings = self::COMMON_ENDINGS;

        $text = (string) preg_replace('~\b(?:https?|ftp)://\S+~iu', '[link]', $text);
        $text = (string) preg_replace('~[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+~u', '[email]', $text);
        $text = (string) preg_replace('~\bwww\.\S+~iu', '[link]', $text);
        $text = (string) preg_replace('~\b(?:[a-z0-9-]+\.)+[a-z]{2,}/\S*~iu', '[link]', $text);
        $text = (string) preg_replace("~\\b(?:[a-z0-9-]+\\.)+(?:{$endings})\\b~iu", '[link]', $text);
        $text = (string) preg_replace_callback(
            '~\+?[\d(][\d\s().\-]*\d~u',
            fn (array $match): string => preg_match_all('/\d/', $match[0]) >= 7 ? '[number]' : $match[0],
            $text,
        );

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Cut to at most $length characters. A longer text keeps $length minus three characters and
     * ends with three plain dots: a single "..." character would push the whole alert into the
     * longer, costlier text encoding.
     */
    private function cut(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 3)).'...';
    }

    private function lastCustomerText(Conversation $conversation): string
    {
        $message = $conversation->messages()
            ->reorder('id', 'desc')
            ->where('direction', MessageDirection::In->value)
            ->first();

        return $message->body ?? '';
    }
}
