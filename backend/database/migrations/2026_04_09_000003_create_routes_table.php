<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->integer('total_distance')->nullable()->comment('Distancia total en metros');
            $table->integer('total_duration')->nullable()->comment('Duración total en segundos');
            $table->enum('status', ['pending', 'optimized', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('optimized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
