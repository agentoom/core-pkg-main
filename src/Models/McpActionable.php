<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Services\McpActionableService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpActionable extends Model
{
    use HasFactory;

    protected $fillable = [
        'mcp_server_id',
        'name',
        'description',
        'category',
        'visibility_group',
        'remote_class',
        'remote_method',
        'parameters_schema',
        'remote_base_url',
        'ai_tool',
    ];

    protected $casts = [
        'parameters_schema' => 'array',
        'ai_tool' => 'boolean',
    ];

    public function mcpServer()
    {
        return $this->belongsTo(McpServer::class);
    }

    /**
     * Execute this actionable remotely.
     */
    public function execute(...$parameters): mixed
    {
        return app(McpActionableService::class)->execute($this, $parameters);
    }
}
