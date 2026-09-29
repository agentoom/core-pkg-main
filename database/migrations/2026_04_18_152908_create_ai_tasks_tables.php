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
            $table->integer('max_tasks_per_period')->nullable();
            $table->string('task_limit_period')->nullable(); // daily, weekly, monthly
        });

        Schema::create('agent_task_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('permitted_agent_id')->constrained('agents')->onDelete('cascade');
            $table->boolean('can_assign')->default(false);
            $table->boolean('can_view')->default(false);
            $table->text('assignment_explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('assigned_by_agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->string('name');
            $table->text('description');
            $table->json('parameters')->nullable();
            $table->string('period')->default('once'); // once, daily, weekly, monthly
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamps();
        });

        Schema::create('ai_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_task_id')->constrained('ai_tasks')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->text('content');
            $table->json('results')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_task_logs');
        Schema::dropIfExists('ai_tasks');
        Schema::dropIfExists('agent_task_permissions');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['max_tasks_per_period', 'task_limit_period']);
        });
    }
};
