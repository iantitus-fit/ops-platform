<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per appointment and lead time (D-31): lead_minutes 0 is the confirmation, 1440 is a day ahead, and so on.
     * The unique key means a lead time sends at most once by construction. The claim and outcome columns follow the
     * owner alert's (06 D-06, D-07): one conditional update sets send_claimed_at so two workers cannot both send, and a
     * send ends in exactly one of sent_at, skipped_at or failed_at. ignores_window is set on a page booking's
     * confirmation, which goes whatever the hour (D-28).
     */
    public function up(): void
    {
        Schema::create('booking_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lead_minutes');
            $table->timestamp('due_at');
            $table->boolean('ignores_window')->default(false);
            $table->timestamp('send_claimed_at')->nullable();
            $table->timestamp('waited_on_unread_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure')->nullable();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['appointment_id', 'lead_minutes']);
            $table->index('due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_sends');
    }
};
