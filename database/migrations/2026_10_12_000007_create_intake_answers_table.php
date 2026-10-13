<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per answer the customer gave to one intake question. A correction is a new row, never an update, so
     * the current answer is the highest id for a conversation, service and question, and the earlier ones stay as
     * history. icm_version_id is the folder version in force when the answer was recorded, so Phase 3 can tell which
     * wording of the question the customer answered.
     */
    public function up(): void
    {
        Schema::create('intake_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('icm_version_id')->constrained();
            $table->string('service_slug');
            $table->string('question_key');
            $table->string('option')->nullable();
            $table->text('raw_text');
            $table->timestamps();

            $table->index(['conversation_id', 'service_slug', 'question_key', 'id'], 'intake_answers_current_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intake_answers');
    }
};
