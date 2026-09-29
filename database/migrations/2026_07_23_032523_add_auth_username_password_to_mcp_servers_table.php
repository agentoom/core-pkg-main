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
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->string('auth_header_name')->nullable()->after('auth_token');
            $table->string('auth_username')->nullable()->after('auth_header_name');
            $table->text('auth_password')->nullable()->after('auth_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropColumn(['auth_header_name', 'auth_username', 'auth_password']);
        });
    }
};
