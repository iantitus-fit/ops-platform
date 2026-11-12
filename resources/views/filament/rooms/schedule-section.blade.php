<x-filament-widgets::widget>
    <div data-room-section="{{ $key }}">
        <x-filament::section :heading="$heading">
            @if ($notice !== null)
                <p>{{ $notice }}</p>
            @endif
            @if ($rows !== [])
                <ul>
                    @foreach ($rows as $row)
                        <li data-schedule-row>
                            <x-filament::link :href="$row['url']">{{ $row['contact'] }}</x-filament::link>: <time datetime="{{ $row['datetime'] }}">{{ $row['when'] }}</time>, {{ $row['service'] }}, {{ $row['job'] }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @foreach ($lines as $line)
                <p>{{ $line }}</p>
            @endforeach
            @if ($moreUrl !== null)
                <p><x-filament::link :href="$moreUrl">Open the schedule</x-filament::link></p>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
