@props(['resource', 'resourceTypeId' => null])

@php
    $limits = app(\Agentoom\Core\Services\ResourceLimitManager::class);
    $total = $limits->limitFor($resource);

    if ($resource === 'resources_per_type' && $resourceTypeId) {
        $used = \Agentoom\Core\Models\Resource::where('resource_type_id', (string) $resourceTypeId)->count();
    } else {
        $used = $limits->getUsage()[$resource] ?? 0;
    }

    $isReached = $total !== null && $used >= $total;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <flux:badge :color="$isReached ? 'red' : 'zinc'" size="sm" variant="subtle">
        @if ($total === null)
            {{ __('agentoom-core::admin.resource_limits.usage.unlimited', ['used' => $used]) }}
        @else
            {{ __('agentoom-core::admin.resource_limits.usage.used', ['used' => $used, 'total' => $total]) }}
        @endif
    </flux:badge>

    @if ($isReached)
        <flux:tooltip :content="__('agentoom-core::admin.resource_limits.usage.limit_reached')">
            <flux:icon.exclamation-triangle class="size-4 text-red-500" />
        </flux:tooltip>
    @endif
</div>
