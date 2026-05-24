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
        Schema::create('replays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guild_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('game_version');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->string('sha256_hash', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('player_count')->nullable();
            $table->enum('status', [
                'uploaded',
                'processing',
                'ready',
                'failed',
            ])->default('uploaded');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['guild_id', 'status']);
            $table->index('game_version');
            $table->index('sha256_hash');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replays');
    }
};
