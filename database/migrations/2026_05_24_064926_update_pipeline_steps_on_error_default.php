<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->string('on_error')->default('continue')->change();
        });

        DB::table('pipeline_steps')
            ->where('on_error', 'fail')
            ->update(['on_error' => 'continue']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->string('on_error')->default('fail')->change();
        });
    }
};
