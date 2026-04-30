<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_external')->table('solicitud_detalles', function (Blueprint $table): void {
            if (! Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'derivado_a_logistica')) {
                $table->boolean('derivado_a_logistica')->default(false)->after('id_estado_detalle');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_external')->table('solicitud_detalles', function (Blueprint $table): void {
            if (Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'derivado_a_logistica')) {
                $table->dropColumn('derivado_a_logistica');
            }
        });
    }
};
