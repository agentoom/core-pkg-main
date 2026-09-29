<?php

return [
    'feeds' => [
        'litellm' => [
            'url' => env('AI_PRICING_LITELLM_URL', 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json'),
            'schema_url' => env('AI_PRICING_LITELLM_SCHEMA_URL', 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.schema.json'),
        ],
        'models_dev' => [
            'url' => env('AI_PRICING_MODELS_DEV_URL', 'https://models.dev/api.json'),
        ],
    ],
    'max_age_hours' => (int) env('AI_PRICING_MAX_AGE_HOURS', 6),
    'timeout' => (int) env('AI_PRICING_TIMEOUT', 60),
    'retry_times' => (int) env('AI_PRICING_RETRY_TIMES', 3),
    'retry_sleep_ms' => (int) env('AI_PRICING_RETRY_SLEEP_MS', 5000),
    'provider_maps' => [
        'openai' => ['litellm' => ['openai'], 'models_dev' => ['openai']],
        'openrouter' => ['litellm' => ['openrouter'], 'models_dev' => ['openrouter']],
        'anthropic' => ['litellm' => ['anthropic'], 'models_dev' => ['anthropic']],
        'gemini' => ['litellm' => ['gemini', 'vertex_ai'], 'models_dev' => ['google']],
        'google' => ['litellm' => ['gemini', 'vertex_ai'], 'models_dev' => ['google']],
        'bedrock' => ['litellm' => ['bedrock', 'amazon-bedrock'], 'models_dev' => ['amazon-bedrock', 'aws']],
        'mistral' => ['litellm' => ['mistral'], 'models_dev' => ['mistral']],
        'groq' => ['litellm' => ['groq'], 'models_dev' => ['groq']],
        'deepseek' => ['litellm' => ['deepseek'], 'models_dev' => ['deepseek']],
        'xai' => ['litellm' => ['xai'], 'models_dev' => ['xai']],
        'cohere' => ['litellm' => ['cohere'], 'models_dev' => ['cohere']],
        'azure' => ['litellm' => ['azure']],
    ],
    'modes' => ['base', 'input', 'output', 'cache_read', 'cache_write', 'reasoning', 'audio_input', 'audio_output', 'request'],
];
