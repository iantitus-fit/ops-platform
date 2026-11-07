<?php

namespace App\Livewire;

use App\Actions\BookAppointment;
use App\Enums\BookingPageState;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Rooms\ScheduleAndBooking\Availability;
use App\Rooms\ScheduleAndBooking\BookingLinkLookup;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\Rooms\ScheduleAndBooking\ResolveBookingLink;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The page a booking link opens (BOOK-02): the times the server offers, a check step, and the one booking, with nothing
 * typed. It is the customer's own action and writes only through BookAppointment::fromLink() (D-04, D-15).
 *
 * Nothing the browser sends is trusted. The token, the chosen time and every other value the page keeps are #[Locked],
 * so Livewire refuses a browser that tries to change them, and a tapped time is checked again by Availability on the
 * server before it is kept and again, under the lock, when it is booked (T-08-32).
 */
#[Layout('layouts.customer')]
class BookingPage extends Component
{
    #[Locked]
    public string $token = '';

    /** The tapped time, as an ISO instant the server set after checking it, or null while the picker shows. */
    #[Locked]
    public ?string $chosen = null;

    /** True after a booking made in this visit, so focus moves to the confirmation only then. */
    #[Locked]
    public bool $justBooked = false;

    /** The error line the page shows: 'taken' (the time went) or 'failed' (anything else on Book), or null. */
    #[Locked]
    public ?string $error = null;

    /** Raised when a fresh list is drawn, so the picker is made again and its earliest day opens. */
    #[Locked]
    public int $pickerVersion = 0;

    /** True when this token and address have tapped too often in the last minute (U-19). */
    #[Locked]
    public bool $rateLimited = false;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    /**
     * A time was tapped: keep it for the check step when the server offers it, else draw fresh times with the taken
     * line (D-07).
     */
    public function choose(string $startIso): void
    {
        if (! $this->withinLimit()) {
            return;
        }

        $now = CarbonImmutable::now();
        $lookup = app(ResolveBookingLink::class)->handle($this->token, $now);

        if ($lookup->state !== BookingPageState::Picker || $lookup->service === null) {
            $this->chosen = null;

            return;
        }

        $start = $this->instantFrom($startIso);

        if ($start === null || ! app(Availability::class)->offers($lookup->service, $start, $now)) {
            $this->showFreshTimes();

            return;
        }

        $this->error = null;
        $this->chosen = $start->utc()->toIso8601ZuluString();
    }

    /**
     * "Choose a different time": the picker comes back with the day the customer had open.
     */
    public function back(): void
    {
        $this->chosen = null;
        $this->error = null;
    }

    /**
     * "Book this time": the one write the page makes. A time taken meanwhile gives fresh times; any other failure keeps
     * the check step with a fixed plain line, and the exception text never reaches the page (T-08-37).
     */
    public function book(BookAppointment $bookAppointment): void
    {
        if (! $this->withinLimit() || $this->chosen === null) {
            return;
        }

        $now = CarbonImmutable::now();
        $link = app(ResolveBookingLink::class)->handle($this->token, $now)->link;

        if ($link === null) {
            $this->chosen = null;

            return;
        }

        try {
            $bookAppointment->fromLink($link, CarbonImmutable::parse($this->chosen), $now);

            $this->justBooked = true;
            $this->chosen = null;
            $this->error = null;
        } catch (BookingRefused $refused) {
            $this->refuse($refused);
        } catch (Throwable $exception) {
            report($exception);

            $this->error = 'failed';
        }
    }

    public function render(): View
    {
        $config = app(ActiveConfig::class);

        try {
            $business = $config->business();
            $zone = $config->timezone();
        } catch (NoActiveVersion) {
            abort(404);
        }

        $lookup = app(ResolveBookingLink::class)->handle($this->token, CarbonImmutable::now());
        $state = $lookup->state;
        $checking = $this->chosen !== null && $state === BookingPageState::Picker;
        $serviceName = (string) ($lookup->service['name'] ?? $lookup->appointment?->service_name ?? '');
        $businessName = (string) $business['name'];
        $phone = (string) $business['phone'];
        $phoneText = PhoneNumber::national($phone);

        return view('livewire.booking-page', [
            'state' => $state,
            'checking' => $checking,
            'businessName' => $businessName,
            'serviceName' => $serviceName,
            'rateLimited' => $this->rateLimited,
            'phone' => $phone,
            'phoneText' => $phoneText,
            'zoneLine' => 'All times are in '.BookingSettings::zoneName($zone).'.',
            'days' => $state === BookingPageState::Picker ? $this->daysFrom($lookup, $zone) : [],
            'chosenTime' => $checking ? $this->visitFrom(CarbonImmutable::parse((string) $this->chosen), $zone) : null,
            'visit' => $lookup->appointment !== null ? $this->visitFrom($lookup->appointment->starts_at, $zone) : null,
        ])
            ->title($this->titleFor($this->rateLimited ? null : $state, $serviceName, $businessName))
            ->layoutData(['businessName' => $businessName, 'phone' => $phone, 'phoneText' => $phoneText]);
    }

    /**
     * Draw fresh times and say the one the customer wanted went (D-07).
     */
    private function showFreshTimes(): void
    {
        $this->chosen = null;
        $this->error = 'taken';
        $this->pickerVersion++;
    }

    /**
     * A refusal from the booking action: a taken time gives fresh times, a closed link just draws its new state, and
     * anything else keeps the check step with the failed line.
     */
    private function refuse(BookingRefused $refused): void
    {
        $message = $refused->getMessage();

        if ($message === BookingRefused::taken()->getMessage()) {
            $this->showFreshTimes();

            return;
        }

        if ($message === BookingRefused::linkClosed()->getMessage()) {
            $this->chosen = null;

            return;
        }

        $this->error = 'failed';
    }

    /**
     * The browser's tapped value as an instant, or null when it is not an ISO instant at all.
     */
    private function instantFrom(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The offered starts as the page draws them: a day label, and each time with its ISO value, its text and its day.
     *
     * @return list<array{label: string, times: list<array{iso: string, time: string, day: string}>}>
     */
    private function daysFrom(BookingLinkLookup $lookup, string $zone): array
    {
        $days = [];

        foreach ($lookup->openStarts as $starts) {
            $days[] = [
                'label' => BookingSettings::dayText($starts[0], $zone),
                'times' => array_map(fn (CarbonImmutable $start): array => [
                    'iso' => $start->utc()->toIso8601ZuluString(),
                    'time' => $this->timeLabel($start, $zone),
                    'day' => BookingSettings::dayText($start, $zone),
                ], $starts),
            ];
        }

        return $days;
    }

    /**
     * A visit's day ("Tuesday, November 3, 2026") and time ("10:00 AM"), in the business's zone.
     *
     * @return array{day: string, time: string}
     */
    private function visitFrom(CarbonInterface $at, string $zone): array
    {
        return [
            'day' => BookingSettings::dayText($at, $zone).', '.CarbonImmutable::instance($at)->setTimezone($zone)->year,
            'time' => $this->timeLabel($at, $zone),
        ];
    }

    /**
     * The time with a non-breaking space before AM or PM, so it never breaks across two lines.
     */
    private function timeLabel(CarbonInterface $at, string $zone): string
    {
        return str_replace(' ', "\u{00A0}", BookingSettings::timeText($at, $zone));
    }

    /**
     * The page title: the service on the picker, "Your visit" for a visit, otherwise the heading the page shows. A null
     * state is the rate-limited notice.
     */
    private function titleFor(?BookingPageState $state, string $serviceName, string $businessName): string
    {
        $heading = match ($state) {
            null => 'Please wait a moment',
            BookingPageState::Picker => 'Book '.$serviceName,
            BookingPageState::Booked, BookingPageState::Cancelled, BookingPageState::Done => 'Your visit',
            BookingPageState::NoTimes => 'No times are open',
            BookingPageState::ServiceUnavailable => 'We cannot book this online',
            BookingPageState::Expired => 'This link has expired',
            BookingPageState::Replaced => 'This link was replaced',
            BookingPageState::DoesNotWork => 'This link does not work',
        };

        return $heading.' | '.$businessName;
    }

    /**
     * Count this tap against the page's own limit: 20 a minute for one link from one address. The route's limiter only
     * covers opening the page, and every tap after that is a request to Livewire, so the count is kept here (U-19,
     * T-08-33). The limit lifts by itself when the minute is up.
     */
    private function withinLimit(): bool
    {
        $key = 'booking:'.hash('sha256', $this->token).':'.request()->ip();

        $this->rateLimited = ! RateLimiter::attempt($key, 20, fn (): bool => true, 60);

        return ! $this->rateLimited;
    }
}
