<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No client data exists, but a development database may hold duplicate alerts from before
     * the key: the same conversation, message, reason and person stored twice. Each conversation
     * alert gets its key here, in id order, and the lowest id of a duplicate group keeps it, so
     * the unique index can be created; the later duplicates stay as history with no key. Alerts
     * about the whole business have no conversation and stay without a key (06 D-08). The key is
     * written inline in the format OwnerAlert::dedupeKey() uses, because a migration should not
     * call app code that may change later. Running it again changes nothing.
     */
    public function up(): void
    {
        $held = DB::table('owner_alerts')
            ->whereNotNull('dedupe_key')
            ->pluck('dedupe_key')
            ->flip()
            ->all();

        DB::table('owner_alerts')
            ->whereNotNull('conversation_id')
            ->whereNull('dedupe_key')
            ->chunkById(500, function ($alerts) use (&$held): void {
                foreach ($alerts as $alert) {
                    $key = 'c'.$alert->conversation_id.':m'.($alert->message_id ?? 0).':'.$alert->reason.':u'.($alert->user_id ?? 0);

                    if (isset($held[$key])) {
                        continue;
                    }

                    $held[$key] = true;

                    DB::table('owner_alerts')->where('id', $alert->id)->update(['dedupe_key' => $key]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the column is dropped by 2026_10_27_000002_add_dedupe_key_to_owner_alerts_table.
     */
    public function down(): void
    {
        //
    }
};
