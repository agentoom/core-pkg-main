<?php

namespace Agentoom\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Sentinelable
{
    public function __construct(
        public string $name = '',
        public string $description = '',
        public string $category = 'General'
    ) {}
}
