<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * failure_report_claimed_at records when a path took a run's report row to report that the run failed. One
     * conditional update sets it, so two paths cannot both report one run. A claim with no finished report
     * (failure_reported_at) after ReportFrontDeskFailure::CLAIM_WAIT_MINUTES is taken again, so a run whose reporter
     * died is still reported, the same lease as the alert send claim (06 D-06, D-07; 06-REVIEW WR-06). Rows from
     * before keep NULL.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('failure_report_claimed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('failure_report_claimed_at');
        });
    }
};
