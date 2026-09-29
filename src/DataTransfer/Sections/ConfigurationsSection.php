<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Configuration;
use Illuminate\Support\Collection;

class ConfigurationsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'configurations';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SYSTEM;
    }

    public function label(): string
    {
        return __('agentoom-core::admin.permissions.groups.configs');
    }

    public function permission(): ?string
    {
        return 'configs.view';
    }

    public function options(): Collection
    {
        return Configuration::all();
    }

    public function optionLabel(mixed $option): string
    {
        return (string) $option->key;
    }

    public function importLevel(): int
    {
        return 0;
    }

    /**
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, array<string, mixed>>
     */
    public function export(array $ids): array
    {
        $query = Configuration::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($config) {
            return [
                'key' => $config->key,
                'value' => Configuration::getValue($config->key),
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $configData) {
            if (isset($configData['key'])) {
                Configuration::setValue($configData['key'], $configData['value'] ?? null);
            }
        }
    }
}
