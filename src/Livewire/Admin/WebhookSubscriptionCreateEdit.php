<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\WebhookSubscription;
use Flux;
use Illuminate\Support\Str;
use Livewire\Component;

class WebhookSubscriptionCreateEdit extends Component
{
    public ?WebhookSubscription $subscription = null;

    public string $url = '';

    public string $secret = '';

    public array $selectedEvents = [];

    public bool $is_active = true;

    public bool $is_editing = false;

    public ?int $agentId = null;

    public ?int $pipelineId = null;

    public ?int $endpointId = null;

    public bool $excludeAgents = false;

    public bool $excludePipelines = false;

    public ?string $channel = null;

    public array $channelConfig = [];

    public array $channelOptions = [
        '' => 'HTTP Webhook',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
    ];

    public array $availableEvents = [
        'agent.run.completed' => 'Agent Run Completed',
        'agent.run.failed' => 'Agent Run Failed',
        'pipeline.completed' => 'Pipeline Completed',
        'pipeline.failed' => 'Pipeline Failed',
        'endpoint.completed' => 'Endpoint Completed',
        'endpoint.failed' => 'Endpoint Failed',
        'incident.created' => 'Incident Created',
    ];

    public function mount(?WebhookSubscription $subscription = null): void
    {
        if ($subscription?->exists) {
            $this->subscription = $subscription;
            $this->is_editing = true;
            $this->url = $subscription->url;
            $this->secret = $subscription->secret;
            $this->selectedEvents = $subscription->events ?? [];
            $this->is_active = $subscription->is_active;
            $this->agentId = $subscription->agent_id;
            $this->pipelineId = $subscription->pipeline_id;
            $this->endpointId = $subscription->endpoint_id;
            $this->excludeAgents = $subscription->exclude_agents;
            $this->excludePipelines = $subscription->exclude_pipelines;
            $this->channel = $subscription->channel;
            $this->channelConfig = $subscription->channel_config ?? [];
        } else {
            $this->subscription = new WebhookSubscription;
            $this->secret = Str::random(32);
            $this->is_editing = false;
        }
    }

    public function generateSecret(): void
    {
        $this->secret = Str::random(32);
    }

    /**
     * Whether agent-scoped events are selected (show agent picker).
     */
    public function showAgentPicker(): bool
    {
        return ! empty(array_intersect($this->selectedEvents, ['agent.run.completed', 'agent.run.failed']));
    }

    /**
     * Whether pipeline-scoped events are selected (show pipeline picker).
     */
    public function showPipelinePicker(): bool
    {
        return ! empty(array_intersect($this->selectedEvents, ['pipeline.completed', 'pipeline.failed']));
    }

    /**
     * Whether endpoint-scoped events are selected (show endpoint picker).
     */
    public function showEndpointPicker(): bool
    {
        return ! empty(array_intersect($this->selectedEvents, ['endpoint.completed', 'endpoint.failed']));
    }

    public function save(bool $stay = false)
    {
        $rules = [
            'url' => 'required|url|max:2048',
            'secret' => 'required|string|min:16',
            'selectedEvents' => 'required|array|min:1',
            'is_active' => 'boolean',
        ];

        if ($this->showAgentPicker()) {
            $rules['agentId'] = 'required|exists:agents,id';
        }

        if ($this->showPipelinePicker()) {
            $rules['pipelineId'] = 'required|exists:pipelines,id';
        }

        if ($this->showEndpointPicker()) {
            $rules['endpointId'] = 'required|exists:endpoints,id';
        }

        $this->validate($rules);

        $data = [
            'url' => $this->channel ? null : $this->url,
            'secret' => $this->secret,
            'events' => array_values($this->selectedEvents),
            'is_active' => $this->is_active,
            'agent_id' => ($this->agentId === 0 || $this->agentId === '0') ? null : $this->agentId,
            'pipeline_id' => ($this->pipelineId === 0 || $this->pipelineId === '0') ? null : $this->pipelineId,
            'endpoint_id' => $this->endpointId ?: null,
            'exclude_agents' => $this->excludeAgents || $this->agentId === 0 || $this->agentId === '0',
            'exclude_pipelines' => $this->excludePipelines || $this->pipelineId === 0 || $this->pipelineId === '0',
            'channel' => $this->channel ?: null,
            'channel_config' => $this->channel ? $this->channelConfig : null,
        ];

        if ($this->is_editing) {
            $this->subscription->update($data);
            Flux::toast('Webhook subscription updated.');
        } else {
            $this->subscription = WebhookSubscription::create($data);
            Flux::toast('Webhook subscription created.');
        }

        if ($stay) {
            return $this->redirect(route('admin.webhook-subscriptions.edit', $this->subscription), navigate: true);
        }

        return $this->redirect(route('admin.webhook-subscriptions.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.webhook-subscription-create-edit', [
            'agents' => Agent::orderBy('name')->get(['id', 'name']),
            'pipelines' => Pipeline::orderBy('name')->get(['id', 'name']),
            'endpoints' => Endpoint::orderBy('name')->get(['id', 'name']),
        ])->layout('agentoom-core::layouts.admin');
    }
}
