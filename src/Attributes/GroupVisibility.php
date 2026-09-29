<?php

namespace Agentoom\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class GroupVisibility
{
    /**
     * @param  string  $group  The visibility group name (e.g. "public", "server_management")
     */
    public function __construct(
        public string $group
    ) {}
}
