<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone_e164', 20)->nullable()->unique();
            $table->string('email')->nullable()->index();
            $table->string('status')->default('lead');
            $table->string('lead_source_key')->nullable();
            $table->unsignedInteger('acquisition_cost_cents')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
