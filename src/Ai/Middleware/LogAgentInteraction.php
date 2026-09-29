<?php

namespace Agentoom\Core\Ai\Middleware;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\StreamEnd;

class LogAgentInteraction
{
    /**
     * Handle the incoming prompt.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $capture = app(CostCaptureStore::class);
        $capture->begin();

        $user = auth()->user();
        $creditService = app(AiCreditService::class);

        $agentId = null;
        if (method_exists($prompt->agent, 'getAgentModel')) {
            $agentId = $prompt->agent->getAgentModel()->id;
        }

        $projectId = null;
        if (app()->bound('current_project_id')) {
            $projectId = app('current_project_id');
        }

        $conversationId = null;
        if (method_exists($prompt->agent, 'currentConversation')) {
            $conversationId = $prompt->agent->currentConversation();
        }

        if (! $projectId && $conversationId) {
            $conversation = DB::table('agent_conversations')->where('id', $conversationId)->first();
            if ($conversation && $conversation->project_id) {
                $projectId = $conversation->project_id;
            }
        }

        if (! $projectId && $agentId) {
            $agent = Agent::find($agentId);
            if ($agent && $agent->project_id) {
                $projectId = $agent->project_id;
            }
        }

        if (! $creditService->hasSufficientCredits($user, $agentId, $projectId)) {
            app(CostCaptureStore::class)->end();
            $exhausted = $creditService->getExhaustedCreditsResponse();

            return new AgentResponse(
                (string) Str::uuid(),
                json_encode($exhausted),
                new Usage(0, 0, 0),
                new Meta
            );
        }

        if (app()->isLocal()) {
            Log::debug('Agent Interaction: Incoming Prompt', [
                'prompt' => $prompt->prompt,
                'model' => $prompt->model,
                'user_id' => $user?->id,
                'attachments' => collect($prompt->attachments)->map(fn ($a) => [
                    'type' => get_class($a),
                    'name' => method_exists($a, 'getName') ? $a->getName() : null,
                ])->toArray(),
            ]);
        }

        $start = microtime(true);

        try {
            $pending = $next($prompt);
        } catch (\Throwable $e) {
            $this->logUsageOnFailure($user, $prompt, $projectId, $e);
            $capture->end();
            throw $e;
        }

        return $pending->then(function (AgentResponse $response) use ($user, $prompt, $projectId, $start, $conversationId) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            if (app(PrivacyIntegration::class)->shouldAnonymize($conversationId)) {
                try {
                    $rawResponse = $response->text;

                    if (app()->isLocal()) {
                        Log::info('PrivacyService: AI response before deanonymization', [
                            'mapping_key' => $conversationId,
                            'before' => $rawResponse,
                        ]);
                    }

                    $response->text = app(PrivacyIntegration::class)->restore($response->text, $conversationId);

                    if (app()->isLocal()) {
                        Log::info('PrivacyService: AI response after deanonymization', [
                            'mapping_key' => $conversationId,
                            'after' => $response->text,
                        ]);
                    }
                } catch (\Throwable) {
                    // Ignore restoration errors
                }
            }

            app(ComplianceIntegration::class)->traceAgentRun($prompt->agent, $response, null, $duration, ...$this->resolveTraceableContext($prompt));

            if ($response->usage) {
                $agentId = null;
                if (method_exists($prompt->agent, 'getAgentModel')) {
                    $agentId = $prompt->agent->getAgentModel()->id;
                }

                app(AiCreditService::class)->logUsage(
                    $user,
                    get_class($prompt->agent),
                    $response->usage,
                    $response->meta,
                    $agentId,
                    $projectId
                );
            }
            app(CostCaptureStore::class)->end();

            $logData = [
                'invocation_id' => $response->invocationId,
                'conversation_id' => $response->conversationId,
                'content' => $response->text,
                'tool_calls' => $response->toolCalls->map(fn ($tc) => [
                    'name' => $tc->name,
                    'arguments' => $tc->arguments,
                ])->toArray(),
                'tool_results' => $response->toolResults->map(fn ($tr) => [
                    'name' => $tr->name,
                    'result' => $tr->result,
                ])->toArray(),
                'usage' => $response->usage?->toArray(),
            ];

            if (property_exists($response, 'events') && $response->events instanceof Collection) {
                $finishReason = $response->events->whereInstanceOf(StreamEnd::class)->first()?->reason;
                if ($finishReason) {
                    $logData['finish_reason'] = $finishReason;
                }

                $errors = $response->events->whereInstanceOf(Error::class)->map(fn ($e) => [
                    'message' => $e->message,
                    'type' => $e->type,
                ])->values();

                if ($errors->isNotEmpty()) {
                    $logData['errors'] = $errors->toArray();
                }
            }

            if (app()->isLocal()) {
                Log::debug('Agent Interaction: LLM Response', $logData);
            }


            return $response;
        });
    }

    protected function logUsageOnFailure($user, $prompt, $projectId, \Throwable $e): void
    {
        $agentId = null;
        if (method_exists($prompt->agent, 'getAgentModel')) {
            $agentId = $prompt->agent->getAgentModel()->id;
        }

        app(AiCreditService::class)->logUsage(
            $user,
            'agent_interaction_failure',
            new Usage(0, 0, 0),
            new Meta($prompt->provider, $prompt->model),
            $agentId,
            $projectId
        );
    }

    /**
     * Resolve traceable type and ID for headless agent contexts
     * (pipelines, schedules, endpoints, AI tasks).
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveTraceableContext(AgentPrompt $prompt): array
    {
        return $this->resolveTraceableFromMethods($prompt->agent, [
            'getPipelineRunId' => 'pipeline_run',
            'getAiTaskId' => 'ai_task',
            'getScheduleLogId' => 'schedule_log',
            'getEndpointLogId' => 'endpoint_log',
        ]);
    }

    private function resolveTraceableFromMethods(object $agent, array $map): array
    {
        foreach ($map as $method => $type) {
            if (method_exists($agent, $method) && $id = $agent->{$method}()) {
                return [$type, $id];
            }
        }

        return [null, null];
    }
}
