@props(['phone', 'text'])
<a href="sms:{{ $phone }}" class="text-accent underline hover:text-accent-strong">{{ $text }}</a>