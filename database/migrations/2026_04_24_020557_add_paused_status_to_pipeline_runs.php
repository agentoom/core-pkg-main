<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pipeline_runs DROP CONSTRAINT IF EXISTS pipeline_runs_status_check');
        }

        Schema::table('pipeline_runs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->string('status')->default('pending')->change();
            } else {
                $table->enum('status', ['pending', 'running', 'completed', 'failed', 'paused'])->default('pending')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pipeline_runs DROP CONSTRAINT IF EXISTS pipeline_runs_status_check');
        }

        Schema::table('pipeline_runs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->string('status')->default('pending')->change();
            } else {
                $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->change();
            }
        });
    }
};
