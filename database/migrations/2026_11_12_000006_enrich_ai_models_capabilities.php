<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('display_name', 160)->nullable()->after('name');
            $table->string('family', 60)->nullable()->after('display_name');
            $table->string('parameter_size', 20)->nullable()->after('family');
            $table->string('capability_type', 30)->nullable()->after('profile');
            $table->boolean('is_reasoning')->default(false)->after('capability_type');
            $table->boolean('is_vision')->default(false)->after('is_reasoning');
            $table->boolean('is_embedding')->default(false)->after('is_vision');
            $table->decimal('estimated_ram_gb', 6, 2)->nullable()->after('is_embedding');
            $table->unsignedSmallInteger('recommended_timeout')->nullable()->after('estimated_ram_gb');
            $table->decimal('recommended_temperature', 3, 2)->nullable()->after('recommended_timeout');
            $table->string('notes', 255)->nullable()->after('recommended_temperature');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'family',
                'parameter_size',
                'capability_type',
                'is_reasoning',
                'is_vision',
                'is_embedding',
                'estimated_ram_gb',
                'recommended_timeout',
                'recommended_temperature',
                'notes',
            ]);
        });
    }
};
