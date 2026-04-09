<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stop_sequence')->comment('Orden de visita dentro de la ruta');
            $table->string('estimated_arrival', 5)->nullable()->comment('Hora estimada HH:MM');
            $table->timestamps();

            $table->unique(['route_id', 'stop_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
