<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('role', 20)->default('marketing')->after('phone');
            $table->string('status', 20)->default('active')->after('role');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->index('role');
        });

        // Accounts created before roles existed came from the old self-registration page.
        DB::table('users')->update(['role' => 'management']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'role', 'status', 'failed_login_attempts', 'locked_until']);
        });
    }
};
