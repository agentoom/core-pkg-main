<div>
    <div class="space-y-4">
        @foreach($fields as $index => $field)
            <div class="flex items-start gap-4" wire:key="field-{{ $index }}">
                <div class="flex-1">
                    <flux:input label="{{ __('agentoom-core::admin.endpoints.field_name') }}" wire:model="fields.{{ $index }}.name" placeholder="e.g. user_id" />
                </div>
                <div class="flex-1">
                    <flux:select label="{{ __('agentoom-core::admin.endpoints.field_type') }}" wire:model="fields.{{ $index }}.type">
                        <flux:select.option value="string">String</flux:select.option>
                        <flux:select.option value="integer">Integer</flux:select.option>
                        <flux:select.option value="boolean">Boolean</flux:select.option>
                        <flux:select.option value="array">Array</flux:select.option>
                        <flux:select.option value="object">Object</flux:select.option>
                    </flux:select>
                </div>
                <div class="flex-1">
                    <flux:textarea label="{{ __('agentoom-core::admin.common.value') }}" wire:model="fields.{{ $index }}.value" placeholder="e.g. value" rows="1" />
                    @if(!empty($fields[$index]['description'] ?? ''))
                        <flux:description size="sm" class="mt-1">{{ $fields[$index]['description'] }}</flux:description>
                    @endif
                    @if(($fields[$index]['name'] ?? '') === 'filters')
                        <div class="mt-2">
                            <flux:modal.trigger name="filters-help">
                                <flux:button variant="subtle" size="xs" icon="question-mark-circle">{{ __('Learn more about filters') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                    @if(($fields[$index]['name'] ?? '') === 'fields')
                        <div class="mt-2">
                            <flux:modal.trigger name="fields-help">
                                <flux:button variant="subtle" size="xs" icon="question-mark-circle">{{ __('Learn more about fields') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                </div>
                <div class="flex-[2]">
                    <flux:input label="{{ __('agentoom-core::admin.endpoints.field_description') }}" wire:model="fields.{{ $index }}.description" placeholder="{{ __('agentoom-core::admin.endpoints.field_description_placeholder') }}" />
                </div>
                <div class="mt-8">
                    <flux:button variant="danger" icon="trash" wire:click="removeField({{ $index }})" />
                </div>
            </div>
        @endforeach

        <div class="flex justify-between">
            <flux:button variant="subtle" icon="plus" wire:click="addField">
                {{ __('agentoom-core::admin.endpoints.add_field') }}
            </flux:button>
            <flux:button variant="primary" wire:click="save">
                {{ __('agentoom-core::admin.endpoints.confirm_structure') }}
            </flux:button>
        </div>

        @once
        <flux:modal name="filters-help" title="Filter Documentation" class="max-w-2xl">
            <div class="space-y-6">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Filters allow you to retrieve specific resources based on their attributes and metadata. You can enter one filter per line or use complex nested logic.') }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Standard Operators</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>price > 100</code></li>
                            <li><code>status = 'active'</code></li>
                            <li><code>quantity <= 10</code></li>
                            <li><code>type != 'legacy'</code></li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Text Matching</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>name contains 'John'</code></li>
                            <li><code>sku starts with 'ABC'</code></li>
                            <li><code>email ends with '@gmail.com'</code></li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Null & Empty</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>email is null</code></li>
                            <li><code>phone is not null</code></li>
                            <li><code>address is empty</code></li>
                            <li><code>notes is not empty</code></li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Relative Dates</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>created_at < '7 days ago'</code></li>
                            <li><code>updated_at > '2 weeks ago'</code></li>
                            <li><code>scheduled_at = 'today'</code></li>
                            <li><code>finished_at < 'now'</code></li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">JSON Fields</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>data.category = 'electronics'</code></li>
                            <li><code>meta.source = 'web'</code></li>
                            <li><code>profile.age >= 18</code> (assumes <code>data->profile->age</code>)</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Grouping</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>OR status = 'pending'</code></li>
                            <li><code>AND price < 50</code></li>
                            <li><code>[('f1 = v1', 'OR f1 = v2'), 'AND f2 = v3']</code></li>
                        </ul>
                    </section>
                </div>

                <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <p class="text-[11px] text-zinc-500 italic">
                        {{ __('Tip: When using grouping with nested arrays, you can use parentheses () or brackets [] to define the group. Strings should be quoted if they contain spaces.') }}
                    </p>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="fields-help" title="Fields Documentation" class="max-w-md">
            <div class="space-y-6">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('The fields parameter allows you to specify which columns to retrieve from the database. Enter one field name per line.') }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Resources Fields</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>id</code></li>
                            <li><code>name</code></li>
                            <li><code>description</code></li>
                            <li><code>data</code> (JSON)</li>
                            <li><code>created_at</code></li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Scraped Data Fields</h4>
                        <ul class="text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400">
                            <li><code>id</code></li>
                            <li><code>url</code></li>
                            <li><code>title</code></li>
                            <li><code>content</code></li>
                            <li><code>published_at</code></li>
                            <li><code>data</code> (JSON)</li>
                        </ul>
                    </section>
                </div>

                <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <p class="text-[11px] text-zinc-500 italic">
                        {{ __('Tip: If you leave this field empty, all available fields will be retrieved by default.') }}
                    </p>
                </div>
            </div>
        </flux:modal>
        @endonce
    </div>
</div>
