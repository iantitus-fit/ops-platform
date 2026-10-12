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
     * One paused state for the whole conversation (D-23): when the assistant stepped back, why, and who did it. A
     * null paused_at means the assistant is active. paused_by is null when the assistant paused itself.
     *
     * On SQLite, adding or dropping a foreign key column rebuilds the table, and the rebuild keeps the partial unique
     * index that refuses a second open conversation for one customer only as a plain unique index, which would refuse
     * a customer's second conversation of any kind. It is dropped and created again as the partial one at the end.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable();
            $table->string('paused_reason')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->createOneOpenPerContactIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn(['paused_at', 'paused_reason']);
        });

        $this->createOneOpenPerContactIndex();
    }

    private function createOneOpenPerContactIndex(): void
    {
        DB::statement('drop index if exists conversations_one_open_per_contact');
        DB::statement("create unique index conversations_one_open_per_contact on conversations (contact_id) where status = 'open'");
    }
};
