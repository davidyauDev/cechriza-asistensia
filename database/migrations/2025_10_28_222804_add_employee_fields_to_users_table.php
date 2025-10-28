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
        Schema::table('users', function (Blueprint $table) {
            $table->string('emp_code')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('emp_code');
            $table->string('last_name')->nullable()->after('first_name');
            
            // Agregar índice en emp_code para búsquedas rápidas
            $table->index('emp_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['emp_code']);
            $table->dropColumn(['emp_code', 'first_name', 'last_name']);
        });
    }
};
