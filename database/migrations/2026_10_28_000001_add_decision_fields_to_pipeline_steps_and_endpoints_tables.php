<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->string('step_type')->default('agent')->nullable()->after('pipeline_id');
            $table->json('decision_config')->nullable()->after('model');
        });

        // Backfill existing steps with their derived type
        DB::table('pipeline_steps')
            ->whereNotNull('agent_id')
            ->update(['step_type' => 'agent']);

        DB::table('pipeline_steps')
            ->where(function ($query) {
                $query->whereNotNull('class')
                    ->orWhereNotNull('method')
                    ->orWhereNotNull('mcp_actionable_id');
            })
            ->update(['step_type' => 'custom']);

        DB::table('pipeline_steps')
            ->whereNull('agent_id')
            ->whereNull('class')
            ->whereNull('method')
            ->whereNull('mcp_actionable_id')
            ->update(['step_type' => 'receive_data']);

        Schema::table('endpoints', function (Blueprint $table) {
            $table->boolean('decision_filter_enabled')->default(false)->after('force_json');
            $table->string('decision_filter_provider')->nullable()->after('decision_filter_enabled');
            $table->string('decision_filter_model')->nullable()->after('decision_filter_provider');
            $table->text('decision_filter_instructions')->nullable()->after('decision_filter_model');
            $table->float('decision_filter_threshold')->default(0.80)->after('decision_filter_instructions');
            $table->string('decision_filter_reject_action')->default('skip_200')->after('decision_filter_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropColumn(['step_type', 'decision_config']);
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'decision_filter_enabled',
                'decision_filter_provider',
                'decision_filter_model',
                'decision_filter_instructions',
                'decision_filter_threshold',
                'decision_filter_reject_action',
            ]);
        });
    }
};
