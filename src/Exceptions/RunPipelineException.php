<?php

namespace Agentoom\Core\Exceptions;

use Exception;

class RunPipelineException extends Exception
{
    public function __construct(public int|string $pipelineIdOrName, string $reason = '', public ?array $input = null)
    {
        $message = "Handing off to pipeline: {$pipelineIdOrName}";
        if ($reason) {
            $message .= " ({$reason})";
        }
        parent::__construct($message);
    }
}
