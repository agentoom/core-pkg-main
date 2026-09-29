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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Check Processes"
            $table->string('class_name')->unique(); // e.g., Agentoom\Core\Ai\Tools\CheckProcesses
            $table->text('description'); // What the tool does
            $table->json('schema')->nullable(); // JSON schema of parameters
            $table->boolean('is_available')->default(true); // Can be toggled on/off
            $table->boolean('is_public')->default(false); // Can be toggled on/off
            $table->longText('code')->nullable(); // Stores the PHP class content
            $table->boolean('is_db_backed')->default(false); // Indicates the tool's code is in the DB
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
