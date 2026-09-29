<?php

namespace Agentoom\Core\Traits;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Execution\ActionExecution;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Stringable;

trait HandlesPrivacy
{
    /**
     * Resolve the privacy mapping key from the current execution context.
     *
     * Returns the conversation ID when privacy redaction is enabled,
     * or null when privacy is disabled or no conversation context exists.
     */
    protected function resolvePrivacyMappingKey(): ?string
    {
        if (! app(PrivacyIntegration::class)->isEnabled()) {
            return null;
        }

        try {
            $execution = app(ActionExecution::class);
            $agent = $execution->agent;

            if (! (property_exists($agent, 'conversationId') && ! empty($agent->conversationId))) {
                return null;
            }

            $mappingKey = $agent->conversationId;

            // Respect per-agent anonymization toggle — must match what ProcessAgentChat
            // uses for prompt anonymization to avoid deanonymizing tool arguments when
            // the prompt was never anonymized in the first place.
            if (! app(PrivacyIntegration::class)->shouldAnonymize($mappingKey)) {
                return null;
            }

            return $mappingKey;
        } catch (\Throwable) {
            // No execution context bound — running outside the pipeline
        }

        return null;
    }

    /**
     * Deanonymize an array of arguments by restoring PII placeholders to original values.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function deanonymizeArguments(array $arguments, string $mappingKey): array
    {
        try {
            $privacyService = app(PrivacyIntegration::class);

            return $this->deanonymizeValues($arguments, $privacyService, $mappingKey);
        } catch (\Throwable $e) {
            Log::warning('HandlesPrivacy: Failed to deanonymize arguments', [
                'error' => $e->getMessage(),
            ]);

            return $arguments;
        }
    }

    /**
     * Recursively restore PII placeholders in an array of values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function deanonymizeValues(array $values, PrivacyIntegration $privacyService, string $mappingKey): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value) && $privacyService->hasPlaceholders($value)) {
                $values[$key] = $privacyService->restore($value, $mappingKey);
            } elseif (is_array($value)) {
                $values[$key] = $this->deanonymizeValues($value, $privacyService, $mappingKey);
            }
        }

        return $values;
    }

    /**
     * Anonymize the tool response before returning it to the LLM.
     */
    protected function anonymizeResponse(Stringable|string $response, ?string $mappingKey): Stringable|string
    {
        if ($mappingKey === null) {
            return $response;
        }

        try {
            $privacyService = app(PrivacyIntegration::class);
            $text = (string) $response;

            // Phase 1 (always-on): Replace AGPII tags with deterministic pseudonyms
            // before the Privacy Service and LLM see the text.
            $text = app(PrivacyIntegration::class)->redactAgpiiTags($text);

            // Safely process JSON tool responses without scrambling structural JSON keys
            if ($this->isJson($text)) {
                $decoded = json_decode($text, true);
                $anonymizedArray = $this->anonymizeArrayValues($decoded, $privacyService, $mappingKey);

                return json_encode($anonymizedArray);
            }

            $result = $privacyService->anonymize($text, $mappingKey);

            return $result['text'];
        } catch (\Throwable $e) {
            Log::warning('HandlesPrivacy: Failed to anonymize response', [
                'error' => $e->getMessage(),
            ]);

            return $response;
        }
    }

    /**
     * Recursively anonymize values within a structured tool response array.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    protected function anonymizeArrayValues(array $array, PrivacyIntegration $privacyService, string $mappingKey): array
    {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $result = $privacyService->anonymize($value, $mappingKey);
                $array[$key] = $result['text'];
            } elseif (is_array($value)) {
                $array[$key] = $this->anonymizeArrayValues($value, $privacyService, $mappingKey);
            }
        }

        return $array;
    }

    /**
     * Check if a string is valid JSON.
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if the request contains forbidden security-related terms.
     */
    protected function checkForForbiddenSecurityTerms(Request $request): ?string
    {
        $forbiddenTerms = ['security code', 'verification code', 'otp', 'pin code', '6-digit', '6 digit', 'verification request'];
        $content = strtolower(
            $request->string('subject').' '.
            $request->string('body').' '.
            $request->string('message').' '.
            $request->string('content')
        );

        foreach ($forbiddenTerms as $term) {
            if (str_contains($content, $term)) {
                return 'Error: You are not allowed to send security codes or verification requests manually. The system handles all security verifications automatically. '.
                    'If a security code was required, it has already been sent by the system. DO NOT try to replicate this behavior or send any notification about it to the user. '.
                    'Simply wait for the user to provide the code in the chat.';
            }
        }

        return null;
    }
}
