<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Icon Picker') }}</flux:heading>
    </div>

    <flux:card class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search icons...') }}" class="flex-1" />
            <flux:badge variant="subtle">{{ $total }} {{ __('Icons found') }}</flux:badge>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
            @foreach ($icons as $icon)
                <div
                    class="relative flex flex-col items-center justify-center p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors group cursor-pointer"
                    wire:click="selectIcon('{{ $icon }}')"
                    title="{{ __('Click to select') }}"
                >
                    <flux:icon :name="$icon" class="size-8 text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100" />

                    <span class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 truncate w-full text-center" title="{{ $icon }}">
                        {{ $icon }}
                    </span>
                </div>
            @endforeach
        </div>

        @if ($totalPages > 1)
            <div class="mt-8">
                <flux:pagination :paginator="new \Illuminate\Pagination\LengthAwarePaginator($icons, $total, $perPage, $this->getPage())" />
            </div>
        @endif

        @if (empty($icons))
            <div class="flex flex-col items-center justify-center py-12 text-zinc-500">
                <flux:icon.magnifying-glass class="size-12 mb-4 opacity-20" />
                <p>{{ __('No icons found matching your search.') }}</p>
            </div>
        @endif
    </flux:card>
</div>
