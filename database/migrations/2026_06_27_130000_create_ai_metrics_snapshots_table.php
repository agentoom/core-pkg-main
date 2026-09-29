<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('snapshot_date')->unique();
            $table->unsignedBigInteger('total_executions')->default(0);
            $table->unsignedBigInteger('successful_executions')->default(0);
            $table->unsignedBigInteger('failed_executions')->default(0);
            $table->unsignedBigInteger('paused_executions')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('total_cost', 15, 8)->default(0);
            $table->unsignedInteger('avg_response_time_ms')->default(0);
            $table->unsignedInteger('avg_queue_wait_ms')->default(0);
            $table->json('provider_metrics')->nullable();
            $table->unsignedInteger('queue_depth')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_metrics_snapshots');
    }
};
