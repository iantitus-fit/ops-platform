<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per stretch of time Jev, the safety check, was down (D-50). The row opens at the first failed call
     * and closes at the next answer. down_alerted_at and up_alerted_at record the two texts the owner gets, so a
     * flapping classifier cannot send more than one of each per outage.
     */
    public function up(): void
    {
        Schema::create('safety_check_outages', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('down_alerted_at')->nullable();
            $table->timestamp('up_alerted_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_check_outages');
    }
};
