<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_hand_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount_pesewas');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['member_user_id', 'created_at']);
        });

        Schema::create('cash_hand_in_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_in_id')->unique()->constrained('cash_hand_ins')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('cancelled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        foreach (['cash_hand_ins', 'cash_hand_in_cancellations'] as $table) {
            $this->makeAppendOnly($table);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_hand_in_cancellations');
        Schema::dropIfExists('cash_hand_ins');
    }

    /**
     * Rule B5, as for the stock ledger: a mistaken hand-in is cancelled by a new record, never edited.
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
            'pgsql' => DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_change()"),
            default => null,
        };
    }
};
