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
        Schema::table('attendances', function (Blueprint $table) {
            // Índice compuesto para consultas por usuario y fecha
            $table->index(['user_id', 'timestamp'], 'idx_user_timestamp');
            
            // Índice para tipo de asistencia
            $table->index(['type'], 'idx_type');
            
            // Índice compuesto para consultas por usuario y tipo
            $table->index(['user_id', 'type'], 'idx_user_type');
            
            // Índice para timestamp (ordenamiento)
            $table->index(['timestamp'], 'idx_timestamp');
            
            // Índice para created_at (auditoría)
            $table->index(['created_at'], 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_user_timestamp');
            $table->dropIndex('idx_type');
            $table->dropIndex('idx_user_type');
            $table->dropIndex('idx_timestamp');
            $table->dropIndex('idx_created_at');
        });
    }
};