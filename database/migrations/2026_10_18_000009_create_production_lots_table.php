<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_movement_id')->unique()->constrained('stock_movements')->cascadeOnDelete();
            $table->string('lot_code', 80);
            $table->date('produced_on');
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining');
            $table->timestamps();

            $table->unique(['product_id', 'lot_code']);
            $table->index(['product_id', 'expires_on', 'produced_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_lots');
    }
};
