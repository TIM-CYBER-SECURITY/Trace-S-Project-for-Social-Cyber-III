<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('trace_id', 14)->nullable()->unique();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('position', 120)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            // PROVISIONED | ACTIVE | SUSPENDED | REVOKED | SYSTEM (akun fixture simulasi, tidak dapat login)
            $table->string('account_status', 16)->default('PROVISIONED')->index();
            $table->string('approval_status', 16)->default('APPROVED');
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });

        // Email tidak lagi menjadi identitas login; Trace-ID adalah identitas tunggal.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'clearance_level', 'rank_title', 'unit', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('operator');
            $table->string('clearance_level', 30)->default('TERBATAS');
            $table->string('rank_title', 60)->nullable();
            $table->string('unit', 80)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropUnique(['trace_id']);
            $table->dropColumn([
                'trace_id', 'position', 'account_status', 'approval_status',
                'must_change_password', 'password_changed_at', 'expires_at',
            ]);
        });
    }
};
