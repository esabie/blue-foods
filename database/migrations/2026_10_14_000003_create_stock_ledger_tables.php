<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->bigInteger('unit_price_pesewas')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('ref_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'to_location_id']);
            $table->index(['product_id', 'from_location_id']);
            $table->index(['type', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('unit_price_pesewas');
            $table->bigInteger('total_pesewas');
            $table->string('customer_note', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['member_user_id', 'created_at']);
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('status', 20)->default('requested')->index();
            $table->dateTime('requested_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->foreignId('movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->index(['member_user_id', 'product_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entity', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 20);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['entity', 'entity_id']);
        });

        $now = now();
        DB::table('locations')->insert([
            ['type' => 'warehouse', 'user_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'online_shop', 'user_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Signed balance per product and location, derived only from the ledger.
        DB::statement('DROP VIEW IF EXISTS stock_balances');
        DB::statement(<<<'SQL'
            CREATE VIEW stock_balances AS
            SELECT m.product_id, m.location_id, SUM(m.delta) AS quantity
            FROM (
                SELECT product_id, to_location_id AS location_id, quantity AS delta
                FROM stock_movements WHERE to_location_id IS NOT NULL
                UNION ALL
                SELECT product_id, from_location_id AS location_id, -quantity AS delta
                FROM stock_movements WHERE from_location_id IS NOT NULL
            ) m
            GROUP BY m.product_id, m.location_id
        SQL);

        foreach (['stock_movements', 'sales', 'product_prices', 'audit_logs'] as $table) {
            $this->makeAppendOnly($table);
        }
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS stock_balances');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('locations');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS forbid_append_only_change()');
        }
    }

    /**
     * Rule B5: ledger records are never edited or deleted, enforced by the database itself.
     */
    private function makeAppendOnly(string $table): void
    {
        $message = "{$table} is append-only";

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => collect(['UPDATE', 'DELETE'])->each(fn ($op) => DB::unprepared(
                "CREATE TRIGGER {$table}_no_".strtolower($op)." BEFORE {$op} ON {$table} FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            )),
            'sqlite' => collect(['UPDATE', 'DELETE'])->each(fn ($op) => DB::unprepared(
                "CREATE TRIGGER {$table}_no_".strtolower($op)." BEFORE {$op} ON {$table}
                 BEGIN SELECT RAISE(ABORT, '{$message}'); END"
            )),
            'pgsql' => (function () use ($table) {
                DB::unprepared(<<<'SQL'
                    CREATE OR REPLACE FUNCTION forbid_append_only_change() RETURNS trigger AS $$
                    BEGIN RAISE EXCEPTION '% is append-only', TG_TABLE_NAME; END;
                    $$ LANGUAGE plpgsql
                SQL);
                DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION forbid_append_only_change()");
            })(),
            default => null,
        };
    }
};
