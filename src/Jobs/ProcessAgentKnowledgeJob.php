<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Models\AgentKnowledge;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAgentKnowledgeJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum characters of extracted text to process (≈ 2 MB of UTF-8 text).
     * Prevents OOM when indexing extremely large documents or URLs.
     */
    private const MAX_TEXT_CHARS = 2_000_000;

    /**
     * Number of chunks to insert per DB batch to keep memory usage flat.
     */
    private const CHUNK_BATCH_SIZE = 50;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $fileRecord = AgentKnowledgeFile::find($this->fileId);

        if (! $fileRecord) {
            return;
        }

        $fileRecord->update(['status' => AgentKnowledgeFile::STATUS_PROCESSING]);

        try {
            $text = '';
            if (in_array($fileRecord->type, [AgentKnowledgeFile::TYPE_FILE, AgentKnowledgeFile::TYPE_TEXT])) {
                $fullPath = storage_path('app/public/'.$fileRecord->path);
                if (! file_exists($fullPath)) {
                    throw new \Exception("File not found at: {$fullPath}");
                }
                $text = Helpers::extractTextFromFile($fullPath);
            } else {
                $text = Helpers::scrapeUrl($fileRecord->path);
            }

            if (empty(trim($text))) {
                throw new \Exception('No text extracted from source.');
            }

            // Guard against extremely large texts that would exhaust memory
            if (strlen($text) > self::MAX_TEXT_CHARS) {
                Log::warning('ProcessAgentKnowledgeJob: text truncated from '.strlen($text).' to '.self::MAX_TEXT_CHARS." chars for file {$fileRecord->id}");
                $text = substr($text, 0, self::MAX_TEXT_CHARS);
            }

            // Semantic chunking
            $chunks = Helpers::semanticChunk($text, 800, 400);

            // Delete old chunks if any
            $fileRecord->knowledge()->delete();

            // Insert chunks in batches to keep memory usage flat
            $now = now()->toDateTimeString();
            $base = [
                'file_id' => $fileRecord->id,
                'agent_id' => $fileRecord->agent_id,
                'project_id' => $fileRecord->project_id,
                'summary' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (array_chunk($chunks, self::CHUNK_BATCH_SIZE) as $batch) {
                $rows = array_map(fn (string $chunk) => array_merge($base, ['knowledge' => $chunk]), $batch);
                DB::table('agent_knowledge')->insert($rows);
            }

            AgentKnowledge::where('file_id', $fileRecord->id)->searchable();

            $fileRecord->update([
                'status' => AgentKnowledgeFile::STATUS_COMPLETED,
                'last_error' => null,
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessAgentKnowledgeJob error: '.$e->getMessage());
            $fileRecord->update([
                'status' => AgentKnowledgeFile::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAgentKnowledgeJob failed: '.$exception->getMessage());

        $fileRecord = AgentKnowledgeFile::find($this->fileId);

        if ($fileRecord) {
            $fileRecord->update([
                'status' => AgentKnowledgeFile::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
