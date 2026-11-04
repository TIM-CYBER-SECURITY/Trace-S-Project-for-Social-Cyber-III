<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Peran: operator | analis | komandan | admin (Supplemental Prompt §3)
            $table->string('role', 20)->default('operator')->after('password');
            $table->string('clearance_level', 30)->default('TERBATAS')->after('role');
            $table->string('rank_title', 60)->nullable()->after('clearance_level');
            $table->string('unit', 80)->nullable()->after('rank_title');
            $table->boolean('is_active')->default(true)->after('unit');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'clearance_level', 'rank_title', 'unit', 'is_active', 'last_login_at']);
        });
    }
};
