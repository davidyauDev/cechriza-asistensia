<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_external')->table('solicitud_detalles', function (Blueprint $table): void {
            if (! Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'id_ubicacion')) {
                $table->integer('id_ubicacion')->nullable()->after('area_id');
            }

            if (! Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'ubicacion')) {
                $table->string('ubicacion', 50)->nullable()->after('id_ubicacion');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_external')->table('solicitud_detalles', function (Blueprint $table): void {
            if (Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'ubicacion')) {
                $table->dropColumn('ubicacion');
            }

            if (Schema::connection('mysql_external')->hasColumn('solicitud_detalles', 'id_ubicacion')) {
                $table->dropColumn('id_ubicacion');
            }
        });
    }
};
