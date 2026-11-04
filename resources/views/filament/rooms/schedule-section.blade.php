<x-filament-widgets::widget>
    <div data-room-section="{{ $key }}">
        <x-filament::section :heading="$heading">
            @if ($notice !== null)
                <p>{{ $notice }}</p>
            @endif
            @foreach ($lines as $line)
                <p>{{ $line }}</p>
            @endforeach
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
