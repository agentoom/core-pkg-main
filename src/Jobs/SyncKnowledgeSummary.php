<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledge;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncKnowledgeSummary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public ?int $agentId;

    public ?int $projectId;

    public function __construct(?int $agentId = null, ?int $projectId = null)
    {
        $this->agentId = $agentId;
        $this->projectId = $projectId;
    }

    public function uniqueId(): string
    {
        return $this->agentId ? "agent:{$this->agentId}" : "project:{$this->projectId}";
    }

    public function handle(): void
    {
        $agent = null;
        if ($this->agentId) {
            $agent = Agent::find($this->agentId);
        } elseif ($this->projectId) {
            // Use the first active agent of the project to perform summarization
            $agent = Agent::where('project_id', $this->projectId)->where('is_active', true)->first();

            // If no active agent, just take any agent from the project
            if (! $agent) {
                $agent = Agent::where('project_id', $this->projectId)->first();
            }
        }

        if (! $agent) {
            Log::warning("SyncKnowledgeSummary: no agent found for summarization (Agent: {$this->agentId}, Project: {$this->projectId}).");

            return;
        }

        $connectionSettings = $agent->getConnectionSettings();

        if (! $connectionSettings || empty($connectionSettings['api_key']) || empty($connectionSettings['model']) || empty($connectionSettings['provider_name'])) {
            Log::warning("SyncKnowledgeSummary: agent {$agent->id} has no valid connection settings.");

            return;
        }

        // Load the system "summarizer" agent to drive the summarization via DynamicAgent.
        $summarizerAgent = Agent::where('slug', 'summarizer')->where('system_agent', true)->first();

        if (! $summarizerAgent) {
            Log::error('SyncKnowledgeSummary: system agent with slug "summarizer" not found. Run agentoom:create-resources --agents.');

            return;
        }

        $query = AgentKnowledgeFile::query();

        if ($this->agentId) {
            $query->where('agent_id', $this->agentId);
        } else {
            $query->where('project_id', $this->projectId);
        }

        $files = $query->where('status', '!=', AgentKnowledgeFile::STATUS_COMPLETED)
            ->get();

        if ($files->isEmpty()) {
            return;
        }

        foreach ($files as $file) {
            $file->update(['status' => AgentKnowledgeFile::STATUS_SYNCING]);

            $chunks = $file->knowledge()->whereNull('summary')->get();
            $fileError = null;

            foreach ($chunks as $chunk) {
                try {
                    // Re-inject the calling agent's AI configuration before each prompt so
                    // the runtime Config (provider key, default provider, model) is set
                    // correctly in the queue worker process.
                    $agent->getConnectionSettings();

                    $dynamicAgent = new DynamicAgent(
                        agentModel: $summarizerAgent,
                        isHeadless: true,
                        silent: true,
                    );

                    $response = $dynamicAgent->prompt(
                        $this->singleChunkPrompt($chunk),
                        provider: $connectionSettings['provider_name'],
                        model: $connectionSettings['model']
                    );

                    $summary = trim((string) ($response->text ?? ''));

                    if ($summary === '') {
                        throw new \RuntimeException('Empty summary returned by summarizer agent.');
                    }

                    $chunk->update(['summary' => $summary]);
                } catch (\Throwable $e) {
                    Log::error("SyncKnowledgeSummary error for chunk {$chunk->id} (agent {$agent->id}): ".$e->getMessage());
                    $fileError = $e->getMessage();
                }
            }

            if ($fileError !== null) {
                $file->update([
                    'status' => AgentKnowledgeFile::STATUS_FAILED,
                    'last_error' => $fileError,
                ]);
            } elseif ($file->isFullySummarized()) {
                $file->update([
                    'status' => AgentKnowledgeFile::STATUS_COMPLETED,
                    'summary' => 'Synchronized', // Keep for compatibility if needed
                    'last_error' => null,
                ]);
            } else {
                $file->update([
                    'status' => AgentKnowledgeFile::STATUS_FAILED,
                    'last_error' => 'Synchronization incomplete: some chunks could not be processed.',
                ]);
            }
        }
    }

    private function singleChunkPrompt(AgentKnowledge $chunk): string
    {
        return "Summarize the following text chunk. Return ONLY the summary text (no preamble, no quotes, no markdown), maximum 100 words.\n\n".
            "--- CHUNK (id: {$chunk->id}) ---\n{$chunk->knowledge}\n--- END CHUNK ---\n";
    }
}
