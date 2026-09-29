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
            $table->boolean('allow_memory_analysis')->default(true)->after('is_active');
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->boolean('allow_memory_analysis')->default(false)->after('is_active');
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->boolean('allow_memory_analysis')->default(false)->after('is_active');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('allow_memory_analysis')->default(false)->after('active');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->boolean('allow_memory_analysis')->default(false)->after('is_active');
        });

        if (Schema::hasTable('agent_widgets')) {
            Schema::table('agent_widgets', function (Blueprint $table) {
                $table->boolean('allow_memory_analysis')->default(true)->after('is_active');
            });
        }

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->boolean('allow_memory_analysis')->default(true)->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });

        if (Schema::hasTable('agent_widgets')) {
            Schema::table('agent_widgets', function (Blueprint $table) {
                $table->dropColumn('allow_memory_analysis');
            });
        }

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropColumn('allow_memory_analysis');
        });
    }
};
