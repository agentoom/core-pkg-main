<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ $project ? __('agentoom-core::admin.projects.edit') : __('agentoom-core::admin.projects.new') }}</flux:heading>
        <flux:subheading>{{ $project ? 'Modify project details and budget.' : 'Create a new project to track AI spending.' }}</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="name" label="{{ __('agentoom-core::admin.projects.name') }}" required />

                <flux:select wire:model="userId" label="{{ __('agentoom-core::admin.projects.owner') }}" required>
                    @foreach($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="col-span-1">
                    <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.projects.assigned_users') }}</flux:heading>

                    @if(count($assignedUserIds) > 0)
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach($assignedUserIds as $id)
                                @php
                                    $assignedUser = $users->firstWhere('id', $id);
                                @endphp
                                @if($assignedUser)
                                    <flux:badge size="sm" wire:click="$set('assignedUserIds', {{ json_encode(array_values(array_diff($assignedUserIds, [$id]))) }})">
                                        {{ $assignedUser->name }}
                                        <flux:badge.close class="cursor-pointer" />
                                    </flux:badge>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <flux:select variant="listbox" multiple searchable wire:model.live="assignedUserIds" placeholder="{{ __('agentoom-core::admin.common.select') }}">
                        @foreach($users as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <flux:textarea wire:model="description" label="{{ __('agentoom-core::admin.projects.description') }}" rows="3" />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:input wire:model="budget_limit" type="number" min="0" step="0.01" label="{{ __('agentoom-core::admin.projects.budget_limit') }}" />

                <flux:input wire:model="budget_limit_starts_at" type="date" label="{{ __('Starts At') }}" />

                @if($project?->systemUser)
                    <flux:input label="{{ __('agentoom-core::admin.projects.system_user') }}" value="{{ $project->systemUser->name }} ({{ $project->systemUser->email }})" readonly copyable />
                @endif
            </div>

            <div class="flex items-center gap-2">
                <flux:checkbox wire:model="is_active" label="{{ __('agentoom-core::admin.projects.is_active') }}" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" :href="route('admin.projects.index')">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                @if($project?->exists)
                    <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
                @endif
                <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>

    @if($project?->exists)
        <flux:card class="mt-6">
            <flux:heading size="lg">{{ __('agentoom-core::admin.projects.assigned_resources') }}</flux:heading>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <flux:label>Agents ({{ $agents->count() }})</flux:label>
                    <div class="text-sm text-zinc-500">{{ $agents->pluck('name')->implode(', ') ?: 'None' }}</div>
                </div>
                <div>
                    <flux:label>Endpoints ({{ $endpoints->count() }})</flux:label>
                    <div class="text-sm text-zinc-500">{{ $endpoints->pluck('name')->implode(', ') ?: 'None' }}</div>
                </div>
                <div>
                    <flux:label>Pipelines ({{ $pipelines->count() }})</flux:label>
                    <div class="text-sm text-zinc-500">{{ $pipelines->pluck('name')->implode(', ') ?: 'None' }}</div>
                </div>
                <div>
                    <flux:label>Schedules ({{ $schedules->count() }})</flux:label>
                    <div class="text-sm text-zinc-500">{{ $schedules->pluck('name')->implode(', ') ?: 'None' }}</div>
                </div>
                <div>
                    <flux:label>AI Tasks ({{ $aiTasks->count() }})</flux:label>
                    <div class="text-sm text-zinc-500">{{ $aiTasks->pluck('name')->implode(', ') ?: 'None' }}</div>
                </div>
            </div>
        </flux:card>
    @endif
</div>
