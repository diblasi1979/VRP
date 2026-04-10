<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('max_route_distance_km', 8, 2)
                ->default(150)
                ->after('capacity')
                ->comment('Distancia máxima por ruta en kilómetros');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('max_route_distance_km');
        });
    }
};