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
        Schema::create('replay_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->enum('scope', [
                'link',
                'guild',
            ]);
            $table->uuid('token')->unique();
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();

            $table->index(['replay_id', 'scope']);
            $table->index('shared_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replay_shares');
    }
};
