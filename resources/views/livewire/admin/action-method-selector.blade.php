<div>
    <flux:modal wire:model="show" class="md:min-w-[40rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('agentoom-core::admin.direct_actions.select_title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.direct_actions.select_subtitle') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            <div class="flex gap-4">
                <flux:field class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('agentoom-core::admin.direct_actions.search_placeholder') }}" />
                </flux:field>
                <flux:field>
                    <flux:select wire:model.live="sourceFilter" variant="listbox">
                        <flux:select.option value="all">{{ __('agentoom-core::admin.direct_actions.all_sources') }}</flux:select.option>
                        <flux:select.option value="local">{{ __('agentoom-core::admin.direct_actions.local') }}</flux:select.option>
                        <flux:select.option value="mcp">{{ __('agentoom-core::admin.direct_actions.mcp_server') }}</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="max-h-[30rem] overflow-y-auto space-y-2">
                @php
                    $methods = $this->methods;
                    $groupedMethods = collect($methods)->groupBy(fn($m) => $m['source'] ?? 'local', preserveKeys: true);
                @endphp

                @foreach($groupedMethods as $source => $sourceMethods)
                    <div class="mb-4">
                        <div class="text-xs font-bold uppercase text-zinc-400 mb-2 px-2 flex items-center gap-2">
                            @if($source === 'mcp')
                                <flux:icon icon="cloud" variant="mini" />
                                {{ __('agentoom-core::admin.direct_actions.external_mcp') }}
                            @else
                                <flux:icon icon="cpu-chip" variant="mini" />
                                {{ __('agentoom-core::admin.direct_actions.local_actions') }}
                            @endif
                        </div>
                        
                        <div class="grid grid-cols-1 gap-2">
                            @foreach($sourceMethods as $key => $method)
                                <button 
                                    type="button"
                                    wire:click="selectMethod(@js($key))"
                                    class="flex flex-col text-left p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors w-full"
                                >
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-semibold text-sm">{{ $method['name'] }}</span>
                                        <flux:badge size="sm" color="zinc">{{ $method['category'] ?? 'General' }}</flux:badge>
                                    </div>
                                    @if($method['description'] ?? null)
                                        <p class="text-xs text-zinc-500 line-clamp-2">{{ $method['description'] }}</p>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if(empty($methods))
                    <div class="text-center py-8 text-zinc-500">
                        {{ __('agentoom-core::admin.direct_actions.no_actions_found') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end">
                            <flux:button variant="ghost"
                    x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.direct_actions.cancel') }}</flux:button>
        </div>
    </flux:modal>
</div>
