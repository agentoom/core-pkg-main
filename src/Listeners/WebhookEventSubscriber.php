<?php

namespace Agentoom\Core\Listeners;

use Agentoom\Core\Events\AiActionExecuted;
use Agentoom\Core\Jobs\DispatchWebhookJob;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\WebhookSubscription;
use Illuminate\Events\Dispatcher;

class WebhookEventSubscriber
{
    /**
     * Map internal event types to webhook event names.
     */
    protected function mapEventType(AiActionExecuted $event): ?string
    {
        // Map entityType + eventType combinations to webhook events
        $key = $event->entityType.'.'.$event->eventType;

        return match ($key) {
            'agent.run_completed' => 'agent.run.completed',
            'agent.run_failed' => 'agent.run.failed',
            'pipeline.run_completed' => 'pipeline.completed',
            'pipeline.run_failed' => 'pipeline.failed',
            'endpoint.completed' => 'endpoint.completed',
            'endpoint.failed' => 'endpoint.failed',
            'incident.created' => 'incident.created',
            default => null,
        };
    }

    /**
     * Handle the AiActionExecuted event.
     */
    public function handleAiActionExecuted(AiActionExecuted $event): void
    {
        $webhookEvent = $this->mapEventType($event);

        if ($webhookEvent === null) {
            return;
        }

        $query = WebhookSubscription::where('is_active', true)
            ->whereJsonContains('events', $webhookEvent);

        // Filter by scope: only deliver to subscriptions scoped to this specific
        // agent/pipeline/endpoint, OR subscriptions that are unscoped (null = all entities).
        // Exclude subscriptions that explicitly exclude this entity type.
        $query->where(function ($q) use ($event) {
            if ($event->entityType === 'agent') {
                $q->where('agent_id', $event->entityId)
                    ->orWhereNull('agent_id');
                $q->where('exclude_agents', false);
            } elseif ($event->entityType === 'pipeline') {
                $q->where('pipeline_id', $event->entityId)
                    ->orWhereNull('pipeline_id');
                $q->where('exclude_pipelines', false);
            } elseif ($event->entityType === 'endpoint') {
                $q->where('endpoint_id', $event->entityId)
                    ->orWhereNull('endpoint_id');
            } else {
                // For other entity types (e.g., 'incident'), only match unscoped subscriptions
                $q->whereNull('agent_id')
                    ->whereNull('pipeline_id')
                    ->whereNull('endpoint_id');
            }
        });

        $subscriptions = $query->get();

        $entityName = match ($event->entityType) {
            'agent' => Agent::find($event->entityId)?->name,
            'pipeline' => Pipeline::find($event->entityId)?->name,
            'endpoint' => Endpoint::find($event->entityId)?->name,
            default => null,
        };

        $payload = [
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'entity_name' => $entityName,
            'actor_id' => $event->actorId,
            'actor_type' => $event->actorType,
            'output' => $event->output,
            'metadata' => $event->metadata,
        ];

        foreach ($subscriptions as $subscription) {
            DispatchWebhookJob::dispatch(
                $subscription->id,
                $webhookEvent,
                $payload
            );
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AiActionExecuted::class => 'handleAiActionExecuted',
        ];
    }
}
