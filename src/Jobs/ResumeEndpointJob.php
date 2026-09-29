<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Services\PipelineRunnerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumeEndpointJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public int $endpointLogId) {}

    public function handle(): void
    {
        $log = EndpointLog::find($this->endpointLogId);

        if (! $log || $log->status !== 'paused') {
            return;
        }

        $endpoint = $log->endpoint;
        if (! $endpoint || ! $endpoint->is_active) {
            return;
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
                    $run = $pipelineRunner->executeRun($run, endpointId: $endpoint->id, endpointLogId: $log->id, background: true);
                } else {
                    $run = $pipelineRunner->run($endpoint->pipeline, json_encode($log->request_data, JSON_PRETTY_PRINT), endpointId: $endpoint->id, endpointLogId: $log->id, background: true);
                }

                $status = $run->status === 'completed' ? 'success' : ($run->status === 'paused' ? 'paused' : 'failure');

                $log->update([
                    'status' => $status,
                    'response_data' => ['output' => $run->output, 'run_id' => $run->id],
                    'error_message' => $run->error,
                ]);

                return;
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

        } catch (ActionPausedException $e) {
            $log->update([
                'status' => 'paused',
                'error_message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error("ResumeEndpointJob failed for log #{$this->endpointLogId}: ".$e->getMessage());

            $log->update([
                'status' => 'failure',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
