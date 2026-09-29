<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_logs DROP CONSTRAINT IF EXISTS schedule_logs_status_check');
        }

        Schema::table('schedule_logs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->string('status')->change();
            } else {
                $table->enum('status', ['success', 'failed', 'paused', 'running'])->change();
            }
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_logs DROP CONSTRAINT IF EXISTS schedule_logs_status_check');
        }

        Schema::table('schedule_logs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->string('status')->change();
            } else {
                $table->enum('status', ['success', 'failed'])->change();
            }
        });
    }
};
