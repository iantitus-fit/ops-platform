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
     * sent_at is when the text channel accepted the text. It stays empty for anything that never went out: a
     * customer's text, a draft, a withdrawn draft, a text still queued and one that failed. The thread sorts and
     * labels each message by it, so a held draft the owner sends later sits where it actually went out (D-54).
     *
     * The one-live-draft index is put back at the end because on SQLite a table rebuild keeps a partial unique index
     * only as a plain one (the 02-19 rule), which would refuse a conversation's second message of any kind. Where
     * the column is added in place, creating the index again changes nothing.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable();
        });

        $this->createOneLiveDraftIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });

        $this->createOneLiveDraftIndex();
    }

    private function createOneLiveDraftIndex(): void
    {
        DB::statement('drop index if exists messages_one_live_draft_per_conversation');
        DB::statement("create unique index messages_one_live_draft_per_conversation on messages (conversation_id) where status = 'draft'");
    }
};
