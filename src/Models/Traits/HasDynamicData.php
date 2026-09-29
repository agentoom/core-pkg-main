<?php

namespace Agentoom\Core\Models\Traits;

use Agentoom\Core\Execution\ActionExecution;
use Illuminate\Support\Facades\Crypt;

trait HasDynamicData
{
    public function getDataAttribute($value): array
    {
        $data = json_decode($value ?? '{}', true) ?: [];
        $type = $this->getDynamicType();

        if (! $type) {
            return $data;
        }

        foreach ($type->fields as $field) {
            if (! empty($field['encrypted']) && isset($data[$field['name']])) {
                if (app()->bound(ActionExecution::class)) {
                    $data[$field['name']] = "[ENCRYPTED_REFERENCE:{$field['name']}]";

                    continue;
                }

                try {
                    $data[$field['name']] = Crypt::decryptString($data[$field['name']]);
                } catch (\Exception $e) {
                    // Fail gracefully if not encrypted or key changed
                }
            }
        }

        return $data;
    }

    /**
     * Get the real decrypted data, bypassing any redaction for AI context.
     * This should be used by tools when they need the actual values to perform actions.
     */
    public function getDecryptedData(): array
    {
        $value = $this->attributes['data'] ?? '{}';
        $data = json_decode($value, true) ?: [];
        $type = $this->getDynamicType();

        if (! $type) {
            return $data;
        }

        foreach ($type->fields as $field) {
            if (! empty($field['encrypted']) && isset($data[$field['name']])) {
                try {
                    $data[$field['name']] = Crypt::decryptString($data[$field['name']]);
                } catch (\Exception $e) {
                    // Fail gracefully
                }
            }
        }

        return $data;
    }

    public function setDataAttribute($value): void
    {
        $this->attributes['data'] = json_encode($value);
    }

    public function encryptSensitiveData(): void
    {
        $type = $this->getDynamicType();
        $data = json_decode($this->attributes['data'] ?? '[]', true) ?: [];

        if (! $type || ! is_array($data)) {
            return;
        }

        $originalRaw = $this->getRawOriginal('data');
        $originalData = $originalRaw ? (is_array($originalRaw) ? $originalRaw : (json_decode($originalRaw, true) ?: [])) : [];

        foreach ($type->fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (! $fieldName || empty($field['encrypted']) || ! isset($data[$fieldName])) {
                continue;
            }

            $val = $data[$fieldName];
            $value = is_array($val) ? json_encode($val) : (string) $val;

            if ($value === "[ENCRYPTED_REFERENCE:{$fieldName}]" || $value === '[REDACTED]') {
                if (isset($originalData[$fieldName])) {
                    $data[$fieldName] = $originalData[$fieldName];

                    continue;
                }
            }

            try {
                Crypt::decryptString($value);

                continue;
            } catch (\Exception $e) {
                $data[$fieldName] = Crypt::encryptString($value);
            }
        }
        $this->attributes['data'] = json_encode($data);
    }

    abstract protected function getDynamicType();
}
