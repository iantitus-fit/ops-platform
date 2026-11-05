@props(['variant' => 'primary'])
<button type="button" {{ $attributes->class([
    'min-h-12 min-w-12 break-words border-2 px-4 py-2 text-center font-semibold',
    'border-accent bg-accent text-on-accent hover:border-accent-strong hover:bg-accent-strong' => $variant === 'primary',
    'border-line bg-page text-ink hover:border-accent' => $variant !== 'primary',
]) }}>{{ $slot }}</button>
