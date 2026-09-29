<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'seo_content_metrics',
            'seo_content_insights',
            'seo_content',
            'seo_product_metrics',
            'seo_product_insights',
            'seo_product_categories',
            'seo_products',
            'seo_order_items',
            'seo_orders',
            'seo_customers',
            'seo_strategy_revisions',
            'seo_evaluations',
            'seo_metrics_timeseries',
            'seo_actions',
            'seo_marketing_constraints',
            'seo_hypotheses',
            'seo_strategies',
            'seo_assessment_snapshots',
            'seo_data_layer_elements',
            'seo_data_layer_pages',
            'seo_content_links',
            'seo_ecommerce_order_items',
            'seo_ecommerce_orders',
            'seo_ecommerce_customers',
            'seo_projects',
            'ads_data',
            'project_resources',
            'project_resource_types',
        ];

        foreach ($tables as $table) {
            $cascade = DB::getDriverName() === 'sqlite' ? '' : ' CASCADE';
            DB::statement("DROP TABLE IF EXISTS {$table}{$cascade}");
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
