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
            $table->foreignId('mcp_server_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('remote_name')->nullable();
        });

        Schema::table('agent_skills', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('remote_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mcp_server_id');
            $table->dropColumn('remote_name');
        });

        Schema::table('agent_skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mcp_server_id');
            $table->dropColumn('remote_name');
        });
    }
};
