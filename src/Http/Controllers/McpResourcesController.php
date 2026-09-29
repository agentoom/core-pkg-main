<?php

namespace Agentoom\Core\Http\Controllers;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class McpResourcesController extends Controller
{
    public const VALID_TYPES = ['pipeline', 'endpoint', 'webhook', 'schedule', 'agent', 'skill'];

    /**
     * List all resources belonging to an MCP server.
     *
     * Returns a map of all resource types and their key details
     * so that MCP servers can resolve references before creating pipelines,
     * endpoints, or other composite resources.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'mcp_url' => ['required', 'string', 'url'],
        ]);

        $mcpServer = McpServer::where('url', $request->input('mcp_url'))->first();

        if (! $mcpServer) {
            return response()->json(['message' => 'MCP server not found.'], 404);
        }

        return response()->json([
            'mcp_name' => $mcpServer->name,
            'resources' => [
                'pipelines' => $this->formatResourceList(
                    Pipeline::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'name', 'is_active'])
                ),
                'endpoints' => $this->formatResourceList(
                    Endpoint::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'name', 'is_active'])
                ),
                'webhooks' => $this->formatResourceList(
                    WebhookSubscription::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'is_active'])
                ),
                'schedules' => $this->formatResourceList(
                    Schedule::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'name', 'active as is_active'])
                ),
                'agents' => $this->formatResourceList(
                    Agent::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'name', 'is_active'])
                ),
                'skills' => $this->formatResourceList(
                    AgentSkill::where('mcp_server_id', $mcpServer->id)->get(['id', 'slug', 'name', 'is_active'])
                ),
                'actionables' => McpActionable::where('mcp_server_id', $mcpServer->id)
                    ->get(['id', 'name', 'description', 'category', 'remote_class', 'remote_method', 'ai_tool'])
                    ->toArray(),
            ],
        ]);
    }

    /**
     * Format a collection of resources into an array of id/slug/name entries.
     */
    protected function formatResourceList(Collection $items): array
    {
        return $items->map(fn ($item) => [
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name ?? null,
            'is_active' => $item->is_active ?? null,
        ])->toArray();
    }

    /**
     * Create a new resource (pipeline, endpoint, webhook, or schedule) on behalf of an MCP server.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mcp_url' => ['required', 'string', 'url'],
            'type' => ['required', 'string', Rule::in(self::VALID_TYPES)],
            'slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            // Pipeline-specific
            'model' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'variables' => ['nullable', 'array'],
            'steps' => ['nullable', 'array'],
            'steps.*.name' => ['nullable', 'string', 'max:255'],
            'steps.*.order' => ['nullable', 'integer', 'min:0'],
            'steps.*.type' => ['nullable', 'string', 'in:agent,custom,receive_data'],
            'steps.*.agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'steps.*.class' => ['nullable', 'string', 'max:500'],
            'steps.*.method' => ['nullable', 'string', 'max:255'],
            'steps.*.mcp_actionable_id' => ['nullable', 'integer', 'exists:mcp_actionables,id'],
            'steps.*.instructions_override' => ['nullable', 'string'],
            'steps.*.pass_output_as_input' => ['nullable', 'boolean'],
            'steps.*.pass_all_previous_outputs' => ['nullable', 'boolean'],
            'steps.*.selected_outputs' => ['nullable', 'array'],
            'steps.*.selected_outputs.*' => ['integer'],
            'steps.*.condition_if' => ['nullable', 'string'],
            'steps.*.next_step_on_true' => ['nullable', 'integer'],
            'steps.*.next_step_on_false' => ['nullable', 'integer'],
            'steps.*.on_error' => ['nullable', 'string', 'in:continue,fail,retry,jump'],
            'steps.*.next_step_on_error' => ['nullable', 'integer'],
            'steps.*.retry_attempts' => ['nullable', 'integer', 'min:0', 'max:10'],
            'steps.*.retry_delay' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'steps.*.condition_on_previous' => ['nullable', 'boolean'],
            'steps.*.condition_on_input' => ['nullable', 'boolean'],
            'steps.*.condition_on_current' => ['nullable', 'boolean'],
            'steps.*.force_json' => ['nullable', 'boolean'],
            'steps.*.is_active' => ['nullable', 'boolean'],
            'steps.*.model' => ['nullable', 'string', 'max:255'],
            // Failure handler steps (same structure)
            'failure_step' => ['nullable', 'array'],
            'failure_step.*.name' => ['nullable', 'string', 'max:255'],
            'failure_step.*.order' => ['nullable', 'integer', 'min:0'],
            'failure_step.*.type' => ['nullable', 'string', 'in:agent,custom,receive_data'],
            'failure_step.*.agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'failure_step.*.class' => ['nullable', 'string', 'max:500'],
            'failure_step.*.method' => ['nullable', 'string', 'max:255'],
            'failure_step.*.mcp_actionable_id' => ['nullable', 'integer', 'exists:mcp_actionables,id'],
            'failure_step.*.instructions_override' => ['nullable', 'string'],
            'failure_step.*.pass_output_as_input' => ['nullable', 'boolean'],
            'failure_step.*.pass_all_previous_outputs' => ['nullable', 'boolean'],
            'failure_step.*.selected_outputs' => ['nullable', 'array'],
            'failure_step.*.selected_outputs.*' => ['integer'],
            'failure_step.*.condition_if' => ['nullable', 'string'],
            'failure_step.*.on_error' => ['nullable', 'string', 'in:continue,fail,retry,jump'],
            'failure_step.*.retry_attempts' => ['nullable', 'integer', 'min:0', 'max:10'],
            'failure_step.*.retry_delay' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'failure_step.*.force_json' => ['nullable', 'boolean'],
            'failure_step.*.is_active' => ['nullable', 'boolean'],
            'failure_step.*.model' => ['nullable', 'string', 'max:255'],
            // Endpoint-specific
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
            'data_structure' => ['nullable', 'array'],
            'allowed_sources' => ['nullable', 'string'],
            // Webhook-specific
            'url' => ['nullable', 'string', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'channel' => ['nullable', 'string', 'max:255'],
            'channel_config' => ['nullable', 'array'],
            // Schedule-specific
            'minute' => ['nullable', 'string', 'max:30'],
            'hour' => ['nullable', 'string', 'max:30'],
            'day' => ['nullable', 'string', 'max:30'],
            'month' => ['nullable', 'string', 'max:30'],
            'day_of_week' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'schedule_model' => ['nullable', 'string', 'max:255'],
            'model_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'repository_instructions' => ['nullable', 'string'],
            // Agent-specific
            'role' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string'],
            // Skill-specific
            'musts' => ['nullable', 'array'],
            'donts' => ['nullable', 'array'],
            'if_this_then' => ['nullable', 'array'],
        ]);

        $mcpServer = McpServer::where('url', $validated['mcp_url'])->first();

        if (! $mcpServer) {
            return response()->json(['message' => 'MCP server not found.'], 404);
        }

        $type = $validated['type'];
        $action = 'created';

        switch ($type) {
            case 'pipeline':
                [$resource, $action] = $this->storePipeline($validated, $mcpServer);
                break;
            case 'endpoint':
                [$resource, $action] = $this->storeEndpoint($validated, $mcpServer);
                break;
            case 'webhook':
                [$resource, $action] = $this->storeWebhook($validated, $mcpServer);
                break;
            case 'schedule':
                [$resource, $action] = $this->storeSchedule($validated, $mcpServer);
                break;
            case 'agent':
                [$resource, $action] = $this->storeAgent($validated, $mcpServer);
                break;
            case 'skill':
                [$resource, $action] = $this->storeSkill($validated, $mcpServer);
                break;
        }

        return response()->json([
            'message' => ucfirst($type).' '.$action.' successfully.',
            'action' => $action,
            'name' => $resource->name ?? $validated['name'] ?? $resource->slug,
            'slug' => $resource->slug,
            'id' => $resource->id,
            'mcp_name' => $mcpServer->name,
        ], $action === 'created' ? 201 : 200);
    }

    /**
     * Store or update a pipeline, optionally with steps and variables.
     *
     * @return array{0: Pipeline, 1: string} The pipeline and the action ('created' or 'updated')
     */
    protected function storePipeline(array $data, McpServer $mcpServer): array
    {
        return DB::transaction(function () use ($data, $mcpServer) {
            // Merge variables into config
            $config = $data['config'] ?? [];
            if (! empty($data['variables'])) {
                $config['variables'] = $data['variables'];
            }

            $attrs = [
                'mcp_server_id' => $mcpServer->id,
                'project_id' => $mcpServer->project_id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => Pipeline::TYPE_STANDARD,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'model' => $data['model'] ?? null,
                'config' => ! empty($config) ? $config : null,
            ];

            $existing = Pipeline::where('mcp_server_id', $mcpServer->id)
                ->where('slug', $data['slug'])
                ->first();

            if ($existing) {
                $existing->update(array_merge($attrs, [
                    'project_id' => $existing->project_id ?? $mcpServer->project_id,
                ]));
                $pipeline = $existing;
                $action = 'updated';

                // Replace steps on update
                $pipeline->steps()->delete();
            } else {
                $pipeline = Pipeline::create($attrs);
                $action = 'created';
            }

            // Store regular steps
            if (! empty($data['steps'])) {
                foreach ($data['steps'] as $index => $stepData) {
                    $pipeline->steps()->create($this->buildStepAttributes($stepData, $index));
                }
            }

            // Store failure handler steps
            if (! empty($data['failure_step'])) {
                foreach ($data['failure_step'] as $index => $stepData) {
                    $attrs = $this->buildStepAttributes($stepData, $index);
                    $attrs['is_failure_handler'] = true;
                    $pipeline->steps()->create($attrs);
                }
            }

            return [$pipeline, $action];
        });
    }

    /**
     * Build PipelineStep attributes from MCP request data.
     *
     * @param  array  $stepData  Raw step data from the MCP request
     * @param  int  $index  Zero-based index in the steps array (used as order if not provided)
     */
    protected function buildStepAttributes(array $stepData, int $index): array
    {
        return [
            'order' => $stepData['order'] ?? $index,
            'name' => $stepData['name'] ?? null,
            'agent_id' => $stepData['agent_id'] ?? null,
            'class' => $stepData['class'] ?? null,
            'method' => $stepData['method'] ?? null,
            'mcp_actionable_id' => $stepData['mcp_actionable_id'] ?? null,
            'instructions_override' => $stepData['instructions_override'] ?? null,
            'pass_output_as_input' => $stepData['pass_output_as_input'] ?? false,
            'pass_all_previous_outputs' => $stepData['pass_all_previous_outputs'] ?? false,
            'selected_outputs' => $stepData['selected_outputs'] ?? [],
            'condition_if' => $stepData['condition_if'] ?? null,
            'next_step_on_true' => $stepData['next_step_on_true'] ?? null,
            'next_step_on_false' => $stepData['next_step_on_false'] ?? null,
            'on_error' => $stepData['on_error'] ?? 'continue',
            'next_step_on_error' => $stepData['next_step_on_error'] ?? null,
            'retry_attempts' => $stepData['retry_attempts'] ?? 0,
            'retry_delay' => $stepData['retry_delay'] ?? 0,
            'condition_on_previous' => $stepData['condition_on_previous'] ?? false,
            'condition_on_input' => $stepData['condition_on_input'] ?? false,
            'condition_on_current' => $stepData['condition_on_current'] ?? false,
            'force_json' => $stepData['force_json'] ?? false,
            'is_active' => $stepData['is_active'] ?? true,
            'model' => $stepData['model'] ?? null,
        ];
    }

    /**
     * Store or update an endpoint.
     *
     * @return array{0: Endpoint, 1: string}
     */
    protected function storeEndpoint(array $data, McpServer $mcpServer): array
    {
        $attrs = [
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'agent_id' => $data['agent_id'] ?? null,
            'pipeline_id' => $data['pipeline_id'] ?? null,
            'data_structure' => $data['data_structure'] ?? null,
            'allowed_sources' => $data['allowed_sources'] ?? null,
        ];

        $existing = Endpoint::where('mcp_server_id', $mcpServer->id)
            ->where('slug', $data['slug'])
            ->first();

        if ($existing) {
            $existing->update(array_merge($attrs, [
                'project_id' => $existing->project_id ?? $mcpServer->project_id,
            ]));

            return [$existing, 'updated'];
        }

        return [Endpoint::create($attrs), 'created'];
    }

    /**
     * Store or update a webhook subscription.
     *
     * @return array{0: WebhookSubscription, 1: string}
     */
    protected function storeWebhook(array $data, McpServer $mcpServer): array
    {
        $attrs = [
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'url' => $data['url'] ?? '',
            'slug' => $data['slug'],
            'secret' => $data['secret'] ?? Str::random(32),
            'events' => $data['events'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'channel' => $data['channel'] ?? null,
            'channel_config' => $data['channel_config'] ?? null,
        ];

        $existing = WebhookSubscription::where('mcp_server_id', $mcpServer->id)
            ->where('slug', $data['slug'])
            ->first();

        if ($existing) {
            $existing->update(array_merge($attrs, [
                'project_id' => $existing->project_id ?? $mcpServer->project_id,
            ]));

            return [$existing, 'updated'];
        }

        return [WebhookSubscription::create($attrs), 'created'];
    }

    /**
     * Store or update a schedule.
     *
     * @return array{0: Schedule, 1: string}
     */
    protected function storeSchedule(array $data, McpServer $mcpServer): array
    {
        $attrs = [
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'minute' => $data['minute'] ?? '*',
            'hour' => $data['hour'] ?? '*',
            'day' => $data['day'] ?? '*',
            'month' => $data['month'] ?? '*',
            'day_of_week' => $data['day_of_week'] ?? '*',
            'timezone' => $data['timezone'] ?? 'UTC',
            'model' => $data['schedule_model'] ?? '',
            'model_id' => $data['model_id'] ?? null,
            'method' => $data['method'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'repository_instructions' => $data['repository_instructions'] ?? null,
            'active' => $data['is_active'] ?? false,
        ];

        $existing = Schedule::where('mcp_server_id', $mcpServer->id)
            ->where('slug', $data['slug'])
            ->first();

        if ($existing) {
            $existing->update(array_merge($attrs, [
                'project_id' => $existing->project_id ?? $mcpServer->project_id,
            ]));

            return [$existing, 'updated'];
        }

        return [Schedule::create($attrs), 'created'];
    }

    /**
     * Store or update an agent.
     *
     * @return array{0: Agent, 1: string}
     */
    protected function storeAgent(array $data, McpServer $mcpServer): array
    {
        $attrs = [
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'remote_name' => $data['name'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'instructions' => $data['instructions'] ?? '',
            'role' => $data['role'] ?? null,
            'objective' => $data['objective'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? true,
            'config' => $data['config'] ?? [],
        ];

        $existing = Agent::where('mcp_server_id', $mcpServer->id)
            ->where('slug', $data['slug'])
            ->first();

        // Fallback: match by remote_name when the MCP sends a different slug
        // than what Core previously stored (e.g. discovery uses unprefixed slugs
        // like "crypto-trigger-analyst" while syncAgents uses prefixed slugs like
        // "agentoom-crypto--crypto-trigger-analyst").
        if (! $existing) {
            $existing = Agent::where('mcp_server_id', $mcpServer->id)
                ->where('remote_name', $data['name'])
                ->first();
        }

        if ($existing) {
            $existing->update(array_merge($attrs, [
                'project_id' => $existing->project_id ?? $mcpServer->project_id,
            ]));
            $agent = $existing;
            $action = 'updated';
        } else {
            $agent = Agent::create($attrs);
            $action = 'created';
        }

        // Sync tools if provided as an array of tool names/class names
        if (isset($data['tools']) && is_array($data['tools'])) {
            $toolIds = Tool::where('mcp_server_id', $mcpServer->id)
                ->orWhereNull('mcp_server_id')
                ->where(function ($q) use ($data) {
                    foreach ($data['tools'] as $toolRef) {
                        $q->orWhere('remote_name', $toolRef)
                            ->orWhere('class_name', $toolRef)
                            ->orWhere('class_name', 'like', '%\\'.class_basename($toolRef));
                    }
                })
                ->pluck('id')
                ->toArray();

            $agent->tools()->sync($toolIds);
        }

        // Sync skills if provided as an array of skill names/slugs
        if (isset($data['skills']) && is_array($data['skills'])) {
            $skillIds = AgentSkill::where('mcp_server_id', $mcpServer->id)
                ->orWhereNull('mcp_server_id')
                ->where(function ($q) use ($data, $mcpServer) {
                    foreach ($data['skills'] as $skillRef) {
                        $q->orWhere('remote_name', $skillRef)
                            ->orWhere('slug', $skillRef)
                            ->orWhere('slug', $mcpServer->id.'-'.$skillRef);
                    }
                })
                ->pluck('id')
                ->toArray();

            $agent->skills()->sync($skillIds);
        }

        return [$agent, $action];
    }

    /**
     * Store or update a skill.
     *
     * @return array{0: AgentSkill, 1: string}
     */
    protected function storeSkill(array $data, McpServer $mcpServer): array
    {
        $attrs = [
            'mcp_server_id' => $mcpServer->id,
            'remote_name' => $data['name'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'instructions' => $data['instructions'] ?? '',
            'musts' => $data['musts'] ?? [],
            'donts' => $data['donts'] ?? [],
            'if_this_then' => $data['if_this_then'] ?? [],
            'tools' => $data['tools'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ];

        $existing = AgentSkill::where('mcp_server_id', $mcpServer->id)
            ->where('slug', $data['slug'])
            ->first();

        // Fallback: match by remote_name when the MCP sends a different slug
        if (! $existing) {
            $existing = AgentSkill::where('mcp_server_id', $mcpServer->id)
                ->where('remote_name', $data['name'])
                ->first();
        }

        if ($existing) {
            $existing->update($attrs);

            return [$existing, 'updated'];
        }

        return [AgentSkill::create($attrs), 'created'];
    }
}
