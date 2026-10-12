<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per alert per recipient (Research A4). The body is stored as sent so the owner can
     * see later exactly what the alert said. An alert ends in exactly one of three ways: sent_at,
     * skipped_at or failed_at, with failure holding the reason when it failed (D-19). The user and
     * the message are kept as nullable references so deleting either leaves the alert's record.
     */
    public function up(): void
    {
        Schema::create('owner_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->boolean('urgent')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->string('to_phone')->nullable();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_alerts');
    }
};
