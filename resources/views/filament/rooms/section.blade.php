<x-filament-widgets::widget>
    <div data-room-section="{{ $key }}">
        <x-filament::section :heading="$heading">
            @if ($emptyLine !== null)
                <p>{{ $emptyLine }}</p>
            @else
                @foreach ($lines as $line)
                    <p>{{ $line }}</p>
                @endforeach
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
