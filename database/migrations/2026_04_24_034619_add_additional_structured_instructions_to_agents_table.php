<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->text('operational_protocol')->nullable()->after('objective');
            $table->text('error_handling')->nullable()->after('operational_protocol');
            $table->boolean('is_cautious_constraint_enabled')->default(true)->after('constraints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'operational_protocol',
                'error_handling',
                'is_cautious_constraint_enabled',
            ]);
        });
    }
};
