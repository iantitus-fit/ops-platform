<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * kind says what an automated message is for, label is what the agent called the reply, and
     * gate_outcome, gate_reason and gate record the send rule's verdict (D-07). The gate JSON
     * carries a version key, so a later shape can sit next to this one instead of splitting the
     * history it is stored for. Any detail about money lives inside gate, not in a column of
     * its own.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('kind')->nullable();
            $table->string('label')->nullable();
            $table->string('gate_outcome')->nullable();
            $table->string('gate_reason')->nullable();
            $table->json('gate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'label', 'gate_outcome', 'gate_reason', 'gate']);
        });
    }
};
