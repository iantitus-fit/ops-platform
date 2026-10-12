<?php

namespace App\Channels;

/**
 * Twilio's request signature, worked out by hand so no SDK is needed.
 *
 * Twilio signs every webhook: it joins the full URL it called with each form field's name and value (fields sorted by
 * name), runs that text through HMAC-SHA1 keyed with the account's auth token, and sends the base64 of the result in
 * the X-Twilio-Signature header. Computing the same value here and finding it equal proves the request came from
 * Twilio and the form was not changed on the way.
 */
class TwilioSignature
{
    /**
     * The signature Twilio would send for this URL and form.
     *
     * An array value (a field sent more than once) contributes its sorted values joined, the way Twilio's own
     * libraries do. Values are used exactly as received, so an emoji is signed as its UTF-8 bytes.
     *
     * @param  array<string, mixed>  $params
     */
    public static function compute(string $url, array $params, string $token): string
    {
        ksort($params);

        $data = $url;

        foreach ($params as $name => $value) {
            if (is_array($value)) {
                $value = array_map(strval(...), $value);
                sort($value);
                $value = implode('', $value);
            }

            $data .= $name.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }

    /**
     * Whether the signature fits any of the URLs.
     *
     * Each URL is also tried with and without its default port, because a proxy or tunnel may add or drop it between
     * what was typed into the Twilio Console and what Twilio signed (Pitfall 13). The comparison takes the same time
     * whether the first character or the last differs.
     *
     * @param  list<string>  $urls
     * @param  array<string, mixed>  $params
     */
    public static function matches(string $signature, array $urls, array $params, string $token): bool
    {
        if ($signature === '' || $token === '') {
            return false;
        }

        $matched = false;

        foreach ($urls as $url) {
            foreach (self::urlVariants($url) as $variant) {
                if (hash_equals(self::compute($variant, $params, $token), $signature)) {
                    $matched = true;
                }
            }
        }

        return $matched;
    }

    /**
     * The URL as written, then the same URL with its default port added or removed (443 for https, 80 for http).
     *
     * @return list<string>
     */
    public static function urlVariants(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return [$url];
        }

        $defaultPort = match (strtolower($parts['scheme'])) {
            'https' => 443,
            'http' => 80,
            default => null,
        };

        if ($defaultPort === null) {
            return [$url];
        }

        $rest = ($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        $userInfo = isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '';
        $origin = $parts['scheme'].'://'.$userInfo.$parts['host'];

        $other = match (true) {
            ($parts['port'] ?? null) === $defaultPort => $origin.$rest,
            isset($parts['port']) => null,
            default => $origin.':'.$defaultPort.$rest,
        };

        return $other === null ? [$url] : [$url, $other];
    }
}
