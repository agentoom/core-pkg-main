<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_models', function (Blueprint $table) {
            $table->unique(['provider_id', 'model_name'], 'provider_models_provider_model_unique');
        });
    }

    public function down(): void
    {
        Schema::table('provider_models', function (Blueprint $table) {
            $table->dropUnique('provider_models_provider_model_unique');
        });
    }
};
