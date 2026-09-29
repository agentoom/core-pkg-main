<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('class')->nullable();
            $table->string('method')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('name')->nullable()->comment('Display name override for this step');
            $table->text('instructions_override')->nullable()->comment('Extra instructions appended to the agent for this step');
            $table->boolean('pass_output_as_input')->default(true)->comment('When true, the previous step output is prepended to this step input');
            $table->string('model')->nullable()->comment('Per-step model override; falls back to pipeline model');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_steps');
    }
};
