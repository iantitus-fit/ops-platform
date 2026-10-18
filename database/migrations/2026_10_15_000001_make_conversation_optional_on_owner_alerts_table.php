<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An alert about the whole business, such as the safety check going down, has no
     * conversation (D-50). The column keeps its foreign key and its cascade; it just may be empty.
     * SQLite rebuilds the table to change a column and owner_alerts has no partial index to
     * recreate, so nothing else needs doing there.
     */
    public function up(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * A conversation is required again, so alerts about the business, which cannot have one,
     * are removed first.
     */
    public function down(): void
    {
        DB::table('owner_alerts')->whereNull('conversation_id')->delete();

        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable(false)->change();
        });
    }
};
