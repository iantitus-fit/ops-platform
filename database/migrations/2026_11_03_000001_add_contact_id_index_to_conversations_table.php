<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A plain index on conversations.contact_id, so finding a customer's conversations reads only theirs. The foreign
     * key adds no index on PostgreSQL, and conversations_one_open_per_contact covers open conversations only, so until
     * now every lookup by customer read the whole table; Contact::hasTextedBusiness() does one on every automatic send
     * (08 D-21).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
        });
    }
};
