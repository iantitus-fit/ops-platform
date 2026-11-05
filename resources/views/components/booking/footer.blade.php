@props(['businessName', 'phone', 'phoneText'])
<footer class="bg-surface">
    <div class="mx-auto max-w-xl space-y-2 px-4 py-8">
        <details class="group border-2 border-line bg-page">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-2 font-semibold text-ink hover:border-accent">
                <span>Privacy notice</span>
                <x-booking.icon.chevron class="size-6 shrink-0 group-open:rotate-180 motion-safe:transition-transform" />
            </summary>
            <p class="break-words px-4 pb-4 pt-2 text-ink-muted">This is placeholder wording for the example business. This page uses your link to book one visit. It does not ask for your name, phone number or address.</p>
        </details>

        <details class="group border-2 border-line bg-page">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-2 font-semibold text-ink hover:border-accent">
                <span>Accessibility statement</span>
                <x-booking.icon.chevron class="size-6 shrink-0 group-open:rotate-180 motion-safe:transition-transform" />
            </summary>
            <p class="break-words px-4 pb-4 pt-2 text-ink-muted">This page is built to WCAG 2.2 Level AA. If something does not work for you, text {{ $businessName }} at <x-booking.sms :phone="$phone" :text="$phoneText" /> and we will help.</p>
        </details>
    </div>
</footer>
