@props(['label', 'open' => false])
<details wire:ignore.self {{ $attributes->class(['group']) }} @if ($open) open @endif>
    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 border-2 border-line bg-page px-4 py-2 text-heading font-semibold text-ink hover:border-accent group-open:bg-surface">
        <span class="break-words">{{ $label }}</span>
        <x-booking.icon.chevron class="size-6 shrink-0 group-open:rotate-180 motion-safe:transition-transform" />
    </summary>
    <div class="pt-2">
        {{ $slot }}
    </div>
</details>
