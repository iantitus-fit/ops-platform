<?php

namespace App\Actions;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The owner holds a span of time for themselves, such as the truck being in the shop (D-16). One of the named writers of
 * the appointments table (D-15). It is not an agent action: no model decides a block, so there is no checks-passed
 * record, and tests/Unit/AppointmentWritesTest.php keeps App\Ai and App\SendRule from using it.
 *
 * A block has no contact, job, service or text, so it writes no timeline entry (decided gap 2): who set it and when are
 * stored on the row. It holds time like a booked appointment, so Availability counts it. A block over a booked visit is
 * refused, naming the visit (decided gap 1); a block may overlap another block. The overlap is checked inside the same
 * 'schedule:book' lock and one transaction that bookings use (D-07, T-08-58), so a block and a booking for the same time
 * never both store. lockForUpdate() does nothing on SQLite, so the cache lock is the guard on both drivers.
 */
class BlockTime
{
    /**
     * The most characters a block note may hold.
     */
    public const int NOTE_MAX_LENGTH = 100;

    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * Hold the span from $startsAt up to $endsAt. Plain spans: no drive buffer applies to a block.
     *
     * @throws BookingRefused when the room is off, the end is not after the start, the start has passed, the note is too
     *                        long, a booked visit overlaps the span, or another booking holds the lock.
     */
    public function handle(User $user, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $note): ScheduleBlock
    {
        if (! $this->config->hasRoom('schedule_and_booking')) {
            throw BookingRefused::roomOff();
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw BookingRefused::endBeforeStart();
        }

        if ($startsAt->lessThanOrEqualTo(now())) {
            throw BookingRefused::pastStart();
        }

        $note = trim((string) $note);

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw BookingRefused::blockNoteTooLong();
        }

        $lock = Cache::lock('schedule:book', 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw BookingRefused::busy();
        }

        try {
            return DB::transaction(fn (): ScheduleBlock => $this->store($user, $startsAt, $endsAt, $note === '' ? null : $note));
        } finally {
            $lock->release();
        }
    }

    /**
     * Refuse a span that overlaps a booked visit, then write the row. Called inside the lock and a transaction. A visit
     * that ends exactly when the block starts, or starts exactly when it ends, does not overlap.
     */
    private function store(User $user, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $note): ScheduleBlock
    {
        $visit = Appointment::query()
            ->booked()
            ->where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
            ->with('contact')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();

        if ($visit !== null) {
            $zone = $this->config->timezone();

            throw BookingRefused::blockOverBooking(
                $visit->contact?->name ?: '(no name)',
                BookingSettings::dayText($visit->starts_at, $zone),
                BookingSettings::timeText($visit->starts_at, $zone),
            );
        }

        return ScheduleBlock::create([
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'state' => AppointmentState::Booked,
            'booked_by' => AppointmentActor::User,
            'booked_by_user_id' => $user->id,
            'booked_at' => now(),
            'note' => $note,
        ]);
    }
}
