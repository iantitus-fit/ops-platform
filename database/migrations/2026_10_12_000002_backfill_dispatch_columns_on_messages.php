<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every message that exists before this phase was already seen by a person, so it counts as
     * dispatched and handled the moment it arrived. Without this the first sweep after
     * migrating would treat Phase 1 and seeded customer texts as new and message those customers.
     */
    public function up(): void
    {
        DB::table('messages')
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => DB::raw('created_at')]);

        DB::table('messages')
            ->whereNull('handled_at')
            ->update(['handled_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the columns are dropped by the earlier add_dispatch_columns migration.
     */
    public function down(): void
    {
        //
    }
};
