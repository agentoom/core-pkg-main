<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_information', function (Blueprint $table): void {
            $table->unsignedTinyInteger('importance')->default(3)->after('content')->index();
            $table->timestamp('expires_at')->nullable()->after('importance')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_information', function (Blueprint $table): void {
            $table->dropIndex(['importance']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['importance', 'expires_at']);
        });
    }
};
