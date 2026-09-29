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
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('endpoint_logs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoint_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
