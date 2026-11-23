<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_lots', function (Blueprint $table) {
            $table->timestamp('expiring_alerted_at')->nullable()->after('remaining');
            $table->timestamp('expired_alerted_at')->nullable()->after('expiring_alerted_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_lots', function (Blueprint $table) {
            $table->dropColumn(['expiring_alerted_at', 'expired_alerted_at']);
        });
    }
};
