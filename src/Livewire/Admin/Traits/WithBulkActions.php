<?php

namespace Agentoom\Core\Livewire\Admin\Traits;

trait WithBulkActions
{
    public array $selected = [];

    public bool $selectAll = false;

    public function updatingPage(): void
    {
        $this->clearSelection();
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selected = $this->allIdsOnPage();
        } else {
            $this->selected = [];
        }
    }

    public function updatedSelected(): void
    {
        $this->selectAll = count($this->selected) === count($this->allIdsOnPage()) && count($this->selected) > 0;
    }

    protected function allIdsOnPage(): array
    {
        // This should be implemented by the component or we can try to guess it from the records
        // For now, let's assume the component provides a way to get current page IDs
        if (method_exists($this, 'getPageIds')) {
            return $this->getPageIds();
        }

        return [];
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    public function getIsAnySelectedProperty(): bool
    {
        return count($this->selected) > 0;
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }
}
