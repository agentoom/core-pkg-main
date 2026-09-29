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
            $table->boolean('anonymization_enabled')->default(true)->after('allow_memory_analysis');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->boolean('privacy_anonymized')->default(true)->after('is_analyzed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('anonymization_enabled');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->dropColumn('privacy_anonymized');
        });
    }
};
