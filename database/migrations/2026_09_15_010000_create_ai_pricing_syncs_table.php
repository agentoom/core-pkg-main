<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_pricing_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->unique();
            $table->string('status')->default('never');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('etag')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pricing_syncs');
    }
};
