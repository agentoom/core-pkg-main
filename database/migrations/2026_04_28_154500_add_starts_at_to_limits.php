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
            $table->date('limit_starts_at')->nullable()->after('limit_period');
            $table->date('task_limit_starts_at')->nullable()->after('task_limit_period');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->date('budget_limit_starts_at')->nullable()->after('budget_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['limit_starts_at', 'task_limit_starts_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget_limit_starts_at']);
        });
    }
};
