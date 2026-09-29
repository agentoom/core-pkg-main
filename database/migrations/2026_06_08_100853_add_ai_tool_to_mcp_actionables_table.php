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
        Schema::table('mcp_actionables', function (Blueprint $table) {
            $table->boolean('ai_tool')->default(false)->after('remote_base_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_actionables', function (Blueprint $table) {
            $table->dropColumn('ai_tool');
        });
    }
};
