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
        Schema::table('pipeline_steps', function (Blueprint $blueprint) {
            $blueprint->boolean('condition_on_previous')->default(true)->after('condition_if');
            $blueprint->boolean('condition_on_input')->default(true)->after('condition_on_previous');
            $blueprint->boolean('condition_on_current')->default(true)->after('condition_on_input');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['condition_on_previous', 'condition_on_input', 'condition_on_current']);
        });
    }
};
