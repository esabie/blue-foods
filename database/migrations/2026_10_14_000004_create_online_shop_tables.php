<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('photo_path')->nullable()->after('description');
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('access_token', 64);
            $table->string('customer_name');
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('delivery_method', 20);
            $table->string('payment_method', 20);
            $table->string('status', 20)->index();
            $table->bigInteger('subtotal_pesewas');
            $table->bigInteger('delivery_fee_pesewas');
            $table->bigInteger('total_pesewas');
            $table->dateTime('reserved_until')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->boolean('needs_refund')->default(false);
            $table->timestamps();
            $table->index(['status', 'reserved_until']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('online_orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('unit_price_pesewas');
            $table->bigInteger('total_pesewas');
            $table->foreignId('movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('online_orders')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('provider', 30);
            $table->string('provider_ref')->nullable()->index();
            $table->string('method', 20);
            $table->bigInteger('amount_pesewas');
            $table->string('status', 20)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('online_orders')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // One row per sale that still counts (team or online), excluding reversed sales.
        // Every revenue and units-sold figure in section 6.1 is calculated from this view.
        DB::statement('DROP VIEW IF EXISTS effective_sales');
        DB::statement(<<<'SQL'
            CREATE VIEW effective_sales AS
            SELECT
                m.id AS movement_id,
                m.occurred_at,
                m.product_id,
                CASE WHEN m.type = 'SOLD' THEN 'team' ELSE 'online' END AS channel,
                l.user_id AS member_user_id,
                oi.order_id,
                m.quantity,
                m.unit_price_pesewas,
                m.quantity * m.unit_price_pesewas AS revenue_pesewas
            FROM stock_movements m
            LEFT JOIN locations l ON l.id = m.from_location_id AND l.type = 'member'
            LEFT JOIN order_items oi ON oi.movement_id = m.id
            WHERE m.type IN ('SOLD', 'SOLD_ONLINE')
              AND NOT EXISTS (
                  SELECT 1 FROM stock_movements r
                  WHERE r.ref_movement_id = m.id AND r.type = 'SALE_REVERSED'
              )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS effective_sales');
        Schema::dropIfExists('order_status_changes');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('online_orders');
        Schema::dropIfExists('site_settings');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'photo_path']);
        });
    }
};
