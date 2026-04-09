<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('weight', 8, 2)->comment('Peso en kg');
            $table->string('time_window_start', 5)->default('08:00')->comment('HH:MM');
            $table->string('time_window_end', 5)->default('18:00')->comment('HH:MM');
            $table->enum('status', ['pending', 'assigned', 'delivered'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
