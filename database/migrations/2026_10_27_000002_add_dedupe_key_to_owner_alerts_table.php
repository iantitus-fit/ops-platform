<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * dedupe_key is the one key a conversation alert is stored under (conversation, message,
     * reason and person, as OwnerAlert::dedupeKey() builds it). It is NULL for alerts about the
     * whole business, which are never de-duplicated (06 D-08).
     */
    public function up(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->dropColumn('dedupe_key');
        });
    }
};
