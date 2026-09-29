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
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->text('condition_if')->nullable()->after('pass_output_as_input');
            $table->integer('next_step_on_true')->nullable()->after('condition_if');
            $table->integer('next_step_on_false')->nullable()->after('next_step_on_true');
        });

        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });

        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropColumn(['condition_if', 'next_step_on_true', 'next_step_on_false']);
        });
    }
};
