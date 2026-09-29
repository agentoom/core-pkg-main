<?php

namespace Agentoom\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiActionExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $eventType,
        public readonly ?string $actorId = null,
        public readonly ?string $actorType = null,
        public readonly mixed $input = null,
        public readonly mixed $output = null,
        public readonly array $metadata = [],
    ) {}
}
