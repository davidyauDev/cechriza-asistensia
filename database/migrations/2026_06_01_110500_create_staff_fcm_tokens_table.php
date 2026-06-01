<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_external')->create('staff_fcm_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('staff_id');
            $table->string('token', 512)->unique('uq_token');
            $table->string('device_name', 150)->nullable();
            $table->enum('platform', ['android'])->default('android');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['staff_id', 'is_active'], 'idx_staff_active');
            $table->foreign('staff_id', 'fk_staff_fcm_tokens_staff')
                ->references('staff_id')
                ->on('ost_staff')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_external')->dropIfExists('staff_fcm_tokens');
    }
};

