<?php

namespace Agentoom\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

trait HasAdvancedFiltering
{
    /**
     * Apply string-based filters to the query.
     */
    protected function applyFilters(Builder $query, array $filters, string $table): void
    {
        foreach ($filters as $key => $filter) {
            if (empty($filter)) {
                continue;
            }

            // Handle nested arrays for grouping
            if (is_array($filter)) {
                $boolean = 'and';
                if (is_string($key) && strtolower($key) === 'or') {
                    $boolean = 'or';
                } elseif (isset($filter[0]) && is_string($filter[0]) && strtolower($filter[0]) === 'or') {
                    $boolean = 'or';
                    array_shift($filter);
                }

                $query->where(function ($q) use ($filter, $table) {
                    $this->applyFilters($q, $filter, $table);
                }, boolean: $boolean);

                continue;
            }

            $boolean = 'and';
            if (preg_match('/^(OR|AND)\s+/i', $filter, $matches)) {
                $boolean = strtolower($matches[1]);
                $filter = preg_replace('/^(OR|AND)\s+/i', '', $filter);
            }

            // Handle parentheses for grouping in a single string
            if (is_string($filter) && str_starts_with(trim($filter), '(') && str_ends_with(trim($filter), ')')) {
                $filter = trim($filter);
                $inner = substr($filter, 1, -1);
                $subFilters = $this->parseFilterString($inner);

                $query->where(function ($q) use ($subFilters, $table) {
                    $this->applyFilters($q, $subFilters, $table);
                }, boolean: $boolean);

                continue;
            }

            // Handle standard operators: >, <, >=, <=, =, !=
            if (preg_match('/^([\w\-\.]+)\s*(>=|<=|>|<|!=|=)\s*(.+)$/', $filter, $matches)) {
                $field = trim($matches[1]);
                $operator = $matches[2];
                $rawValue = trim($matches[3]);
                $isQuoted = (str_starts_with($rawValue, "'") && str_ends_with($rawValue, "'")) ||
                            (str_starts_with($rawValue, '"') && str_ends_with($rawValue, '"'));

                $value = trim($rawValue, " '\"");

                // Handle relative dates
                if (! $isQuoted && preg_match('/^\d+\s+(days|weeks|months|years|hours|minutes)\s+ago$/i', $value)) {
                    try {
                        $value = Carbon::parse($value)->toDateTimeString();
                    } catch (\Exception $e) {
                    }
                } elseif (! $isQuoted && in_array(strtolower($value), ['yesterday', 'today', 'now'])) {
                    try {
                        $value = Carbon::parse($value)->toDateTimeString();
                    } catch (\Exception $e) {
                    }
                }

                if (! $isQuoted && is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }

                $this->applyCondition($query, $table, $field, $operator, $value, $boolean);

                continue;
            }

            // Handle "contains", "starts with", "ends with" operators
            if (preg_match('/^([\w\-\.]+)\s+(contains|starts\s+with|ends\s+with)\s+(.+)$/i', $filter, $matches)) {
                $field = trim($matches[1]);
                $operator = strtolower(trim($matches[2]));
                $value = trim($matches[3], " '\"");

                if ($operator === 'contains') {
                    $this->applyCondition($query, $table, $field, 'like', "%{$value}%", $boolean);
                } elseif ($operator === 'starts with') {
                    $this->applyCondition($query, $table, $field, 'like', "{$value}%", $boolean);
                } elseif ($operator === 'ends with') {
                    $this->applyCondition($query, $table, $field, 'like', "%{$value}", $boolean);
                }

                continue;
            }

            // Handle "is [not] empty" and "is [not] null"
            if (preg_match('/^([\w\-\.]+)\s+is\s+(not\s+)?(empty|null)$/i', $filter, $matches)) {
                $field = trim($matches[1]);
                $not = ! empty($matches[2]);
                $type = strtolower($matches[3]);

                if ($type === 'null') {
                    $this->applyNullCondition($query, $table, $field, $not, $boolean);
                } else {
                    $this->applyEmptyCondition($query, $table, $field, $not, $boolean);
                }

                continue;
            }
        }
    }

    /**
     * Apply a single condition to the query, deciding between column or JSON field.
     */
    protected function applyCondition(Builder $query, string $table, string $field, string $operator, mixed $value, string $boolean = 'and'): void
    {
        $query->where($this->resolveField($table, $field), $operator, $value, $boolean);
    }

    /**
     * Apply a null check to the query.
     */
    protected function applyNullCondition(Builder $query, string $table, string $field, bool $not, string $boolean = 'and'): void
    {
        $target = $this->resolveField($table, $field);

        if ($not) {
            $query->whereNotNull($target, $boolean);
        } else {
            $query->whereNull($target, $boolean);
        }
    }

    /**
     * Apply an empty check to the query.
     */
    protected function applyEmptyCondition(Builder $query, string $table, string $field, bool $not, string $boolean = 'and'): void
    {
        $target = $this->resolveField($table, $field);

        if ($not) {
            $query->where(function ($q) use ($target) {
                $q->whereNotNull($target)->where($target, '!=', '');
            }, boolean: $boolean);
        } else {
            $query->where(function ($q) use ($target) {
                $q->whereNull($target)->orWhere($target, '=', '');
            }, boolean: $boolean);
        }
    }

    /**
     * Parse a filter string with OR/AND into a list of individual filters.
     */
    protected function parseFilterString(string $string): array
    {
        $parts = preg_split('/\s+(OR|AND)\s+/i', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = [];
        $currentBoolean = '';

        foreach ($parts as $part) {
            $part = trim($part);
            if (in_array(strtoupper($part), ['OR', 'AND'])) {
                $currentBoolean = strtoupper($part).' ';
            } else {
                $result[] = $currentBoolean.$part;
                $currentBoolean = '';
            }
        }

        return $result;
    }

    /**
     * Resolve fields for selection, adding aliases for JSON fields.
     */
    protected function resolveSelectFields(string $table, array $fields): array
    {
        $resolved = [];
        foreach ($fields as $field) {
            $field = (string) $field;
            if (str_contains(strtolower($field), ' as ')) {
                $resolved[] = $field;

                continue;
            }

            // Handle already formatted JSON paths like data->field
            if (str_contains($field, '->')) {
                $parts = explode('->', $field);
                $alias = end($parts);
                $resolved[] = "{$field} as j-{$alias}";

                continue;
            }

            $target = $this->resolveField($table, $field);

            if (str_contains($target, '->')) {
                $alias = $field;
                if (str_starts_with($alias, 'data.')) {
                    $alias = substr($alias, 5);
                } elseif (str_starts_with($alias, 'meta.')) {
                    $alias = substr($alias, 5);
                }
                $alias = str_replace('.', '_', $alias);
                $resolved[] = "{$target} as j-{$alias}";
            } else {
                $resolved[] = $target;
            }
        }

        return $resolved;
    }

    /**
     * Resolve the target field (column or JSON path).
     */
    protected function resolveField(string $table, string $field): string
    {
        // If it's a nested field (contains dot), it's definitely JSON
        if (str_contains($field, '.')) {
            $jsonPath = str_replace('.', '->', $field);
            // We assume it's in the 'data' column by default if no prefix is given
            if (! str_starts_with($jsonPath, 'data->') && ! str_starts_with($jsonPath, 'meta->')) {
                $jsonPath = 'data->'.$jsonPath;
            }

            return $jsonPath;
        }

        // Check if it's a direct column in the table
        if (Schema::hasColumn($table, $field)) {
            return $field;
        }

        // Otherwise, assume it's a field in the 'data' JSON column
        return "data->{$field}";
    }
}
