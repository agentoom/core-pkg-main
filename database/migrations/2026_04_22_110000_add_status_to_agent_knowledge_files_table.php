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
            $table->string('status')->default('pending')->after('path');
            $table->text('last_error')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_knowledge_files', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_error']);
        });
    }
};
