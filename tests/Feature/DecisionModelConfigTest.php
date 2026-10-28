<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Livewire\Admin\ConfigIndex;
use Agentoom\Core\Livewire\Admin\OnboardingWizard;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class DecisionModelConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_inject_current_ai_config_service_resolves_decision_config(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-openrouter-test',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'typesafe/jev-1.14',
            'model_features' => [
                'architecture' => [
                    'output_modalities' => ['decisions'],
                ],
            ],
            'is_active' => true,
        ]);

        Configuration::setValue('ai_decision_provider', 'openrouter');
        Configuration::setValue('ai_decision_model', $model->id);
        Configuration::setValue('ai_decision_confidence_threshold', 0.85);

        $config = InjectCurrentAiConfigService::getDecisionConfig();

        $this->assertEquals('openrouter', $config['provider']);
        $this->assertEquals('typesafe/jev-1.14', $config['model']);
        $this->assertEquals(0.85, $config['confidence_threshold']);
        $this->assertEquals('sk-openrouter-test', $config['api_key']);
    }

    public function test_inject_current_ai_config_service_falls_back_to_global_ai_provider_when_decision_provider_unset(): void
    {
        $provider = Provider::create([
            'name' => 'openai',
            'api_key' => 'sk-openai-test',
            'url' => 'https://api.openai.com/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o-mini',
            'model_features' => [],
            'is_active' => true,
        ]);

        Configuration::setValue('ai_provider', 'openai');
        Configuration::setValue('ai_model', $model->id);

        $config = InjectCurrentAiConfigService::getDecisionConfig();

        $this->assertEquals('openai', $config['provider']);
        $this->assertEquals('gpt-4o-mini', $config['model']);
        $this->assertEquals(0.80, $config['confidence_threshold']);
        $this->assertEquals('sk-openai-test', $config['api_key']);
    }

    public function test_config_index_saves_decision_configuration(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-test',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'typesafe/jev-latest',
            'model_features' => [],
            'is_active' => true,
        ]);

        Livewire::test(ConfigIndex::class)
            ->set('ai_decision_provider', 'openrouter')
            ->set('ai_decision_model', (string) $model->id)
            ->set('ai_decision_confidence_threshold', 0.90)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('openrouter', Configuration::getValue('ai_decision_provider'));
        $this->assertEquals((string) $model->id, Configuration::getValue('ai_decision_model'));
        $this->assertEquals(0.90, (float) Configuration::getValue('ai_decision_confidence_threshold'));
    }

    public function test_onboarding_wizard_persists_decision_model(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-test',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $decisionModel = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'typesafe/jev-1.14',
            'model_features' => [
                'architecture' => ['output_modalities' => ['decisions']],
            ],
            'is_active' => true,
        ]);

        Livewire::test(OnboardingWizard::class)
            ->set('step', 3)
            ->set('providerId', $provider->id)
            ->set('providerName', 'openrouter')
            ->set('decisionModel', (string) $decisionModel->id)
            ->call('assignSystemAgentModels')
            ->assertHasNoErrors();

        $this->assertEquals('openrouter', Configuration::getValue('ai_decision_provider'));
        $this->assertEquals((string) $decisionModel->id, Configuration::getValue('ai_decision_model'));
    }
}
