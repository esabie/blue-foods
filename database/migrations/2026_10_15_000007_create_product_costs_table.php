<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->bigInteger('unit_cost_pesewas');
            $table->dateTime('effective_from');
            $table->foreignId('set_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_id', 'effective_from']);
        });

        $message = 'product_costs is append-only';

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => collect(['UPDATE', 'DELETE'])->each(fn ($op) => DB::unprepared(
                'CREATE TRIGGER product_costs_no_'.strtolower($op)." BEFORE {$op} ON product_costs FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            )),
            'sqlite' => collect(['UPDATE', 'DELETE'])->each(fn ($op) => DB::unprepared(
                'CREATE TRIGGER product_costs_no_'.strtolower($op)." BEFORE {$op} ON product_costs
                 BEGIN SELECT RAISE(ABORT, '{$message}'); END"
            )),
            'pgsql' => DB::unprepared('CREATE TRIGGER product_costs_append_only BEFORE UPDATE OR DELETE ON product_costs
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_change()'),
            default => null,
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
    }
};
