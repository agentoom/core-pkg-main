<?php

namespace Agentoom\Core\Exceptions;

use Exception;

class EmergencyStopActiveException extends Exception
{
    public function __construct(
        public readonly string $entityType,
        public readonly int|string $entityId,
    ) {
        parent::__construct("Emergency stop is active for {$this->entityType} #{$this->entityId}. Execution blocked.");
    }
}
