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
            $table->decimal('max_usd_limit', 15, 6)->nullable()->after('is_active');
            $table->string('limit_period')->nullable()->after('max_usd_limit'); // daily, weekly, monthly
            $table->boolean('is_hard_limit')->default(false)->after('limit_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['max_usd_limit', 'limit_period', 'is_hard_limit']);
        });
    }
};
