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
        Schema::create('ai_model_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model')->nullable(); // null can mean default for provider
            $table->decimal('input_price_per_million', 15, 6)->default(2.00);
            $table->decimal('output_price_per_million', 15, 6)->default(2.00);
            $table->timestamps();

            $table->unique(['provider', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_pricings');
    }
};
