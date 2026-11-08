<?php

use App\Events\CustomerRecordChanged;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Booking links sent before plan 08-17 have their whole address in stored texts, card files, session rows, backups
     * and the text provider's log, so they are revoked: from now on none of those copies can book (D-12). A link that
     * booked is left alone, because its page shows the customer their visit. The stored texts are not rewritten,
     * because rewriting cannot reach copies that already exist and would destroy what was sent. Cards are redrawn so the
     * folder holds /book/[link], since RecordsWriter now hides every booking address. It does nothing on an empty
     * database, so the first deploy is unaffected.
     */
    public function up(): void
    {
        $revokedIds = DB::table('booking_links')
            ->whereNull('revoked_at')
            ->whereNotExists(function ($appointments): void {
                $appointments->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.booking_link_id', 'booking_links.id');
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($revokedIds !== []) {
            DB::table('booking_links')
                ->whereIn('id', $revokedIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            Log::info('Revoked booking links sent before their tokens were hidden (D-12).', ['booking_link_ids' => $revokedIds]);
        }

        $contactIds = DB::table('booking_links')
            ->whereIn('id', $revokedIds)
            ->pluck('contact_id')
            ->merge(
                DB::table('messages')
                    ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                    ->where('messages.body', 'like', '%/book/%')
                    ->pluck('conversations.contact_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        foreach ($contactIds as $contactId) {
            try {
                CustomerRecordChanged::dispatch($contactId);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not undone on purpose: putting the links back would make every copy hold working links again. The revoked ids
     * are in the log, so one can be put back by hand.
     */
    public function down(): void
    {
        //
    }
};
