<x-filament-widgets::widget>
    <div data-room-section="{{ $key }}">
        <x-filament::section :heading="$heading">
            @if ($emptyLine !== null)
                <p>{{ $emptyLine }}</p>
            @else
                @if ($rows !== [])
                    <ul>
                        @foreach ($rows as $row)
                            <li data-leads-row>
                                <x-filament::link :href="$row['url']">{{ $row['contact'] }}</x-filament::link>: {{ $row['label'] }}, opened <time datetime="{{ $row['datetime'] }}">{{ $row['opened'] }}</time>, {{ $row['origin'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                @foreach ($lines as $line)
                    <p>{{ $line }}</p>
                @endforeach
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
