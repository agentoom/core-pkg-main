<?php

namespace Database\Seeders;

use Agentoom\Core\Models\AgentSkill;
use Illuminate\Database\Seeder;

class AdsAnalysisSkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AgentSkill::updateOrCreate(
            ['slug' => 'ads-analysis-reporting'],
            [
                'name' => 'Ads Analysis & Reporting',
                'description' => 'Analyze performance across multiple ad platforms (Google, Meta, TikTok) using locally synced data. Compare ROAS, CTR, and CPC across platforms to identify trends and optimize spend.',
                'instructions' => '---
name: ads-analysis-reporting
description: Analyze performance across Google, Meta, and TikTok. Use for ROAS comparisons, CPC trends, and campaign optimization.
---

# Ads Analysis & Reporting Skill

Use this skill to fetch, compare, and analyze performance data from multiple advertising platforms.

## Core Actions

### Sync Data
To analyze the latest data, always trigger a sync for the relevant platform first.
- **Google**: Use `action: sync`, `platform: google`, `params: {customer_id: "..."}`.
- **Meta**: Use `action: sync`, `platform: meta`, `params: {ad_account_id: "...", level: "campaign"}`.
- **TikTok**: Use `action: sync`, `platform: tiktok`, `params: {advertiser_id: "..."}`.

### Query and Analyze
Use SQL queries on the `ads_data` table to get insights.
Key fields: `platform`, `date`, `campaign_name`, `spend`, `impressions`, `clicks`, `conversions`, `conversion_value`, `reach`, `frequency`.

#### Example Queries:
- Total spend and ROAS by platform:
  `SELECT platform, SUM(spend) as total_spend, (SUM(conversion_value)/SUM(spend)) as roas FROM ads_data GROUP BY platform`
- Daily performance trend:
  `SELECT date, SUM(spend) as daily_spend, SUM(conversions) as daily_conv FROM ads_data GROUP BY date ORDER BY date ASC`
- Best performing campaigns:
  `SELECT campaign_name, platform, SUM(spend) as spend, SUM(conversions) as conversions, (SUM(conversion_value)/SUM(spend)) as roas FROM ads_data GROUP BY campaign_name, platform HAVING spend > 0 ORDER BY roas DESC LIMIT 10`
',
                'musts' => ['Always specify a date range in your analysis.', 'Compare performance metrics (ROAS, CPC, CTR) across platforms when multiple exist.', 'Look for trends in daily data rather than just total sums.'],
                'donts' => ['Do not guess performance metrics; always query the database.', 'Do not ignore high spend / low ROAS campaigns.'],
                'tools' => ['Agentoom\\Core\\Ai\\Tools\\Public\\QueryLocalAdsTool'],
                'is_active' => true,
            ]
        );
    }
}
