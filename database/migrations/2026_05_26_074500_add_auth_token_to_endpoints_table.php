<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->boolean('auth_token_enabled')->default(false)->after('allowed_sources');
            $table->string('auth_token', 64)->nullable()->after('auth_token_enabled');
            $table->string('auth_token_header', 255)->default('X-Agentoom-Signature')->after('auth_token');
        });
    }

    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn(['auth_token_enabled', 'auth_token', 'auth_token_header']);
        });
    }
};
