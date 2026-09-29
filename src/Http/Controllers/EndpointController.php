<?php

namespace Agentoom\Core\Http\Controllers;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Events\AiActionExecuted;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Services\PipelineRunnerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class EndpointController extends Controller
{
    public function process(Request $request, string $slug)
    {
        $endpoint = Endpoint::where('slug', $slug)->first();

        if (! $endpoint || ! $endpoint->is_active) {
            abort(404);
        }

        if (! empty($endpoint->allowed_sources)) {
            $sourceIp = $request->ip();
            $refererHost = parse_url($request->header('referer'), PHP_URL_HOST);
            $originHost = parse_url($request->header('origin'), PHP_URL_HOST);

            $isAllowed = false;
            foreach ($endpoint->allowed_sources as $source) {
                if ($source === $sourceIp || $source === $refererHost || $source === $originHost) {
                    $isAllowed = true;
                    break;
                }
            }

            if (! $isAllowed) {
                return response()->json(['error' => 'Forbidden source'], 403);
            }
        }

        if ($endpoint->auth_token_enabled && $endpoint->auth_token) {
            $headerValue = $request->header($endpoint->auth_token_header);
            if ($headerValue !== $endpoint->auth_token) {
                return response()->json(['error' => 'Forbidden source'], 403);
            }
        }

        $inputData = $request->all();
        $processedData = $inputData;

        // Direct response — acknowledge immediately, then process in background
        if ($endpoint->direct_response_enabled) {
            $body = $endpoint->direct_response_body
                ?: json_encode(['status' => 'accepted', 'message' => 'Request received']);
            $contentType = $endpoint->direct_response_content_type ?: 'application/json';
            $statusCode = $endpoint->direct_response_code ?: 200;

            // Fire-and-forget: dispatch background processing and respond right away
            dispatch(function () use ($endpoint, $inputData, $processedData) {
                (new self)->processEndpointRequest($endpoint, $inputData, $processedData);
            })->onQueue('agent-chat-processing');

            return response($body, $statusCode)->header('Content-Type', $contentType);
        }

        if ($endpoint->project_id) {
            app()->instance('current_project_id', $endpoint->project_id);
        }

        if ($endpoint->project_id && $endpoint->project?->system_user_id) {
            Auth::onceUsingId($endpoint->project->system_user_id);
        } elseif ($endpoint->user_id) {
            Auth::onceUsingId($endpoint->user_id);
        }

        return $this->processEndpointRequest($endpoint, $inputData, $processedData);
    }

    /**
     * Execute the actual endpoint processing (agent or pipeline).
     */
    private function processEndpointRequest(Endpoint $endpoint, array $inputData, array $processedData): mixed
    {
        if ($endpoint->project_id && ! app()->bound('current_project_id')) {
            app()->instance('current_project_id', $endpoint->project_id);
        }

        if (! Auth::check()) {
            if ($endpoint->project_id && $endpoint->project?->system_user_id) {
                Auth::onceUsingId($endpoint->project->system_user_id);
            } elseif ($endpoint->user_id) {
                Auth::onceUsingId($endpoint->user_id);
            }
        }

        // Map data if structure is defined
        if ($endpoint->data_structure && is_array($endpoint->data_structure)) {
            $processedData = [];
            foreach ($endpoint->data_structure as $field) {
                $name = $field['name'];
                $processedData[$name] = $inputData[$name] ?? null;
            }
        }

        $instructions = "Processed data from endpoint '{$endpoint->name}':\n".json_encode($processedData, JSON_PRETTY_PRINT);

        if ($endpoint->prompt) {
            $instructions .= "\n\nAdditional instructions:\n{$endpoint->prompt}";
        }

        $instructions .= "\n\n This data is received in background from an endpoint. DO NOT expect or require any user interaction.
        Figure out alone what to do with this data and respond with a single string with 'task successful'  or failure explaining what went wrong.";

        if ($endpoint->force_json) {
            $instructions .= "\n\nIMPORTANT: You MUST respond with valid JSON only. Your entire response must be a parseable JSON object with at least a 'result' key.";
        }

        $log = EndpointLog::create([
            'project_id' => $endpoint->project_id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'request_data' => $inputData,
        ]);

        // Emergency stop check — blocks execution if a stop is active
        app(ComplianceIntegration::class)->assertNotStopped('endpoint', $endpoint->id);
        if ($endpoint->agent_id) {
            app(ComplianceIntegration::class)->assertNotStopped('agent', $endpoint->agent_id);
        }
        if ($endpoint->pipeline_id) {
            app(ComplianceIntegration::class)->assertNotStopped('pipeline', $endpoint->pipeline_id);
        }

        try {
            if ($endpoint->pipeline_id) {
                $pipelineRunner = app(PipelineRunnerService::class);
                $run = $pipelineRunner->run($endpoint->pipeline, json_encode($processedData, JSON_PRETTY_PRINT), endpointId: $endpoint->id, endpointLogId: $log->id, background: false);

                $status = $run->status === 'completed' ? 'success' : ($run->status === 'paused' ? 'paused' : 'failure');

                $log->update([
                    'status' => $status,
                    'response_data' => ['output' => $run->output, 'run_id' => $run->id],
                    'error_message' => $run->error,
                ]);

                // Dispatch webhook events for pipeline-as-endpoint runs
                if ($run->status === 'completed') {
                    AiActionExecuted::dispatch(
                        entityType: 'endpoint',
                        entityId: $endpoint->id,
                        eventType: 'completed',
                        output: $run->output,
                        metadata: [
                            'pipeline_id' => $endpoint->pipeline_id,
                            'pipeline_run_id' => $run->id,
                        ],
                    );
                } elseif ($run->status !== 'paused') {
                    AiActionExecuted::dispatch(
                        entityType: 'endpoint',
                        entityId: $endpoint->id,
                        eventType: 'failed',
                        output: $run->error,
                        metadata: [
                            'pipeline_id' => $endpoint->pipeline_id,
                            'pipeline_run_id' => $run->id,
                        ],
                    );
                }
                // Note: Paused pipeline runs don't fire webhook events
                // (they'll fire on resume if the completion resolves to success/failure)

                return response()->json([
                    'status' => $status,
                    'response' => $run->output,
                    'error' => $run->error,
                ], $run->status === 'completed' ? 200 : ($run->status === 'paused' ? 202 : 500));
            }

            if (! $endpoint->agent_id) {
                throw new \Exception('Endpoint has no target (agent or pipeline).');
            }

            $agentModel = $endpoint->agent;
            $agentModel->load('skills', 'tools', 'knowledgeFiles');

            $agent = new DynamicAgent($agentModel, isHeadless: true, endpointId: $endpoint->id, endpointLogId: $log->id, user: $endpoint->user);
            $providerConfig = InjectCurrentAiConfigService::inject($agentModel);

            $response = $agent->prompt(
                $instructions,
                provider: $providerConfig['provider_name'],
                model: $providerConfig['model'],
            );

            $log->update([
                'status' => 'success',
                'response_data' => ['text' => $response->text],
            ]);

            // Dispatch webhook event for agent-as-endpoint success
            AiActionExecuted::dispatch(
                entityType: 'endpoint',
                entityId: $endpoint->id,
                eventType: 'completed',
                output: $response->text,
                metadata: ['agent_id' => $endpoint->agent_id],
            );

            app(ComplianceIntegration::class)->dispatchEndpointEvent(
                eventType: 'endpoint.processed',
                endpointId: $endpoint->id,
                agentId: $endpoint->agent_id ?? 0,
                input: $instructions,
                output: $response->text,
            );

            return response()->json([
                'status' => 'success',
                'response' => $response->text,
            ]);

        } catch (ActionPausedException $e) {
            $log->update([
                'status' => 'paused',
                'error_message' => $e->getMessage(),
            ]);

            app(ComplianceIntegration::class)->dispatchEndpointEvent(
                eventType: 'endpoint.processed',
                endpointId: $endpoint->id,
                agentId: $endpoint->agent_id ?? 0,
                input: $instructions,
                metadata: ['status' => 'paused', 'pipeline' => (bool) $endpoint->pipeline_id],
            );

            return response()->json([
                'status' => 'paused',
                'message' => $e->getMessage(),
            ], 202);
        } catch (Throwable $e) {
            Log::error('Endpoint processing failed: '.$e->getMessage(), [
                'endpoint_id' => $endpoint->id,
                'exception' => $e,
            ]);

            $log->update([
                'status' => 'failure',
                'error_message' => $e->getMessage(),
            ]);

            // Dispatch webhook event for agent-as-endpoint failure
            AiActionExecuted::dispatch(
                entityType: 'endpoint',
                entityId: $endpoint->id,
                eventType: 'failed',
                output: $e->getMessage(),
                metadata: [
                    'agent_id' => $endpoint->agent_id,
                    'error' => class_basename($e),
                ],
            );

            app(ComplianceIntegration::class)->dispatchEndpointEvent(
                eventType: 'endpoint.failed',
                endpointId: $endpoint->id,
                agentId: $endpoint->agent_id ?? 0,
                input: $instructions,
                output: $e->getMessage(),
                metadata: ['error' => class_basename($e), 'pipeline' => (bool) $endpoint->pipeline_id],
            );

            return response()->json([
                'status' => 'failure',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resume(EndpointLog $log)
    {
        if ($log->status !== 'paused') {
            return response()->json(['error' => 'Log not found or not paused'], 404);
        }

        $endpoint = $log->endpoint;
        if (! $endpoint || ! $endpoint->is_active) {
            return response()->json(['error' => 'Endpoint not found or inactive'], 404);
        }

        if ($endpoint->project_id) {
            app()->instance('current_project_id', $endpoint->project_id);
        }

        if ($endpoint->project_id && $endpoint->project?->system_user_id) {
            Auth::onceUsingId($endpoint->project->system_user_id);
        } elseif ($endpoint->user_id) {
            Auth::onceUsingId($endpoint->user_id);
        }

        $log->update(['status' => 'pending']);

        try {
            if ($endpoint->pipeline_id) {
                $pipelineRunner = app(PipelineRunnerService::class);
                $runId = $log->response_data['run_id'] ?? null;
                $run = $runId ? PipelineRun::find($runId) : null;

                if ($run) {
                    $run = $pipelineRunner->executeRun($run, endpointId: $endpoint->id, endpointLogId: $log->id, background: false);
                } else {
                    $run = $pipelineRunner->run($endpoint->pipeline, json_encode($log->request_data, JSON_PRETTY_PRINT), endpointId: $endpoint->id, endpointLogId: $log->id, background: false);
                }

                $status = $run->status === 'completed' ? 'success' : ($run->status === 'paused' ? 'paused' : 'failure');

                $log->update([
                    'status' => $status,
                    'response_data' => ['output' => $run->output, 'run_id' => $run->id],
                    'error_message' => $run->error,
                ]);

                return response()->json([
                    'status' => $status,
                    'message' => $run->status === 'completed' ? 'Endpoint pipeline resumed' : ($run->status === 'paused' ? 'Endpoint pipeline paused' : 'Endpoint pipeline failed'),
                ], $run->status === 'completed' ? 200 : ($run->status === 'paused' ? 202 : 500));
            }

            $agentModel = $endpoint->agent;
            $agentModel->load('skills', 'tools', 'knowledgeFiles');

            $agent = new DynamicAgent($agentModel, isHeadless: true, endpointId: $endpoint->id, endpointLogId: $log->id, user: $endpoint->user);
            $providerConfig = InjectCurrentAiConfigService::inject($agentModel);

            $instructions = "Processed data from endpoint '{$endpoint->name}':\n".json_encode($log->request_data, JSON_PRETTY_PRINT);

            if ($endpoint->prompt) {
                $instructions .= "\n\nAdditional instructions:\n{$endpoint->prompt}";
            }

            $instructions .= "\n\n This data is received in background from an endpoint. DO NOT expect or require any user interaction.
            Figure out alone what to do with this data and respond with a single string with 'task successful'  or failure explaining what went wrong.";

            $response = $agent->prompt(
                "Security authorization successful. Please proceed with your previous action. For context, here is the task you were performing:\n\n".$instructions,
                provider: $providerConfig['provider_name'],
                model: $providerConfig['model'],
            );

            $log->update([
                'status' => 'success',
                'response_data' => ['text' => $response->text],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Endpoint agent resumed',
            ]);

        } catch (ActionPausedException $e) {
            $log->update([
                'status' => 'paused',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'paused',
                'message' => $e->getMessage(),
            ], 202);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failure',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failure',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
