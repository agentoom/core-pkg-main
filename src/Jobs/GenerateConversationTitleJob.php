<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Services\ConversationTitleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateConversationTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $conversationId) {}

    /**
     * Execute the job.
     */
    public function handle(ConversationTitleService $service): void
    {
        $service->generateTitle($this->conversationId);
    }
}
