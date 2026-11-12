<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plain indexes on owner_alerts.conversation_id and owner_alerts.message_id. The foreign keys add none on
     * PostgreSQL, and RaiseOwnerAlert::alreadyRaised() asks by both before every owner alert, as do the life-safety
     * hand-off, the failed-run report and the front desk by message alone; without them each of those reads the whole
     * table. Phase 8 adds two alert reasons that take the same path.
     */
    public function up(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->index('conversation_id');
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropIndex(['message_id']);
        });
    }
};
