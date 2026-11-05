<?php

use App\Actions\MintBookingLink;
use App\Icm\FolderSync;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| Helpers the booking page's test files share. Each test file loads this file itself, so any one of them runs alone.
*/

/**
 * A moment on the example business's wall clock (America/Chicago), as the customer taps it.
 */
function bookingPageChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Monday 2026-11-02 22:00 in the shop's zone (so the first open time is Tuesday
 * 10:00: the shop opens at 08:00 and the lead time is two hours), an owner with a phone, and Dana Whitfield, who
 * texted: her open conversation and Job 1 tied to it.
 *
 * @return array{0: Contact, 1: Conversation, 2: Project}
 */
function bookingPageSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(bookingPageChicago('2026-11-02 22:00'));

    User::factory()->withPhone('+12025550199')->create();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);
    $job = Project::factory()->create(['contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    return [$contact, $conversation, $job];
}

/**
 * Mint a link for the conversation and give it the outbound text that carried it, the way the send step does.
 *
 * @return array{0: BookingLink, 1: string}
 */
function bookingPageMint(Conversation $conversation, string $service = 'ac-repair'): array
{
    [$link, $token] = app(MintBookingLink::class)->handle($conversation, $service, CarbonImmutable::now());

    $message = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Here is the link to book.',
        'meta' => ['booking_link_id' => $link->id],
    ]);

    app(MintBookingLink::class)->attachMessage($link, $message);

    return [$link->fresh(), $token];
}

/**
 * The words a customer reads in a page's HTML: tags gone, entities decoded, every run of white space (a non-breaking
 * space too) one plain space.
 */
function bookingPageText(string $html): string
{
    $html = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
}

/**
 * A page's HTML as something to ask structural questions of.
 */
function bookingPageXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

/**
 * Change the live folder and make it live again, then hand out fresh scoped objects.
 */
function bookingPageResync(Closure $changeFolder): void
{
    $changeFolder();

    app(FolderSync::class)->run();
    app()->forgetScopedInstances();
}

/**
 * A page's HTML with the parts Livewire makes fresh on every request (its ids and snapshot) taken out, so two pages can
 * be compared.
 */
function bookingPageWithoutLivewireIds(string $html): string
{
    return preg_replace('/\swire:(snapshot|effects|id)="[^"]*"/', '', $html) ?? $html;
}
