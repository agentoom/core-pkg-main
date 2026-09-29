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
            $table->string('source_url')->nullable()->after('id');
            $table->string('source_type')->nullable()->after('source_url');
            $table->date('collection_date')->nullable()->after('source_type');
            $table->boolean('data_subject_consent')->default(false)->after('collection_date');
            $table->text('bias_notes')->nullable()->after('data_subject_consent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_knowledge_files', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'source_type', 'collection_date', 'data_subject_consent', 'bias_notes']);
        });
    }
};
