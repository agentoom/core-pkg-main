<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('input')->nullable();
            $table->longText('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_run_steps');
    }
};
