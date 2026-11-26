<?php

namespace App\Ai;

use InvalidArgumentException;

/**
 * A Booking section wording measured beside the bars (10 D-18), supplied as data to walks:record only.
 *
 * Ian's live run records a walk once with the prompt as it is built and once for each wording he wants to compare. A
 * wording is a JSON file holding a name and the Booking section's two texts; walks:record binds it for one walk, and
 * FrontDeskPrompt reads its two texts in place of its constants while it is bound. The shipped texts stay the constants in
 * FrontDeskPrompt, so a wording never changes what customers' conversations read, and the name keeps its recording apart
 * from the one the sync replays. 08 WR-07 stands: a wording holds no owner line and no booking address.
 */
readonly class BookingSectionWording
{
    /**
     * @param  string  $name  What the recording is stored under, apart from as_built.
     * @param  string  $online  The text for a customer with no visit ahead, with {services} once where the list of services goes.
     * @param  string  $visitBooked  The text for a customer with a visit booked ahead.
     */
    public function __construct(
        public string $name,
        public string $online,
        public string $visitBooked,
    ) {}

    /**
     * Build a wording from the decoded JSON of a wording file, or refuse it in plain words naming the field.
     *
     * @param  array<array-key, mixed>  $data  The file's name, booking_online and booking_visit_booked.
     *
     * @throws InvalidArgumentException when a field cannot be used
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;

        if ($name === 'as_built') {
            throw new InvalidArgumentException('The wording name cannot be as_built, because the sync replays that recording. Use another name.');
        }

        if (! is_string($name) || preg_match('/\A[a-z0-9-]+\z/', $name) !== 1) {
            throw new InvalidArgumentException('The wording name must use only lowercase letters, digits and dashes, such as shorter-text.');
        }

        $online = $data['booking_online'] ?? null;

        if (! is_string($online) || trim($online) === '') {
            throw new InvalidArgumentException('booking_online must be text.');
        }

        if (substr_count($online, '{services}') !== 1) {
            throw new InvalidArgumentException('booking_online must hold {services} exactly once, where the list of services goes.');
        }

        $visitBooked = $data['booking_visit_booked'] ?? null;

        if (! is_string($visitBooked) || trim($visitBooked) === '') {
            throw new InvalidArgumentException('booking_visit_booked must be text.');
        }

        return new self($name, $online, $visitBooked);
    }
}
