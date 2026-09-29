<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visibility Groups
    |--------------------------------------------------------------------------
    |
    | Control which AI tools and actionable methods are active/loaded.
    | Tools and actionables can be tagged with a #[GroupVisibility('group_name')]
    | attribute. Only groups listed here will be loaded.
    |
    | An empty array means ALL groups are active (no filtering).
    |
    | Set via .env: AGENTOOM_ACTIVE_VISIBILITY_GROUPS="public,server_management"
    | Can be overridden via the admin UI (Global App Config → Visibility Groups).
    |
    */
    'visibility' => [
        'active_groups' => env('AGENTOOM_ACTIVE_VISIBILITY_GROUPS') !== null
            ? array_filter(array_map('trim', explode(',', env('AGENTOOM_ACTIVE_VISIBILITY_GROUPS', ''))))
            : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Limits
    |--------------------------------------------------------------------------
    |
    | Hard caps on how many of each resource this instance may hold. Every cap
    | is read from its own environment variable and applies instance-wide.
    |
    | A cap that is unset (or not numeric) means the resource is not capped.
    |
    | Keys must match the keys registered in ResourceUsageRegistry; optional
    | packages register their own counters and read their own cap here.
    |
    */
    'limits' => [
        'agents' => env('AGENTOOM_MAX_AGENTS'),
        'pipelines' => env('AGENTOOM_MAX_PIPELINES'),
        'endpoints' => env('AGENTOOM_MAX_ENDPOINTS'),
        'resource_types' => env('AGENTOOM_MAX_RESOURCE_TYPES'),
        'resources_per_type' => env('AGENTOOM_MAX_RESOURCES_PER_TYPE'),
        'projects' => env('AGENTOOM_MAX_PROJECTS'),
        'mcp_servers' => env('AGENTOOM_MAX_MCP_SERVERS'),
        'pipeline_workers' => env('AGENTOOM_MAX_PIPELINE_WORKERS'),
        'chat_widgets' => env('AGENTOOM_MAX_CHAT_WIDGETS'),
        'accessibility_widgets' => env('AGENTOOM_MAX_ACCESSIBILITY_WIDGETS'),
        'forms' => env('AGENTOOM_MAX_FORMS'),
        'prompts' => env('AGENTOOM_MAX_PROMPTS'),
        'protected_operations' => env('AGENTOOM_MAX_PROTECTED_OPERATIONS'),
    ],

    'execution' => [
        'pipes' => [
            // Execution pipes are contributed by installed optional packages
            // via Agentoom\Core\Execution\ExecutionPipeRegistry. Additional
            // pipes may still be declared here via config/environment.
        ],
    ],

];
