<?php

namespace App\Records;

/**
 * Makes customer-written text safe to put in a records/ file.
 *
 * The files are read by the owner's AI, so a customer's words must never pass for a heading,
 * a table row or an instruction. Block text is quoted line by line; anything that has to sit
 * in a heading, bullet or table cell is cut to one line and has its markdown characters escaped.
 */
class MarkdownText
{
    /**
     * One line of text for a heading, bullet or table cell.
     *
     * All whitespace, line breaks included, becomes single spaces. The text is cut to $limit
     * characters, ellipsis included, before its markdown characters are backslash-escaped,
     * so the escaping can never be cut in half.
     */
    public function inline(?string $text, int $limit = 120): string
    {
        $line = $this->oneLine($text);

        if (mb_strlen($line) > $limit) {
            $line = rtrim(mb_substr($line, 0, max($limit - 1, 0))).'…';
        }

        return strtr($line, [
            '\\' => '\\\\',
            '`' => '\\`',
            '*' => '\\*',
            '_' => '\\_',
            '[' => '\\[',
            ']' => '\\]',
            '<' => '\\<',
            '>' => '\\>',
            '#' => '\\#',
            '|' => '\\|',
        ]);
    }

    /**
     * The text on one line, whitespace collapsed, with nothing escaped. Only for text that is
     * about to go inside a quote block.
     */
    public function oneLine(?string $text): string
    {
        $collapsed = preg_replace('/[\s\p{Z}\p{Cc}]+/u', ' ', mb_scrub((string) $text));

        return trim((string) $collapsed);
    }

    /**
     * A block quote: every line of the text, empty lines included, prefixed with "> ".
     *
     * Lines are split on every kind of line ending. Inside a quote nothing is escaped, so the
     * customer's words stay readable and cannot start anything outside the quote.
     */
    public function quote(string $text): string
    {
        $text = rtrim(mb_scrub($text));

        if ($text === '') {
            return '> (no text)';
        }

        return implode("\n", array_map(
            fn (string $line): string => '> '.$line,
            preg_split('/\R/u', $text) ?: [],
        ));
    }

    /**
     * One table cell: a single escaped line, so a pipe or line break cannot break the row.
     */
    public function tableCell(?string $text, int $limit = 80): string
    {
        return $this->inline($text, $limit);
    }
}
