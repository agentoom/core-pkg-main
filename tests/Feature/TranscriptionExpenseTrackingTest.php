<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
use Livewire\Livewire;

class TranscriptionExpenseTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcription_deducts_credits(): void
    {
        Storage::fake('public');

        // Mock transcription response to return usage
        Transcription::fake([
            new TranscriptionResponse(
                'Transcribed text',
                collect(),
                new Usage(1000, 2000),
                new Meta('openrouter', 'openai/whisper-large-v3')
            ),
        ]);

        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-fake-key',
            'url' => 'https://openrouter.ai/api/v1',
            'is_active' => true,
        ]);

        // Create model with pricing
        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'openai/whisper-large-v3',
            'is_active' => true,
            'model_features' => [
                'pricing' => [
                    // Stored per million, exactly as the provider sync writes it.
                    'prompt' => '6.0',
                    'completion' => '6.0',
                ],
            ],
        ]);

        Configuration::setValue('ai_provider', $provider->id);
        Configuration::setValue('ai_model', $model->id);
        Configuration::setValue('ai_transcription_provider', $provider->id);
        Configuration::setValue('ai_transcription_model', $model->id);

        $initialCredits = 10.0;
        Configuration::setValue('app_ai_credits', $initialCredits);

        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'name' => 'Test Project',
            'is_active' => true,
            'user_id' => $user->id,
        ]);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
            'project_id' => $project->id,
        ]);

        $widget = AgentWidget::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $audio = UploadedFile::fake()->create('audio.webm', 100, 'audio/webm');

        try {
            Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
                ->set('audioUpload', $audio)
                ->call('send')
                ->call('ask');
        } catch (RequestException $e) {
            // Expected 401 from subsequent agent call
        } catch (\Throwable $e) {
            // Handle other errors
        }
        Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === null);

        $remainingCredits = (float) Configuration::getValue('app_ai_credits');

        // 1000 tokens * $6/M = 0.006
        // 2000 tokens * $6/M = 0.012
        // Total = 0.018
        // OpenRouter multiplier 1.25 (default)
        // 0.018 * 1.25 = 0.0225

        $this->assertLessThan($initialCredits, $remainingCredits);

        $usageLog = DB::table('ai_usage_logs')
            ->where('action_type', 'audio_transcription')
            ->first();

        $this->assertNotNull($usageLog);
        $this->assertEquals(3000, $usageLog->total_tokens);
        $this->assertEquals(0.0225, (float) $usageLog->cost);
    }
}
