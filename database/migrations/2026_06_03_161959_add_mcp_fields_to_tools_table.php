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
        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->constrained('mcp_servers')->nullOnDelete();
            $table->string('remote_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn(['mcp_server_id', 'remote_name']);
        });
    }
};
