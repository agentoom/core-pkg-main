<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpDiscoveryService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class McpServerIndex extends Component
{
    use WithPagination;

    public $show_delete_modal = false;

    public $show_details_modal = false;

    public $server_id_to_delete = null;

    public $delete_agents = false;

    public $delete_skills = false;

    public ?McpServer $selected_server = null;

    public string $mcp_api_token = '';

    public function mount(): void
    {
        $this->mcp_api_token = Configuration::getValue('mcp_api_token', '');
    }

    public function regenerateMcpApiToken(): void
    {
        $this->mcp_api_token = Str::random(64);
        Configuration::setValue('mcp_api_token', $this->mcp_api_token);
        session()->flash('success', __('agentoom-core::admin.mcp_servers.token_regenerated'));
    }

    public function showDetails($serverId)
    {
        $this->selected_server = McpServer::with(['tools', 'resources'])->find($serverId);
        $this->show_details_modal = true;
    }

    public function showDeleteModal($serverId)
    {
        $this->server_id_to_delete = $serverId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        $server = McpServer::find($this->server_id_to_delete);
        if ($server) {
            // Tools and resources always deleted
            $server->tools()->each(function ($tool) {
                $tool->delete(); // This should also handle deassignment if cascade is set or we do it manually
            });
            $server->resources()->delete();

            if ($this->delete_agents) {
                $server->agents()->delete();
            } else {
                // If not deleting agents, at least null out their mcp_server_id?
                // The issue says "one, none or both" for Agents and Skills.
                // If we don't delete them, they stay but without the server link.
                $server->agents()->update(['mcp_server_id' => null]);
            }

            if ($this->delete_skills) {
                $server->skills()->delete();
            } else {
                $server->skills()->update(['mcp_server_id' => null]);
            }

            $server->delete();
        }

        $this->show_delete_modal = false;
        $this->server_id_to_delete = null;
        $this->delete_agents = false;
        $this->delete_skills = false;

        session()->flash('success', __('agentoom-core::admin.mcp_servers.deleted_success'));
    }

    public function sync($serverId)
    {
        $server = McpServer::find($serverId);
        if (! $server) {
            session()->flash('error', 'Server not found');

            return;
        }

        try {
            $discoveryService = app(McpDiscoveryService::class);
            $results = $discoveryService->syncServer($server);

            $summary = __('agentoom-core::admin.mcp_servers.sync_summary', [
                'tools' => $this->formatSyncCount($results['tools']),
                'agents' => $this->formatSyncCount($results['agents']),
                'skills' => $this->formatSyncCount($results['skills']),
            ]);

            session()->flash('success', $summary);

            $skipped = array_merge(
                $results['tools']['skipped'] ?? [],
                $results['agents']['skipped'] ?? [],
                $results['skills']['skipped'] ?? []
            );

            if (! empty($skipped)) {
                session()->flash('warning', __('agentoom-core::admin.mcp_servers.skipped_elements_warning', ['elements' => implode(', ', $skipped)]));
            }
        } catch (\Exception $e) {
            session()->flash('error', __('agentoom-core::admin.mcp_servers.sync_error', ['error' => $e->getMessage()]));
        }
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

    public function render()
    {
        return view('agentoom-core::livewire.admin.mcp-server-index', [
            'servers' => McpServer::withCount(['tools', 'resources'])->orderBy('name')->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
