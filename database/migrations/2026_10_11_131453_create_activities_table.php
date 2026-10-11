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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('summary');
            $table->nullableMorphs('subject');
            $table->string('lead_source_key')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contact_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
