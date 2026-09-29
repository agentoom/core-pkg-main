<?php

namespace Agentoom\Core\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphTo;

trait HasOwnership
{
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignOwner(object $owner): void
    {
        $this->owner_type = get_class($owner);
        $this->owner_id = $owner->getKey();
        $this->assigned_at = now();
        $this->save();
    }

    public function hasOwner(): bool
    {
        return $this->owner_id !== null;
    }
}
