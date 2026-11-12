<div x-data="{ lastTime: null }">
    @if ($rateLimited)
        <x-booking.notice wire:key="rate-limited" heading="Please wait a moment" :focus="true">
            There have been too many tries. Wait a minute, then try again.
            <x-slot:action>
                <x-booking.button variant="secondary" wire:click="tryAgain" class="w-full sm:w-auto">Try again</x-booking.button>
            </x-slot:action>
        </x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::Picker)
        <div wire:key="picker" class="space-y-6">
            <h1 @if ($retried && ! $checking) tabindex="-1" x-init="$el.focus()" @endif class="break-words text-display font-semibold">Book {{ $serviceName }}</h1>

            <div id="booking-picker" wire:key="booking-picker-{{ $pickerVersion }}" @if ($checking) hidden @endif class="space-y-6">
                <p>Pick a time that works for you. {{ $zoneLine }}</p>

                @if ($error === 'taken')
                    <x-booking.error>That time was just taken. These times are open now.</x-booking.error>
                @endif

                <div class="space-y-2">
                    @foreach ($days as $day)
                        <x-booking.day :label="$day['label']" :open="$loop->first">
                            <ul @if ($error === 'taken') aria-describedby="booking-error" @endif class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($day['times'] as $time)
                                    <li class="flex">
                                        <x-booking.time :iso="$time['iso']" :time="$time['time']" :day="$time['day']" />
                                    </li>
                                @endforeach
                            </ul>
                        </x-booking.day>
                    @endforeach
                </div>
            </div>

            @if ($checking)
                <section class="space-y-6">
                    <h2 tabindex="-1" x-init="$el.focus()" class="break-words text-heading font-semibold">Check your time</h2>

                    <dl class="space-y-2">
                        <div>
                            <dt class="text-ink-muted">Service</dt>
                            <dd class="break-words font-semibold">{{ $serviceName }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted">Day</dt>
                            <dd class="font-semibold">{{ $chosenTime['day'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted">Time</dt>
                            <dd class="font-semibold">{{ $chosenTime['time'] }}</dd>
                        </div>
                    </dl>

                    <p class="text-ink-muted">{{ $zoneLine }}</p>

                    @if ($error === 'failed')
                        <x-booking.error>Your time was not booked. Try again, or text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" />.</x-booking.error>
                    @endif

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-booking.button wire:click="book" wire:loading.attr="aria-disabled" wire:target="book" :aria-describedby="$error === 'failed' ? 'booking-error' : null" class="w-full sm:w-auto">
                            <span wire:loading.remove wire:target="book">Book this time</span>
                            <span wire:loading wire:target="book">Booking your time</span>
                        </x-booking.button>
                        <x-booking.button variant="secondary" x-on:click="$wire.back().then(() => $nextTick(() => document.getElementById(lastTime)?.focus()))" class="w-full sm:w-auto">Choose a different time</x-booking.button>
                    </div>
                </section>
            @endif
        </div>
    @elseif ($state === \App\Enums\BookingPageState::Booked)
        <div wire:key="booked" class="space-y-6">
            <h1 @if ($justBooked || $retried) tabindex="-1" x-init="$el.focus()" @endif class="flex items-center gap-2 break-words text-display font-semibold">
                <x-booking.icon.check class="size-8 shrink-0" />
                <span>You are booked</span>
            </h1>

            <dl class="space-y-2">
                <div>
                    <dt class="text-ink-muted">Service</dt>
                    <dd class="break-words font-semibold">{{ $serviceName }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Day</dt>
                    <dd class="font-semibold">{{ $visit['day'] }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Time</dt>
                    <dd class="font-semibold">{{ $visit['time'] }}</dd>
                </div>
            </dl>

            <p class="text-ink-muted">{{ $zoneLine }}</p>

            <p class="break-words">Need to change this visit? Text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" />.</p>
        </div>
    @elseif ($state === \App\Enums\BookingPageState::Cancelled)
        <x-booking.notice wire:key="cancelled" heading="This visit was cancelled" :focus="$retried">To book another time, text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" />.</x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::Done)
        <x-booking.notice wire:key="done" heading="This visit is done" :focus="$retried">Thank you for choosing {{ $businessName }}.</x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::NoTimes)
        <x-booking.notice wire:key="no-times" heading="No times are open" :focus="$retried">We have no open times right now. Text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" /> and we will help you book.</x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::ServiceUnavailable)
        <x-booking.notice wire:key="unavailable" heading="We cannot book this online" :focus="$retried">Text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" /> and we will help.</x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::Expired)
        <x-booking.notice wire:key="expired" heading="This link has expired" :focus="$retried">Text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" /> to get a new one.</x-booking.notice>
    @elseif ($state === \App\Enums\BookingPageState::Replaced)
        <x-booking.notice wire:key="replaced" heading="This link was replaced" :focus="$retried">A newer link was sent to you. Open the latest text from {{ $businessName }}, or text us at <x-booking.sms :phone="$phone" :text="$phoneText" />.</x-booking.notice>
    @else
        <x-booking.notice wire:key="does-not-work" heading="This link does not work" :focus="$retried">Text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" /> and we will help.</x-booking.notice>
    @endif
</div>
