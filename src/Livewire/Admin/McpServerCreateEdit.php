<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\ResourceLimitManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Component;

class McpServerCreateEdit extends Component
{
    public ?McpServer $server = null;

    public string $name = '';

    public string $url = '';

    public string $transport_type = 'sse';

    public string $auth_type = '';

    public string $auth_token = '';

    public string $auth_header_name = 'X-API-Key';

    public string $auth_username = '';

    public string $auth_password = '';

    public string $status = 'active';

    public bool $overwrite = false;

    public bool $is_editing = false;

    public function updatedUrl($value)
    {
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1')) {
            session()->flash('warning', 'Using "localhost" or "127.0.0.1" inside Docker might not work. Try using "host.docker.internal" instead.');
        }
    }

    public function mount(?McpServer $server = null)
    {
        if ($server && $server->exists) {
            $this->server = $server->load(['tools', 'resources']);
            $this->is_editing = true;
            $this->name = $server->name;
            $this->url = $server->url;
            $this->transport_type = $server->transport_type;
            $this->auth_type = $server->auth_type ?? '';
            $this->auth_token = '';
            $this->auth_header_name = 'X-API-Key';
            $this->auth_username = '';
            $this->auth_password = '';
            $this->status = $server->status;
        } else {
            $this->is_editing = false;
        }
    }

    public function updatedAuthType(): void
    {
        if ($this->auth_type === '') {
            $this->auth_token = '';
            $this->auth_username = '';
            $this->auth_password = '';
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'url' => ['required', 'url', Rule::when(in_array($this->auth_type, ['bearer', 'api_key', 'basic']), 'starts_with:https://')],
            'transport_type' => 'required|in:sse,http',
            'auth_type' => 'nullable|in:,bearer,api_key,basic',
            'auth_token' => 'nullable|string|max:4096',
            'auth_header_name' => 'nullable|string|max:255',
            'auth_username' => 'nullable|string|max:255',
            'auth_password' => 'nullable|string|max:4096',
            'status' => 'required|in:active,inactive,error',
        ]);

        if (! $this->server && app(ResourceLimitManager::class)->isLimitReached('mcp_servers')) {
            session()->flash('error', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $authData = $this->buildAuthData();

        if ($this->server) {
            $oldAuthType = $this->server->auth_type;
            $this->server->update(array_merge([
                'name' => $this->name,
                'url' => $this->url,
                'transport_type' => $this->transport_type,
                'status' => $this->status,
            ], $authData));
            $this->logAuthChange($oldAuthType);
            $baseMessage = __('agentoom-core::admin.mcp_servers.updated_success');
        } else {
            $this->server = McpServer::create(array_merge([
                'name' => $this->name,
                'url' => $this->url,
                'transport_type' => $this->transport_type,
                'status' => $this->status,
            ], $authData));
            $this->logAuthChange(null);
            $baseMessage = __('agentoom-core::admin.mcp_servers.created_success');
        }

        try {
            $discoveryService = app(McpDiscoveryService::class);
            $results = $discoveryService->syncServer($this->server, $this->overwrite);

            $summary = __('agentoom-core::admin.mcp_servers.sync_summary', [
                'tools' => $this->formatSyncCount($results['tools']),
                'agents' => $this->formatSyncCount($results['agents']),
                'skills' => $this->formatSyncCount($results['skills']),
            ]);

            session()->flash('success', $baseMessage.'. '.$summary);

            $skipped = array_merge(
                $results['tools']['skipped'] ?? [],
                $results['agents']['skipped'] ?? [],
                $results['skills']['skipped'] ?? []
            );

            if (! empty($skipped)) {
                session()->flash('warning', __('agentoom-core::admin.mcp_servers.skipped_elements_warning', ['elements' => implode(', ', $skipped)]));
            }
        } catch (\Exception $e) {
            // Handshake failed, but we still saved the server
            session()->flash('error', __('agentoom-core::admin.mcp_servers.sync_error', ['error' => $e->getMessage()]));
        }

        return $this->redirect(route('admin.mcp-servers.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.mcp-server-create-edit')
            ->layout('agentoom-core::layouts.admin');
    }

    protected function buildAuthData(): array
    {
        $data = [
            'auth_type' => $this->auth_type ?: null,
            'auth_token' => null,
            'auth_header_name' => null,
            'auth_username' => null,
            'auth_password' => null,
        ];

        if ($this->auth_type === 'bearer' || $this->auth_type === 'api_key') {
            $existingToken = $this->server?->auth_token;
            $data['auth_token'] = trim($this->auth_token) ?: $existingToken;
        }

        if ($this->auth_type === 'api_key') {
            $data['auth_header_name'] = trim($this->auth_header_name) ?: 'X-API-Key';
        }

        if ($this->auth_type === 'basic') {
            $data['auth_username'] = trim($this->auth_username) ?: null;
            $existingPassword = $this->server?->auth_password;
            $data['auth_password'] = trim($this->auth_password) ?: $existingPassword;
        }

        return $data;
    }

    protected function logAuthChange(?string $oldAuthType): void
    {
        $newType = $this->auth_type ?: 'none';
        $oldType = $oldAuthType ?: 'none';

        if ($oldType !== $newType || ($newType !== 'none' && $this->isAuthCredentialChanged())) {
            Log::info('MCP server auth credentials changed', [
                'user_id' => auth()->id(),
                'server_id' => $this->server->id,
                'server_name' => $this->server->name,
                'auth_type' => $newType,
                'previous_auth_type' => $oldType,
            ]);
        }
    }

    protected function isAuthCredentialChanged(): bool
    {
        if ($this->auth_token && trim($this->auth_token) !== '') {
            return true;
        }
        if ($this->auth_username && trim($this->auth_username) !== '') {
            return true;
        }
        if ($this->auth_password && trim($this->auth_password) !== '') {
            return true;
        }

        return false;
    }

    protected function formatSyncCount(array $report): string
    {
        $total = $report['imported'] + ($report['existing'] ?? 0) + ($report['updated'] ?? 0) + (isset($report['skipped']) ? count($report['skipped']) : 0);
        if ($total === 0) {
            return '0';
        }

        $parts = [];
        if ($report['imported'] > 0) {
            $parts[] = $report['imported'].' '.__('agentoom-core::admin.common.new');
        }
        if (($report['updated'] ?? 0) > 0) {
            $parts[] = $report['updated'].' '.__('agentoom-core::admin.common.updated');
        }
        if (($report['existing'] ?? 0) > 0) {
            $parts[] = $report['existing'].' '.__('agentoom-core::admin.mcp_servers.already_present');
        }
        if (isset($report['skipped']) && count($report['skipped']) > 0) {
            $parts[] = count($report['skipped']).' '.__('agentoom-core::admin.mcp_servers.skipped');
        }
        if (($report['deleted'] ?? 0) > 0) {
            $parts[] = $report['deleted'].' '.__('agentoom-core::admin.mcp_servers.removed');
        }

        return $total.' ('.implode(', ', $parts).')';
    }
}
