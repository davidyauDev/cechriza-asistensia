<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_match_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->unsignedInteger('moves');
            $table->unsignedInteger('elapsed_seconds');
            $table->unsignedInteger('matched_pairs');
            $table->unsignedInteger('score');
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['score', 'elapsed_seconds', 'moves']);
            $table->index(['user_id', 'score']);
            $table->index('played_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_match_scores');
    }
};

