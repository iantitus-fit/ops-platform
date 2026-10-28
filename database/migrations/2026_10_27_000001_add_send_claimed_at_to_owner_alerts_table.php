<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * send_claimed_at records when a sender took the alert to text it. One conditional update
     * sets it, so two senders cannot both text one alert. A claim with no outcome after
     * SendOwnerAlert::CLAIM_WAIT_MINUTES is taken again by the every-minute sweep, so an alert
     * whose sender died is not lost (06 D-06, D-07).
     */
    public function up(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->timestamp('send_claimed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->dropColumn('send_claimed_at');
        });
    }
};
