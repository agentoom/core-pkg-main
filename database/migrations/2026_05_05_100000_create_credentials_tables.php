<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_types', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->json('config');
            $blueprint->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $blueprint->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $blueprint->timestamps();
        });

        Schema::create('credentials', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('credential_type_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $blueprint->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->text('data');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('credential_types');
    }
};
