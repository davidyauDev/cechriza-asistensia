<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            //
            // //$table->addColumn('tinyInteger', 'active')->after('descripcion')->default(1);
            $currentDate = date('Y-m-d');
            $table->addColumn('date', 'fecha')->after('active')->default($currentDate);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            //
        });
    }
};
