<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('capacity', 8, 2)->comment('Capacidad máxima en kg');
            $table->decimal('start_lat', 10, 7)->comment('Latitud depósito/origen');
            $table->decimal('start_lng', 10, 7)->comment('Longitud depósito/origen');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
