<div class="space-y-6">
    @if (!$nested)
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">
                    {{ __('agentoom-core::admin.agents.learned_skills_title') }}: {{ $agent->name }}
                </flux:heading>
                <flux:subheading>
                    {{ __('agentoom-core::admin.agents.learned_skills_subtitle') }}
                </flux:subheading>
            </div>
            <flux:button :href="route('admin.agents.edit', $agent)" icon="chevron-left" variant="subtle">
                {{ __('agentoom-core::admin.common.back') }}
            </flux:button>
        </div>
    @endif

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:card>
        <div class="mb-4">
            <flux:input wire:model.live="search"
                        icon="magnifying-glass"
                        placeholder="{{ __('agentoom-core::admin.common.search') }}" />
        </div>

        <flux:table :paginate="$skills">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.agents.learned_skill_title') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agents.learned_skill_content') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.created_at') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($skills as $skill)
                    <flux:table.row :key="$skill->id">
                        <flux:table.cell class="font-medium">
                            {{ $skill->title }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-lg">
                            <div class="line-clamp-2" title="{{ $skill->content }}">
                                {{ $skill->content }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $skill->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:button
                                variant="danger"
                                icon="trash"
                                size="sm"
                                wire:click="confirmDelete({{ $skill->id }})">
                                {{ __('agentoom-core::admin.common.delete') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center text-zinc-400">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Delete modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.common.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.common.delete_confirmation_text') }}
            </flux:subheading>

            <div class="flex gap-3 justify-center pt-2">
                <flux:button wire:click="$set('showDeleteModal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="delete">
                    {{ __('agentoom-core::admin.common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
