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
        Schema::table('agent_knowledge_files', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->change();
            $table->foreignId('project_id')->nullable()->after('agent_id')->constrained()->nullOnDelete();
        });

        Schema::table('agent_knowledge', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->change();
            $table->foreignId('project_id')->nullable()->after('agent_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_knowledge', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('agent_id')->nullable(false)->change();
        });

        Schema::table('agent_knowledge_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('agent_id')->nullable(false)->change();
        });
    }
};
