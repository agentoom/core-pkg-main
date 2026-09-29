<?php

namespace Agentoom\Core\Tests\Feature\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class InjectCurrentAiConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prioritizes_provider_api_key_over_global_config_key()
    {
        // 1. Setup a provider with a valid key
        $provider = Provider::create([
            'name' => 'openrouter',
            'url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'real-provider-key',
        ]);

        // 2. Setup global configuration with a different (placeholder) key
        Configuration::create(['key' => 'ai_provider', 'value' => 'openrouter']);
        Configuration::create(['key' => 'ai_api_key', 'value' => 'test-key']);

        // 3. Setup an agent WITHOUT a specific provider_id (to trigger fallback to global)
        $agent = Agent::create([
            'name' => 'Test Agent',
            'config' => [], // Empty config triggers fallback to global provider
        ]);

        // 4. Run injection
        $result = InjectCurrentAiConfigService::inject($agent);

        // 5. Verify the real provider key was used, not the test-key
        $this->assertEquals('real-provider-key', $result['api_key'], 'Should prioritize provider-specific API key over global configuration key');
        $this->assertEquals('real-provider-key', Config::get('ai.providers.openrouter.key'));
    }

    public function test_it_keeps_the_default_openrouter_url_when_database_provider_url_is_empty(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'url' => null,
            'api_key' => 'provider-key',
        ]);

        Configuration::create(['key' => 'ai_provider', 'value' => (string) $provider->id]);
        Configuration::create(['key' => 'ai_api_key', 'value' => '']);

        $agent = Agent::create([
            'name' => 'OpenRouter Agent',
            'config' => [],
        ]);

        InjectCurrentAiConfigService::inject($agent);

        $this->assertSame('https://openrouter.ai/api/v1', Config::get('ai.providers.openrouter.url'));
    }

    public function test_it_configures_custom_provider_as_openai_compatible(): void
    {
        $provider = Provider::create([
            'name' => 'custom',
            'driver' => 'openai',
            'url' => 'http://localhost:11434/v1',
            'api_key' => 'test-key',
        ]);

        Configuration::create(['key' => 'ai_provider', 'value' => (string) $provider->id]);
        Configuration::create(['key' => 'ai_api_key', 'value' => '']);

        $agent = Agent::create([
            'name' => 'Custom Provider Agent',
            'config' => [],
        ]);

        $result = InjectCurrentAiConfigService::inject($agent);

        $this->assertSame('custom', $result['provider_name']);
        $this->assertSame('openai', Config::get('ai.providers.custom.driver'));
        $this->assertSame('http://localhost:11434/v1', Config::get('ai.providers.custom.url'));
    }
}
