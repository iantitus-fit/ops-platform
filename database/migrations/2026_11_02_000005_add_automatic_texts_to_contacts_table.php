<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The owner's switch for automatic texts to one customer (D-20). Both columns are empty by default, so the switch is
     * off for every existing contact. A text from the customer turns automatic texts on by itself; that is worked out
     * from the inbound messages in plan 08-02 and stored nowhere.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('automatic_texts_on_at')->nullable();
            $table->foreignId('automatic_texts_on_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('automatic_texts_on_by_user_id');
            $table->dropColumn('automatic_texts_on_at');
        });
    }
};
