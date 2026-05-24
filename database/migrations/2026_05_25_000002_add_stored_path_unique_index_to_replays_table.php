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
        Schema::table('replays', function (Blueprint $table) {
            $table->unique('stored_path', 'replays_stored_path_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->dropUnique('replays_stored_path_unique');
        });
    }
};
