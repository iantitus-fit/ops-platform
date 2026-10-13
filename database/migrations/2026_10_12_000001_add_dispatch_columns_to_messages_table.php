<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * dispatched_at records that an inbound message was queued for processing; the claim on it
     * is what makes "queued exactly once" a database fact (D-42). handled_at records that the
     * message was taken into a reply run, so two runs never answer the same text. The index
     * serves the scheduled sweep that looks for inbound messages never dispatched.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->index(['direction', 'dispatched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['direction', 'dispatched_at']);
            $table->dropColumn(['dispatched_at', 'handled_at']);
        });
    }
};
