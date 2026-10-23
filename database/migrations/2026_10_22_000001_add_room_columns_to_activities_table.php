<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which room wrote a room_entry row, and which of that room's entry kinds it is. Both stay null for every row
     * v1.0 writes. The columns hold no foreign key, so SQLite adds them without rebuilding the table.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('room')->nullable();
            $table->string('entry')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['room', 'entry']);
        });
    }
};
