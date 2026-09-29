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
        Schema::table('scraped_data', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('author');
            $table->string('image_url')->nullable()->after('language');
            $table->string('favicon_url')->nullable()->after('image_url');
            $table->string('site_name')->nullable()->after('favicon_url');
            $table->json('meta')->nullable()->after('data');
            $table->json('tags')->nullable()->after('meta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraped_data', function (Blueprint $table) {
            //
        });
    }
};
