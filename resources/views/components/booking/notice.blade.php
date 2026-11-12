@props(['heading', 'focus' => false])
<div {{ $attributes->class('space-y-6') }}>
    <h1 @if ($focus) tabindex="-1" x-init="$el.focus()" @endif class="break-words text-display font-semibold">{{ $heading }}</h1>
    <p class="break-words">{{ $slot }}</p>
    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
