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
        Schema::create('scrape_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraped_data_type_id')->constrained('scraped_data_types')->onDelete('cascade');
            $table->string('type'); // single, sitemap
            $table->string('target');
            $table->string('sitemap_type')->nullable(); // XML, RSS, TEXT, HTML
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_targets');
    }
};
