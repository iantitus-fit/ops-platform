<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One table for both kinds of row: a customer's appointment and a block of time the owner holds (A3). The shape
     * each kind must have is enforced by the next migration. Times are stored in UTC, start and end both, so every
     * overlap check compares spans (D-06, D-11). The service's slug and name are copied at booking so a later change to
     * the folder cannot rewrite history (D-42). There is no address column (D-42). booking_link_id is unique so one link
     * carries at most one appointment ever; several nulls are allowed on both databases (D-13).
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_link_id')->nullable()->unique()->constrained('booking_links')->nullOnDelete();
            $table->string('service_slug')->nullable();
            $table->string('service_name')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('state');
            $table->string('booked_by');
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at');
            $table->string('cancelled_by')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index(['state', 'starts_at']);
            $table->index('contact_id');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
