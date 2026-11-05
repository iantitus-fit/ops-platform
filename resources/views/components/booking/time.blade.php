@props(['iso', 'time', 'day'])
<button type="button" id="booking-time-{{ preg_replace('/[^0-9A-Za-z]/', '', $iso) }}" x-on:click="lastTime = $el.id" wire:click="choose('{{ $iso }}')" {{ $attributes->class(['min-h-12 min-w-12 w-full border-2 border-line bg-page px-4 py-2 text-center font-semibold text-ink hover:border-accent']) }}>{{ $time }}<span class="sr-only">, {{ $day }}</span></button>
