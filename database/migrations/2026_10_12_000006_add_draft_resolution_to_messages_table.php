<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A draft ends one of four ways (approved, edited, discarded or replaced), and the way it ended is kept with who
     * ended it and when, so no draft is ever deleted. original_body keeps the agent's words when the owner edits
     * them. The partial unique index lets the database itself refuse a second live draft in one conversation (D-28).
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('original_body')->nullable();
        });

        DB::statement("create unique index messages_one_live_draft_per_conversation on messages (conversation_id) where status = 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists messages_one_live_draft_per_conversation');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['resolution', 'resolved_at', 'original_body']);
        });
    }
};
