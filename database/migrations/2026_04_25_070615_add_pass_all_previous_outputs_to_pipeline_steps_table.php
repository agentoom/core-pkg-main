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
            $table->boolean('pass_all_previous_outputs')->default(false)->after('pass_output_as_input');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropColumn('pass_all_previous_outputs');
        });
    }
};
