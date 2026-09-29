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
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->longText('error')->nullable()->change();
        });

        Schema::table('pipeline_run_steps', function (Blueprint $table) {
            $table->longText('error')->nullable()->change();
        });

        Schema::table('endpoint_logs', function (Blueprint $table) {
            $table->longText('error_message')->nullable()->change();
        });

        Schema::table('ai_task_logs', function (Blueprint $table) {
            $table->longText('content')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->text('error')->nullable()->change();
        });

        Schema::table('pipeline_run_steps', function (Blueprint $table) {
            $table->text('error')->nullable()->change();
        });

        Schema::table('endpoint_logs', function (Blueprint $table) {
            $table->text('error_message')->nullable()->change();
        });

        Schema::table('ai_task_logs', function (Blueprint $table) {
            $table->text('content')->change();
        });
    }
};
