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
     * These are the platform's projects (D-10): each piece of work for a contact. A contact may have any number opened
     * by hand, but a contact is new once, so at most one is opened by a new lead, and the database refuses a second even
     * when two jobs race. There is no appointment column and no status column: a project is open while closed_at is
     * empty, and appointments attach to it later with the link on the appointment (D-09).
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('opened_by');
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('contact_id');
        });

        DB::statement("create unique index projects_one_lead_project_per_contact on projects (contact_id) where opened_by = 'lead'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists projects_one_lead_project_per_contact');

        Schema::dropIfExists('projects');
    }
};
