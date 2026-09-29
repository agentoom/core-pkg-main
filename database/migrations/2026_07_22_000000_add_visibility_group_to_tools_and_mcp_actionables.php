<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->string('visibility_group')->nullable()->after('is_public');
            $table->index('visibility_group');
        });

        Schema::table('mcp_actionables', function (Blueprint $table) {
            $table->string('visibility_group')->nullable()->after('category');
            $table->index('visibility_group');
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('visibility_group');
        });

        Schema::table('mcp_actionables', function (Blueprint $table) {
            $table->dropColumn('visibility_group');
        });
    }
};
