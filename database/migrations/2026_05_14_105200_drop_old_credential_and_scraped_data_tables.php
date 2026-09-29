<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('scrape_targets');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('credential_types');
        Schema::dropIfExists('scraped_data');
        Schema::dropIfExists('scraped_data_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
