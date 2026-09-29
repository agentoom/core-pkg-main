<?php

namespace Agentoom\Core\Traits;

trait ParsesParameters
{
    /**
     * Parse a parameter value based on its type, with lenient support for arrays and objects.
     */
    protected function parseParameterValue(mixed $value, string $type, string $paramName): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = ltrim($type, '?');

        if ($type === 'integer' || $type === 'int') {
            return (int) $value;
        }

        if ($type === 'boolean' || $type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === 'float' || $type === 'double') {
            return (float) $value;
        }

        if (str_contains($type, 'array') || str_contains($type, 'object')) {
            if (! is_string($value)) {
                return str_contains($type, 'array') ? (array) $value : (object) $value;
            }

            $trimmed = trim($value);
            if (empty($trimmed)) {
                return str_contains($type, 'array') ? [] : (object) [];
            }

            // 1. Try standard JSON first
            $decodedValue = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedValue;
            }

            // 2. Lenient parsing for arrays
            if (str_contains($type, 'array')) {
                return $this->lenientParseArray($trimmed);
            }

            // If it's an object and JSON failed, we don't have a lenient parser for objects yet
            throw new \InvalidArgumentException(sprintf(
                'The value for parameter "%s" must be a valid JSON %s. Given: "%s". Error: %s',
                $paramName,
                $type,
                $value,
                json_last_error_msg()
            ));
        }

        return $value;
    }

    /**
     * Recursively parse a structured string (supporting nested [] and ()) into an array.
     */
    protected function lenientParseArray(string $value): array
    {
        $value = trim($value);
        if (empty($value)) {
            return [];
        }

        // Handle multi-line strings that are NOT structured as arrays
        if (! str_starts_with($value, '[') && ! str_starts_with($value, '(') && str_contains($value, "\n")) {
            return array_values(array_filter(array_map('trim', explode("\n", $value))));
        }

        // Strip outer brackets if present
        if ((str_starts_with($value, '[') && str_ends_with($value, ']')) ||
            (str_starts_with($value, '(') && str_ends_with($value, ')'))) {
            $value = trim(substr($value, 1, -1));
        }

        $parts = $this->splitTopLevel($value);
        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // If it looks like a nested array/tuple, recurse
            if ((str_starts_with($part, '[') && str_ends_with($part, ']')) ||
                (str_starts_with($part, '(') && str_ends_with($part, ')'))) {
                $result[] = $this->lenientParseArray($part);
            } else {
                // Strip quotes
                if ((str_starts_with($part, "'") && str_ends_with($part, "'")) ||
                    (str_starts_with($part, '"') && str_ends_with($part, '"'))) {
                    $part = substr($part, 1, -1);
                }
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * Split a string by commas, but only at the top level (ignoring commas inside brackets or strings).
     */
    private function splitTopLevel(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];

            if ($inString) {
                if ($char === $quoteChar && ($i === 0 || $str[$i - 1] !== '\\')) {
                    $inString = false;
                }
                $current .= $char;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quoteChar = $char;
                $current .= $char;

                continue;
            }

            if ($char === '[' || $char === '(') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === ']' || $char === ')') {
                $depth--;
                $current .= $char;

                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
