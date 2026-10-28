<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

class DecisionModelService
{
    /**
     * Evaluate an input state against instructions using a decision model or calibrated LLM.
     *
     * @param  mixed  $state  Data/context to evaluate (string, array, or JSON-serializable)
     * @param  string  $instructions  Decision criteria/question to evaluate
     * @param  float  $threshold  Confidence threshold between 0.0 and 1.0 (default: 0.80)
     * @param  string|int|null  $providerIdentifier  Optional provider name or ID override
     * @param  string|int|null  $modelIdentifier  Optional model name or ID override
     * @param  string  $questionType  "noul" (boolean yes/no with confidence) or "choice"
     * @param  array|null  $criteria  Optional explicit true/false or multi-choice criteria
     * @return array{passed: bool, decision: bool|string, confidence: float, threshold: float, model: string, provider: string, raw: mixed, error: ?string}
     */
    public function evaluate(
        mixed $state,
        string $instructions,
        float $threshold = 0.80,
        string|int|null $providerIdentifier = null,
        string|int|null $modelIdentifier = null,
        string $questionType = 'noul',
        ?array $criteria = null,
        ?User $user = null,
        ?int $agentId = null,
        ?int $projectId = null,
    ): array {
        $threshold = max(0.0, min(1.0, (float) $threshold));

        // Resolve provider & model configuration
        $config = $this->resolveConfiguration($providerIdentifier, $modelIdentifier, $threshold);
        $provider = $config['provider'];
        $model = $config['model'];
        $apiKey = $config['api_key'];
        $driver = $config['driver'];
        $url = $config['url'];
        $threshold = $config['confidence_threshold'];

        if (! $provider || ! $model) {
            Log::warning('DecisionModelService: Provider or model could not be resolved', [
                'provider' => $provider,
                'model' => $model,
            ]);

            return [
                'passed' => false,
                'decision' => false,
                'confidence' => 0.0,
                'threshold' => $threshold,
                'model' => $model ?? 'unknown',
                'provider' => $provider ?? 'unknown',
                'raw' => null,
                'error' => 'No active decision model or provider configured',
            ];
        }

        $normalizedState = $this->normalizeState($state);

        // Try OpenRouter native Decisions API if provider is OpenRouter and model supports decisions
        if ($driver === 'openrouter' && $apiKey) {
            try {
                return $this->executeOpenRouterDecision(
                    $normalizedState,
                    $instructions,
                    $model,
                    $apiKey,
                    $url,
                    $threshold,
                    $questionType,
                    $criteria,
                    $user,
                    $agentId,
                    $projectId
                );
            } catch (Throwable $e) {
                Log::warning('DecisionModelService: OpenRouter native decision failed, falling back to structured LLM prompt', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Universal structured LLM fallback for all other providers (or if OpenRouter native call fails)
        return $this->executeUniversalLlmDecision(
            $normalizedState,
            $instructions,
            $provider,
            $model,
            $threshold,
            $questionType,
            $criteria,
            $user,
            $agentId,
            $projectId
        );
    }

    /**
     * Execute a decision query via OpenRouter's native Decisions API (/api/alpha/decisions).
     */
    protected function executeOpenRouterDecision(
        array $state,
        string $instructions,
        string $model,
        string $apiKey,
        ?string $baseUrl,
        float $threshold,
        string $questionType,
        ?array $criteria,
        ?User $user,
        ?int $agentId,
        ?int $projectId
    ): array {
        $endpoint = rtrim($baseUrl ?: 'https://openrouter.ai', '/').'/api/alpha/decisions';

        $questionPayload = [
            'type' => $questionType === 'choice' ? 'choice' : 'noul',
            'instructions' => $instructions,
        ];

        if ($criteria) {
            $questionPayload['criteria'] = $criteria;
        } elseif ($questionType === 'noul') {
            $questionPayload['criteria'] = [
                'true' => 'The condition described in the instructions is met.',
                'false' => 'The condition described in the instructions is not met.',
            ];
        }

        $payload = [
            'model' => $model,
            'state' => $state,
            'questions' => [
                'decision' => $questionPayload,
            ],
        ];

        $startTime = microtime(true);
        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url', 'https://agentoom.com'),
                'X-Title' => config('app.name', 'Agentoom'),
            ])
            ->post($endpoint, $payload);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenRouter Decisions API returned HTTP {$response->status()}: ".substr($response->body(), 0, 300));
        }

        $data = $response->json();
        $answer = $data['answers']['decision'] ?? null;

        if (! is_array($answer)) {
            throw new \RuntimeException('Invalid decisions response payload: missing answers.decision');
        }

        $passed = false;
        $decision = false;
        $confidence = 0.0;

        if (($answer['type'] ?? '') === 'noul') {
            $confidence = isset($answer['noul']) ? (float) $answer['noul'] : 0.0;
            $passed = $confidence >= $threshold;
            $decision = $passed;
        } elseif (($answer['type'] ?? '') === 'choice') {
            $decision = $answer['choice'] ?? '';
            $probabilities = $answer['probabilities'] ?? [];
            $confidence = isset($probabilities[$decision]) ? (float) $probabilities[$decision] : 1.0;
            $passed = $confidence >= $threshold;
        }

        // Log AI usage / billing if credit service is available
        $this->logUsage($user, $agentId, $projectId, $model, 'openrouter', $data['usage'] ?? null);

        Log::info('DecisionModelService: OpenRouter decision completed', [
            'model' => $model,
            'passed' => $passed,
            'confidence' => $confidence,
            'threshold' => $threshold,
            'duration_ms' => $durationMs,
        ]);

        return [
            'passed' => $passed,
            'decision' => $decision,
            'confidence' => round($confidence, 4),
            'threshold' => $threshold,
            'model' => $model,
            'provider' => 'openrouter',
            'raw' => $answer,
            'error' => null,
        ];
    }

    /**
     * Universal structured LLM fallback for non-OpenRouter providers (e.g. OpenAI, Anthropic, Ollama, Groq)
     * using a strict structured decision schema prompt.
     */
    protected function executeUniversalLlmDecision(
        array $state,
        string $instructions,
        string $provider,
        string $model,
        float $threshold,
        string $questionType,
        ?array $criteria,
        ?User $user,
        ?int $agentId,
        ?int $projectId
    ): array {
        $criteriaText = '';
        if ($criteria) {
            foreach ($criteria as $key => $desc) {
                $criteriaText .= "- {$key}: {$desc}\n";
            }
        } elseif ($questionType === 'noul') {
            $criteriaText = "- true: The condition in the instructions is satisfied.\n- false: The condition in the instructions is not satisfied.\n";
        }

        $stateJson = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = <<<EOT
You are an ultra-fast, calibrated probabilistic decision evaluator (System 1).
Evaluate the following STATE against the given INSTRUCTIONS and criteria.

STATE:
{$stateJson}

INSTRUCTIONS:
{$instructions}

CRITERIA:
{$criteriaText}

Return ONLY a strictly valid JSON object matching this exact format with no extra text or markdown formatting:
{
  "decision": true,
  "confidence": 0.95,
  "reasoning": "One concise sentence explaining the judgment."
}
EOT;

        if ($questionType === 'choice') {
            $prompt = str_replace(
                '"decision": true,',
                '"decision": "selected_choice_key",',
                $prompt
            );
        }

        try {
            $agent = new \Laravel\Ai\AnonymousAgent(
                instructions: 'You evaluate states and output strictly valid JSON decision objects with calibrated confidence scores.',
                messages: [],
                tools: []
            );

            $response = $agent->prompt($prompt, provider: $provider, model: $model);

            $text = trim((string) $response);

            // Strip possible markdown fences
            if (str_starts_with($text, '```')) {
                $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
                $text = preg_replace('/\s*```$/', '', $text);
                $text = trim($text);
            }

            $decoded = json_decode($text, true);
            if (! is_array($decoded) || ! array_key_exists('decision', $decoded)) {
                throw new \RuntimeException('Model did not return valid JSON with a "decision" key: '.substr($text, 0, 150));
            }

            $decision = $decoded['decision'];
            $confidence = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
                ? (float) $decoded['confidence']
                : 1.0;
            $confidence = max(0.0, min(1.0, $confidence));

            $passed = false;
            if ($questionType === 'choice') {
                $passed = ! empty($decision) && $confidence >= $threshold;
            } else {
                $isTruthy = $decision === true || $decision === 1 || $decision === 'true';
                $passed = $isTruthy && $confidence >= $threshold;
                $decision = $isTruthy;
            }

            $this->logUsage($user, $agentId, $projectId, $model, $provider, $response->usage ?? null, $response->meta ?? null);

            return [
                'passed' => $passed,
                'decision' => $decision,
                'confidence' => round($confidence, 4),
                'threshold' => $threshold,
                'model' => $model,
                'provider' => $provider,
                'raw' => $decoded,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('DecisionModelService: Universal LLM decision failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'passed' => false,
                'decision' => false,
                'confidence' => 0.0,
                'threshold' => $threshold,
                'model' => $model,
                'provider' => $provider,
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve effective provider and model configuration with fallback to global settings.
     */
    protected function resolveConfiguration(
        string|int|null $providerIdentifier,
        string|int|null $modelIdentifier,
        float $threshold
    ): array {
        $defaultConfig = InjectCurrentAiConfigService::getDecisionConfig();

        $provider = null;
        if ($providerIdentifier) {
            $provider = is_numeric($providerIdentifier)
                ? Provider::find($providerIdentifier)
                : Provider::where('name', $providerIdentifier)->first();
        }

        if (! $provider && ! empty($defaultConfig['provider'])) {
            $provider = Provider::where('name', $defaultConfig['provider'])->first();
        }

        $modelName = null;
        if ($modelIdentifier) {
            $modelRecord = is_numeric($modelIdentifier)
                ? ProviderModel::find($modelIdentifier)
                : ProviderModel::where('model_name', $modelIdentifier)
                    ->when($provider, fn ($q) => $q->where('provider_id', $provider->id))
                    ->first();
            $modelName = $modelRecord?->model_name ?? (string) $modelIdentifier;
        }

        if (! $modelName) {
            $modelName = $defaultConfig['model'] ?? null;
        }

        return [
            'provider' => $provider?->name ?? $defaultConfig['provider'],
            'driver' => $provider?->driver ?: ($provider?->name ?? $defaultConfig['driver'] ?? 'openai'),
            'model' => $modelName,
            'api_key' => $provider?->api_key ?? $defaultConfig['api_key'],
            'url' => $provider?->url ?? $defaultConfig['url'],
            'confidence_threshold' => $threshold ?: ($defaultConfig['confidence_threshold'] ?? 0.80),
        ];
    }

    /**
     * Normalize arbitrary input state to an associative array for the Decisions API.
     */
    protected function normalizeState(mixed $state): array
    {
        if (is_array($state)) {
            return array_is_list($state) ? ['items' => $state] : $state;
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_is_list($decoded) ? ['items' => $decoded] : $decoded;
            }

            return ['context' => $state];
        }

        if (is_numeric($state) || is_bool($state)) {
            return ['value' => $state];
        }

        if (is_null($state)) {
            return ['context' => ''];
        }

        return ['context' => (string) json_encode($state)];
    }

    /**
     * Log credit usage for decision inference.
     */
    protected function logUsage(
        ?User $user,
        ?int $agentId,
        ?int $projectId,
        string $model,
        string $provider,
        mixed $usageData = null,
        mixed $metaData = null
    ): void {
        try {
            if (! app()->bound(AiCreditService::class)) {
                return;
            }

            $usage = null;
            if ($usageData instanceof Usage) {
                $usage = $usageData;
            } elseif (is_array($usageData)) {
                $usage = new Usage(
                    promptTokens: (int) ($usageData['prompt_tokens'] ?? $usageData['input_tokens'] ?? 0),
                    completionTokens: (int) ($usageData['completion_tokens'] ?? $usageData['output_tokens'] ?? 0),
                );
            }

            $meta = null;
            if ($metaData instanceof Meta) {
                $meta = $metaData;
            }

            app(AiCreditService::class)->logUsage(
                user: $user,
                actionType: 'decision_model',
                usage: $usage,
                meta: $meta,
                agentId: $agentId,
                projectId: $projectId
            );
        } catch (Throwable $e) {
            Log::debug('DecisionModelService: Failed to log usage credits', ['error' => $e->getMessage()]);
        }
    }
}
