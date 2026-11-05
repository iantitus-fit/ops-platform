<div x-data="{ lastTime: null }">
    @if ($state === \App\Enums\BookingPageState::Picker)
        <div class="space-y-6">
            <h1 class="break-words text-display font-semibold">Book {{ $serviceName }}</h1>

            <div id="booking-picker" wire:key="booking-picker-{{ $pickerVersion }}" @if ($checking) hidden @endif class="space-y-6">
                <p>Pick a time that works for you. {{ $zoneLine }}</p>

                <div class="space-y-2">
                    @foreach ($days as $day)
                        <x-booking.day :label="$day['label']" :open="$loop->first">
                            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
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

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-booking.button wire:click="book" class="w-full sm:w-auto">Book this time</x-booking.button>
                        <x-booking.button variant="secondary" x-on:click="$wire.back().then(() => $nextTick(() => document.getElementById(lastTime)?.focus()))" class="w-full sm:w-auto">Choose a different time</x-booking.button>
                    </div>
                </section>
            @endif
        </div>
    @elseif ($state === \App\Enums\BookingPageState::Booked)
        <div class="space-y-6">
            <h1 @if ($justBooked) tabindex="-1" x-init="$el.focus()" @endif class="flex items-center gap-2 break-words text-display font-semibold">
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
    @endif
</div>
