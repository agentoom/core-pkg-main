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
        // Pipelines
        Schema::table('pipelines', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->after('id')->constrained('mcp_servers')->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Endpoints (slug already exists, just add mcp_server_id)
        Schema::table('endpoints', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->after('id')->constrained('mcp_servers')->nullOnDelete();
        });

        // Webhook subscriptions
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->after('id')->constrained('mcp_servers')->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('url');
        });

        // Schedules
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')->nullable()->after('id')->constrained('mcp_servers')->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn('mcp_server_id');
            $table->dropColumn('slug');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn('mcp_server_id');
        });

        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn('mcp_server_id');
            $table->dropColumn('slug');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['mcp_server_id']);
            $table->dropColumn('mcp_server_id');
            $table->dropColumn('slug');
        });
    }
};
