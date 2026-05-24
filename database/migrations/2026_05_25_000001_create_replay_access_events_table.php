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
        Schema::create('replay_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replay_share_id')->nullable()->constrained('replay_shares')->nullOnDelete();
            $table->timestamp('occurred_at')->index();

            $table->index(['replay_id', 'occurred_at']);
            $table->index(['replay_share_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replay_access_events');
    }
};
