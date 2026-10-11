<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The composite unique index is what makes a retried webhook harmless: the same
     * provider message id can only be recorded once per provider. Rows with no provider
     * id (owner replies, drafts) are not constrained.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->string('author');
            $table->text('body');
            $table->string('status');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_message_id']);
            $table->index(['conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
