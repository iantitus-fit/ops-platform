<?php

namespace App\Actions;

use App\Icm\ActiveConfig;
use App\Models\BookingLink;
use App\Models\Conversation;
use App\Models\Message;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Makes, replaces and takes back the booking links a customer is texted (D-12). Not an agent action: a link is the
 * platform's own record that a text carried a way to book, so there is no checks-passed record.
 *
 * The token is 256 random bits written as 64 hex characters, and only its sha256 hash is stored. A fast hash is right
 * for a secret that long and random, because it lets the booking page find the row by hashing what arrives in the URL;
 * bcrypt, the password precedent, is salted and so cannot be looked up from the token alone (research Pattern 2). The
 * plain token is returned once, to the caller that puts it in the text, and is never stored, logged or put in an
 * exception message.
 */
class MintBookingLink
{
    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * Mint a link for a conversation and a service. In one transaction the conversation's older links that are still
     * unbooked, unreplaced and unrevoked are marked superseded, and the new link is created, expiring after the booking
     * file's link_expires_days (D-12). Returns the link and the plain token.
     *
     * @return array{0: BookingLink, 1: string}
     *
     * @throws BookingRefused when the room is off or the folder has no booking file.
     */
    public function handle(Conversation $conversation, string $serviceSlug, CarbonImmutable $now): array
    {
        $settings = BookingSettings::fromConfig($this->config) ?? throw BookingRefused::roomOff();

        $token = bin2hex(random_bytes(32));

        $link = DB::transaction(function () use ($conversation, $serviceSlug, $now, $settings, $token): BookingLink {
            BookingLink::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('superseded_at')
                ->whereNull('revoked_at')
                ->whereDoesntHave('appointment')
                ->update(['superseded_at' => $now]);

            return BookingLink::query()->create([
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'service_slug' => $serviceSlug,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $now->addDays($settings->linkExpiresDays),
            ]);
        });

        return [$link, $token];
    }

    /**
     * Take a link back. It stops working at once and stays as a row, so a revoked link is told apart from one that
     * never existed. A link already revoked keeps its first time.
     */
    public function revoke(BookingLink $link): void
    {
        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
        }
    }

    /**
     * Record the text that carried the link, so a booking can alert the owner on it (D-33).
     */
    public function attachMessage(BookingLink $link, Message $message): void
    {
        $link->forceFill(['message_id' => $message->id])->save();
    }
}
