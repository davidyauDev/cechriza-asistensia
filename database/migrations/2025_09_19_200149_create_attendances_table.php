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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('timestamp')->comment('Timestamp en milisegundos');
            $table->double('latitude', 10, 7);
            $table->double('longitude', 11, 8);
            $table->text('notes')->nullable();
            $table->string('device_model', 255);
            $table->unsignedTinyInteger('battery_percentage');
            $table->unsignedTinyInteger('signal_strength');
            $table->string('network_type', 50);
            $table->boolean('is_internet_available');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->comment('Identificador único');
            $table->string('type', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
