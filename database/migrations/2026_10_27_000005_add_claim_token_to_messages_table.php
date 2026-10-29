<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * claim_token records which front-desk run claimed this customer message. The claim writes it in the same update
     * that sets handled_at, and failed() finds the run's burst by it (06 D-12). Rows from before, and keyword texts
     * handled on arrival, keep NULL and so belong to no run.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('claim_token', 36)->nullable();

            $table->index(['conversation_id', 'claim_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'claim_token']);
            $table->dropColumn('claim_token');
        });
    }
};
