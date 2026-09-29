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
        Schema::table('users', function (Blueprint $table) {
            $table->string('ai_limit_type')->nullable()->after('password');
            $table->bigInteger('ai_limit_amount')->nullable()->after('ai_limit_type');
            $table->date('ai_limit_start_date')->nullable()->after('ai_limit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ai_limit_type',
                'ai_limit_amount',
                'ai_limit_start_date',
            ]);
        });
    }
};
