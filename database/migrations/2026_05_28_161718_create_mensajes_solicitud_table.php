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
        Schema::connection('mysql_external')->create('mensajes_solicitud', function (Blueprint $table) {
            $table->bigIncrements('id_mensaje');
            $table->unsignedInteger('id_solicitud');
            $table->unsignedInteger('staff_id');
            $table->text('mensaje')->nullable();
            $table->enum('tipo', ['texto', 'imagen', 'archivo'])->default('texto');
            $table->string('archivo_url', 255)->nullable();
            $table->string('archivo_nombre', 255)->nullable();
            $table->string('archivo_mime', 100)->nullable();
            $table->unsignedBigInteger('archivo_size')->nullable();
            $table->boolean('leido')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_solicitud')
                ->references('id_solicitud')
                ->on('solicitudes');

            $table->foreign('staff_id')
                ->references('staff_id')
                ->on('ost_staff');

            $table->index(['id_solicitud', 'created_at'], 'idx_mensajes_solicitud_created');
            $table->index(['staff_id'], 'idx_mensajes_staff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_external')->dropIfExists('mensajes_solicitud');
    }
};
