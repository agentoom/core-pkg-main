<?php

namespace Agentoom\Core\Traits;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Stringable;

trait HandlesToolActions
{
    use HandlesPrivacy;

    /**
     * Execute the tool by dispatching to the method specified in the action parameter.
     *
     * When privacy redaction is enabled, deanonymizes the LLM's tool call
     * arguments (restoring PII placeholders to real values) before execution,
     * and anonymizes the tool response before returning it to the LLM.
     */
    public function handle(Request $request): Stringable|string
    {
        // Enforce the forbidden terms guardrail before processing anything
        if ($forbiddenError = $this->checkForForbiddenSecurityTerms($request)) {
            return $forbiddenError;
        }

        $action = (string) $request->string('action');

        if (empty($action)) {
            return 'Error: No action specified in the request.';
        }

        $mappingKey = $this->resolvePrivacyMappingKey();

        if ($mappingKey !== null) {
            $request = $this->deanonymizeRequest($request, $mappingKey);
        }

        // Try direct call (e.g. action="createResource")
        if (method_exists($this, $action)) {
            $result = $this->{$action}($request);

            return $this->anonymizeResponse($result, $mappingKey);
        }

        // Try camelCased call (e.g. action="create_resource" -> createResource)
        $methodName = str($action)->camel()->toString();
        if (method_exists($this, $methodName)) {
            $result = $this->{$methodName}($request);

            return $this->anonymizeResponse($result, $mappingKey);
        }

        return "Error: Action '{$action}' is not supported by this tool.";
    }

    /**
     * Deanonymize the tool request by restoring PII placeholders to original values.
     */
    protected function deanonymizeRequest(Request $request, string $mappingKey): Request
    {
        try {
            $privacyService = app(PrivacyIntegration::class);
            $arguments = $request->toArray();
            $restored = $this->deanonymizeValues($arguments, $privacyService, $mappingKey);

            return new Request($restored);
        } catch (\Throwable $e) {
            Log::warning('HandlesToolActions: Failed to deanonymize request', [
                'error' => $e->getMessage(),
            ]);

            return $request;
        }
    }
}
