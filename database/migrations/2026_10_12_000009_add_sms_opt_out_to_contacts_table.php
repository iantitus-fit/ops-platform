<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the customer texted STOP (D-40). A null value means they can be texted. The column holds no foreign key,
     * so SQLite adds it without rebuilding the table.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('sms_opted_out_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('sms_opted_out_at');
        });
    }
};
