<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('member_note', 255)->nullable();
            $table->string('status', 20)->default('requested')->index();
            $table->dateTime('requested_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->foreignId('movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->index(['member_user_id', 'product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
