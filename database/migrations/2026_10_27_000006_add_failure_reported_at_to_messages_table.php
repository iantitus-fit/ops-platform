<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * failure_reported_at is set on a run's report row when the owner is told that the run failed. One conditional
     * update sets it, so the run's own catch and the queue's failed() report a run once (06 D-11). It is given back
     * when the report could not be written (02.1 IN-11). Rows from before keep NULL.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('failure_reported_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('failure_reported_at');
        });
    }
};
