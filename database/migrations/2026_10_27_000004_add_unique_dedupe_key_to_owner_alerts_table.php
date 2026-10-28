<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two workers raising one alert at once cannot both store it: the database refuses the
     * second row with the same key. NULLs are distinct in a unique index on SQLite and
     * Postgres, so business-wide alerts and old rows without a key are not affected.
     */
    public function up(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->unique('dedupe_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
        });
    }
};
