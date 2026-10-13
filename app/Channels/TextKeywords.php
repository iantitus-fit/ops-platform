<?php

namespace App\Channels;

/**
 * The words a carrier treats as keywords, matched the way Twilio matches them (D-39).
 *
 * Twilio compares the whole message, trimmed and without regard to case, so "Stop" is a keyword and "stop the noise
 * please" is an ordinary text. With a Messaging Service that has Advanced Opt-Out, Twilio tells the webhook what it
 * recognised in OptOutType and sends its own reply. This class does the same matching in code as a backup for a
 * provider that reports nothing, and for the fake channel, which has to behave like Twilio. The comparison is done in
 * PHP, never in SQL, because LIKE differs between SQLite and Postgres.
 */
class TextKeywords
{
    /**
     * Words that opt a number out.
     *
     * @var list<string>
     */
    public const array OPT_OUT_WORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'REVOKE', 'OPTOUT'];

    /**
     * Words that opt a number back in.
     *
     * @var list<string>
     */
    public const array OPT_IN_WORDS = ['START', 'YES', 'UNSTOP'];

    /**
     * Words that ask for help.
     *
     * @var list<string>
     */
    public const array HELP_WORDS = ['HELP', 'INFO'];

    /**
     * What Twilio would report in OptOutType for this text: STOP, START, HELP, or null when the text is no keyword.
     */
    public static function twilioOptOutType(string $body): ?string
    {
        $word = self::normalize($body);

        return match (true) {
            in_array($word, self::OPT_OUT_WORDS, true) => 'STOP',
            in_array($word, self::OPT_IN_WORDS, true) => 'START',
            in_array($word, self::HELP_WORDS, true) => 'HELP',
            default => null,
        };
    }

    /**
     * What the app does with a text: stop, start, help, or null when it is an ordinary message.
     *
     * The provider's own word is trusted first; with none, the same words are matched in code. YES is the careful
     * one (Pitfall 14): it opts a number back in only when the contact is opted out, so a plain "yes" answering an
     * intake question is never swallowed as a keyword.
     */
    public static function actionFor(?string $optOutType, string $body, bool $optedOut): ?string
    {
        $type = $optOutType === null || trim($optOutType) === ''
            ? self::twilioOptOutType($body)
            : mb_strtoupper(trim($optOutType));

        return match ($type) {
            'STOP' => 'stop',
            'START' => self::normalize($body) === 'YES' && ! $optedOut ? null : 'start',
            'HELP' => 'help',
            default => null,
        };
    }

    private static function normalize(string $body): string
    {
        return mb_strtoupper(trim($body));
    }
}
