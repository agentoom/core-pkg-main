<?php

namespace Agentoom\Core\Exceptions;

use Exception;

class StopPipelineException extends Exception
{
    public function __construct(string $message = 'Agent requested to stop the pipeline', protected string $status = 'stopped')
    {
        parent::__construct($message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
