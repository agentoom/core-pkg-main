<?php

use Agentoom\Core\Models\Configuration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mark onboarding as complete for existing installations
        // that already have providers or agents configured
        if (Configuration::getValue('onboarding_complete', null) === null) {
            Configuration::setValue('onboarding_complete', '1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Configuration::setValue('onboarding_complete', '0');
    }
};
