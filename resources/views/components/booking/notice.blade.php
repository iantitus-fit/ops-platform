@props(['heading'])
<div class="space-y-6">
    <h1 class="break-words text-display font-semibold">{{ $heading }}</h1>
    <p class="break-words">{{ $slot }}</p>
</div>
