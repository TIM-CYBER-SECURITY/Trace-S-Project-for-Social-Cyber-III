<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('status', 12)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('status', 12)->default('ACTIVE');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique(); // ADMINISTRATOR | OPERATOR
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_key', 60)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        foreach (['role_permissions', 'permissions', 'roles', 'units', 'organizations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
