<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->boolean('rate_limit_enabled')->default(false)->after('auth_token_header');
            $table->integer('rate_limit_max_per_minute')->nullable()->after('rate_limit_enabled');
            $table->integer('rate_limit_max_per_hour')->nullable()->after('rate_limit_max_per_minute');
        });
    }

    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn('rate_limit_max_per_hour');
            $table->dropColumn('rate_limit_max_per_minute');
            $table->dropColumn('rate_limit_enabled');
        });
    }
};
