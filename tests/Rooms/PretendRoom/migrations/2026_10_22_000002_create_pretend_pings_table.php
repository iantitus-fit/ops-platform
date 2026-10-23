<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per note the pretend feed handed over. feed_id is the id the feed gave the note. This migration loads
     * only inside tests, so the table never exists in the product (D-18, D-20).
     */
    public function up(): void
    {
        Schema::create('pretend_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('feed_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pretend_pings');
    }
};
