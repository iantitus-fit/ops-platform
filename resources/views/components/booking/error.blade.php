@props(['id' => 'booking-error'])
<p id="{{ $id }}" tabindex="-1" x-init="$el.focus()" class="flex items-start gap-2 break-words font-semibold text-danger">
    <x-booking.icon.exclamation-circle class="size-6 shrink-0" />
    <span>{{ $slot }}</span>
</p>
