<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('pipeline_run_id')
                ->nullable()
                ->constrained('pipeline_runs')
                ->nullOnDelete();

            $table->enum('status', ['success', 'failed']);
            $table->longText('log')->nullable()->comment('Agent output, error messages, token usage, etc.');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_logs');
    }
};
