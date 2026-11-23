<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per walk, per version of its customer turns and per variant (10 D-06). A walk's recording is found by its
     * file path and a hash of its customer turns, so a changed customer turn finds none and the walk is refused as
     * unrecorded (D-05). The variant keeps the measured Booking wordings of D-18 apart from the recording the gate reads
     * (as_built). fingerprint is what the recording was made against (D-04): when the candidate's differs, the recording
     * is stale, which is reported and never refused. Recordings live here and never in the business folder, so recording
     * again never changes the folder or mints a version.
     */
    public function up(): void
    {
        Schema::create('walk_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('walk_path');
            $table->string('script_hash', 64);
            $table->string('variant')->default('as_built');
            $table->string('fingerprint', 64);
            $table->json('payload');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['walk_path', 'script_hash', 'variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walk_recordings');
    }
};
