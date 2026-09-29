<?php

namespace Agentoom\Core\Exceptions;

use Exception;

class JumpToStepException extends Exception
{
    public function __construct(public int $stepOrder, string $message = '', public ?array $params = null)
    {
        parent::__construct($message ?: "Jumping to step {$stepOrder}");
    }
}
