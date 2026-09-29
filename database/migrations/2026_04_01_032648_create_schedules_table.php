<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('minute', 30)->default('*');
            $table->string('hour', 30)->default('*');
            $table->string('day', 30)->default('*');
            $table->string('month', 30)->default('*');
            $table->string('day_of_week', 30)->default('*');
            $table->string('timezone')->default('UTC');
            $table->string('model')->comment('Fully qualified class name, e.g. Agentoom\\Core\\Models\\Agent');
            $table->unsignedBigInteger('model_id')->nullable()->comment('ID of the agent or pipeline to run');
            $table->string('method')->nullable();
            $table->text('instructions')->nullable();
            $table->text('repository_instructions')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(false);
            $table->dateTime('last_execution')->nullable()->comment('Updated on every execution');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
