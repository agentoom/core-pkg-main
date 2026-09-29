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
            $table->string('on_error')->default('continue')->after('next_step_on_false');
            $table->integer('next_step_on_error')->nullable()->after('on_error');
            $table->integer('retry_attempts')->default(0)->after('next_step_on_error');
            $table->integer('retry_delay')->default(0)->after('retry_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropColumn(['on_error', 'next_step_on_error', 'retry_attempts', 'retry_delay']);
        });
    }
};
