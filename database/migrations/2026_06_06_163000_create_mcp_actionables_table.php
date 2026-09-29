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
        Schema::create('mcp_actionables', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('mcp_server_id')->constrained()->cascadeOnDelete();
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->string('category')->nullable();
            $blueprint->string('remote_class');
            $blueprint->string('remote_method');
            $blueprint->json('parameters_schema')->nullable();
            $blueprint->string('remote_base_url')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_actionables');
    }
};
