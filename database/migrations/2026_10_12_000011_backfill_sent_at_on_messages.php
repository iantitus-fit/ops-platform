<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An approved or edited draft was sent the moment it was resolved (SendOwnerReply sets resolved_at just before
     * the channel call), and every other sent text was created as queued within a second of going out. So a text
     * that went out before sent_at existed gets resolved_at when it has one, else created_at. Rows that already have
     * a sent_at and rows that never went out are left alone. Kept separate from the migration that adds the column
     * so a test can run it on rows (the 02-08 precedent).
     */
    public function up(): void
    {
        DB::table('messages')
            ->whereNull('sent_at')
            ->whereIn('status', ['sent', 'delivered'])
            ->update(['sent_at' => DB::raw('coalesce(resolved_at, created_at)')]);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the column is dropped by 2026_10_12_000010_add_sent_at_to_messages_table.
     */
    public function down(): void
    {
        //
    }
};
