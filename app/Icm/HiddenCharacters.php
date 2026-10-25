<?php

namespace App\Icm;

/**
 * The one definition of the characters a file or folder name may not hold (05 D-19, D-25), so the check at load time and
 * the mark at print time can never disagree about which characters count.
 *
 * The four Unicode categories are Cc (control characters: line breaks, tabs), Cf (format characters: text-direction
 * overrides, zero-width and tag characters), Zl and Zp (the line and paragraph separators). Together they cover what can
 * start a forged line in changelog.md or make one name look like another. The zero-width joiner and non-joiner are
 * Cf, so they are refused too (D-25). A combining mark such as the accent in a decomposed e is category Mn, and a
 * no-break space is Zs, so ordinary non-English names and both ways a disk can spell café pass untouched.
 *
 * The marks are placeholder wording until the wording pass.
 */
final class HiddenCharacters
{
    /**
     * Any one character of the four categories. The u flag makes PCRE read the text as UTF-8.
     */
    public const PATTERN = '/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u';

    /**
     * The same set, with a line break (CR LF as one unit, or one LF, CR, NEL, U+2028 or U+2029) matched first so it can be
     * told apart from the rest.
     */
    private const MARKING_PATTERN = '/\r\n|[\n\r\x{0085}\x{2028}\x{2029}]|[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u';

    /**
     * Whether the text holds a hidden character. Text that is not valid UTF-8 makes preg_match return false, not 0, and
     * counts as a hit: a name the engine cannot read is never let through.
     */
    public static function found(string $text): bool
    {
        return preg_match(self::PATTERN, $text) !== 0;
    }

    /**
     * The text with each hidden character replaced by a plain-words mark: [line break] for a line break, [hidden character]
     * for any other. The text is scrubbed first, so broken bytes become a question mark and the result is always valid
     * UTF-8 and never empty because of them.
     */
    public static function marked(string $text): string
    {
        $scrubbed = mb_scrub($text, 'UTF-8');

        $marked = preg_replace_callback(
            self::MARKING_PATTERN,
            fn (array $match): string => preg_match('/\A(?:\r\n|[\n\r\x{0085}\x{2028}\x{2029}])\z/u', $match[0]) === 1 ? '[line break]' : '[hidden character]',
            $scrubbed,
        );

        return $marked ?? (string) preg_replace(self::PATTERN, '[hidden character]', $scrubbed);
    }
}
