<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->decimal('cost', 15, 8)->nullable()->change();
            $table->decimal('provider_cost', 15, 8)->nullable()->after('cost');
            $table->string('pricing_source')->nullable()->after('provider_cost');
            $table->string('provider')->nullable()->after('pricing_source');
            $table->string('model')->nullable()->after('provider');
            $table->timestamp('priced_at')->nullable()->after('model');
            $table->string('idempotency_key')->nullable()->after('priced_at');
            $table->unsignedBigInteger('input_tokens')->default(0)->after('idempotency_key');
            $table->unsignedBigInteger('output_tokens')->default(0)->after('input_tokens');
            $table->unsignedBigInteger('cache_read_tokens')->default(0)->after('output_tokens');
            $table->unsignedBigInteger('cache_write_tokens')->default(0)->after('cache_read_tokens');
            $table->unsignedBigInteger('reasoning_tokens')->default(0)->after('cache_write_tokens');
        });

        DB::statement('UPDATE ai_usage_logs SET pricing_source = COALESCE(pricing_source, \'legacy\')');
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->index('created_at', 'ai_usage_logs_created_at_index');
            $table->index(['provider', 'model'], 'ai_usage_logs_provider_model_index');
            $table->index('pricing_source', 'ai_usage_logs_pricing_source_index');
            $table->unique(['action_type', 'idempotency_key'], 'ai_usage_logs_action_idempotency_unique');
        });

        Schema::table('ai_model_pricings', function (Blueprint $table) {
            $table->dropUnique('ai_model_pricings_provider_model_unique');
            $table->decimal('input_price_per_million', 15, 6)->nullable()->change();
            $table->decimal('output_price_per_million', 15, 6)->nullable()->change();
            $table->decimal('cache_read_price_per_million', 15, 6)->nullable();
            $table->decimal('cache_write_price_per_million', 15, 6)->nullable();
            $table->decimal('reasoning_price_per_million', 15, 6)->nullable();
            $table->decimal('audio_input_price_per_million', 15, 6)->nullable();
            $table->decimal('audio_output_price_per_million', 15, 6)->nullable();
            $table->decimal('request_price', 15, 8)->nullable();
            $table->decimal('image_price_per_million', 15, 6)->nullable();
            $table->string('source')->default('legacy');
            $table->string('source_model_id')->nullable();
            $table->text('source_url')->nullable();
            $table->string('mode')->nullable();
            $table->timestamp('priced_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unique(['provider', 'model', 'source'], 'ai_model_pricings_provider_model_source_unique');
            $table->index(['provider', 'model', 'source'], 'ai_model_pricings_lookup_index');
        });

        DB::table('ai_model_pricings')->whereNull('source')->update(['source' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('ai_model_pricings', function (Blueprint $table) {
            $table->dropUnique('ai_model_pricings_provider_model_source_unique');
            $table->dropIndex('ai_model_pricings_lookup_index');
            $table->dropColumn([
                'cache_read_price_per_million', 'cache_write_price_per_million',
                'reasoning_price_per_million', 'audio_input_price_per_million',
                'audio_output_price_per_million', 'request_price', 'image_price_per_million',
                'source', 'source_model_id', 'source_url', 'mode', 'priced_at', 'last_seen_at',
            ]);
            $table->decimal('input_price_per_million', 15, 6)->default(2.00)->change();
            $table->decimal('output_price_per_million', 15, 6)->default(2.00)->change();
            $table->unique(['provider', 'model']);
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropUnique('ai_usage_logs_action_idempotency_unique');
            $table->dropIndex('ai_usage_logs_created_at_index');
            $table->dropIndex('ai_usage_logs_provider_model_index');
            $table->dropIndex('ai_usage_logs_pricing_source_index');
            $table->dropColumn([
                'provider_cost', 'pricing_source', 'provider', 'model', 'priced_at',
                'idempotency_key', 'input_tokens', 'output_tokens', 'cache_read_tokens',
                'cache_write_tokens', 'reasoning_tokens',
            ]);
        });
    }
};
