<?php

/**
 * IN-02 (08-REVIEW.md): only SendBookingText may pass the booking text kind. OutboundSender lets a booking text out with
 * no reading of the model, because D-26 allows it for a text built from an owner line with no model words, and it trusts
 * the caller's meta parts. Any other class that named the kind would skip the safety reading, so the files under app/
 * that name it are listed here: SendBookingText passes it, OutboundSender reads it to route the check, and MessageKind
 * defines it. A source scan finds the files, and the snippets below prove each way of naming the kind is caught, so the
 * scan cannot pass because it never matches (the AppointmentWritesTest precedent).
 *
 * Unit tests do not boot Laravel, so paths come from dirname(__DIR__, 2) and plain PHP file functions.
 */

/**
 * Whether the source names the booking text kind, by its enum case or by its string value in quotes. A longer string that
 * only starts with it, such as a room entry key, does not count.
 */
function bookingTextKindNamedIn(string $source): bool
{
    return preg_match('/MessageKind::BookingText(?![A-Za-z0-9_])/', $source) === 1
        || preg_match('/([\'"])booking_text\1/', $source) === 1;
}

/**
 * Every file under app/ that names the booking text kind, as sorted paths relative to the project root.
 *
 * @return list<string>
 */
function bookingTextKindFilesUnderApp(): array
{
    $root = dirname(__DIR__, 2);
    $named = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        if (bookingTextKindNamedIn((string) file_get_contents($file->getPathname()))) {
            $named[] = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root.'/app/')));
        }
    }

    sort($named);

    return $named;
}

it('only SendBookingText passes the booking text kind', function () {
    expect(bookingTextKindFilesUnderApp())->toBe([
        'app/Actions/OutboundSender.php',
        'app/Actions/SendBookingText.php',
        'app/Enums/MessageKind.php',
    ]);
});

it('flags every way to name the kind', function (string $snippet) {
    expect(bookingTextKindNamedIn($snippet))->toBeTrue();
})->with([
    'a meta kind set to the enum case' => ["\$attributes = ['kind' => MessageKind::BookingText];"],
    'a kind given as the string in single quotes' => ["\$attributes = ['kind' => 'booking_text'];"],
    'from with the string in double quotes' => ['$kind = MessageKind::from("booking_text");'],
    'tryFrom with the string in single quotes' => ["\$kind = MessageKind::tryFrom('booking_text');"],
]);

it('passes names that only look like it', function (string $snippet) {
    expect(bookingTextKindNamedIn($snippet))->toBeFalse();
})->with([
    'the room entry key' => ["\$entry = 'booking_text_not_sent';"],
    'the action class' => ['$sender = SendBookingText::class;'],
    'a job name' => ['dispatch(new SendDueBookingTexts);'],
]);
