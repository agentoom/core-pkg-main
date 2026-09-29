<?php

namespace Agentoom\Core\Traits;

trait NormalizesIds
{
    /**
     * Normalize an ID by stripping prefixes like "global:" or "project:".
     */
    protected function normalizeId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $id = (string) $id;

        if (str_starts_with($id, 'global:')) {
            $id = substr($id, 7);
        } elseif (str_starts_with($id, 'project:')) {
            $id = substr($id, 8);
        }

        return is_numeric($id) ? (int) $id : null;
    }
}
