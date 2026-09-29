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
            $table->boolean('condition_on_previous')->default(false)->change();
            $table->boolean('condition_on_input')->default(false)->change();
            $table->boolean('condition_on_current')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->boolean('condition_on_previous')->default(true)->change();
            $table->boolean('condition_on_input')->default(true)->change();
            $table->boolean('condition_on_current')->default(true)->change();
        });
    }
};
