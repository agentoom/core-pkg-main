<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('agentoom-core::admin.configs.title') }}</flux:heading>
{{--        <flux:subheading>{{ __('agentoom-core::admin.configs.subtitle') }}</flux:subheading>--}}
    </div>

    {{-- Flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:tab.group :selected="auth()->user()->hasRole('superadmin') ? 'general' : 'billing'">
        <flux:tabs variant="pills">
            @if (auth()->user()->hasRole('superadmin'))
                <flux:tab name="general" icon="cog-6-tooth">{{ __('agentoom-core::admin.configs.tab_general') }}</flux:tab>
                <flux:tab name="ai" icon="cpu-chip">{{ __('agentoom-core::admin.configs.tab_ai') }}</flux:tab>
                <flux:tab name="chat" icon="chat-bubble-left-right">{{ __('agentoom-core::admin.configs.tab_chat') }}</flux:tab>
                <flux:tab name="compliance" icon="shield-check">{{ __('agentoom-core::admin.configs.tab_compliance') }}</flux:tab>
                <flux:tab name="visibility" icon="eye">{{ __('agentoom-core::admin.configs.tab_visibility') }}</flux:tab>
            @endif
            <flux:tab name="billing" icon="credit-card">{{ __('agentoom-core::admin.configs.tab_billing') }}</flux:tab>
        </flux:tabs>

        @if (auth()->user()->hasRole('superadmin'))
            <flux:tab.panel name="general" class="space-y-4 pt-4">
            <form wire:submit="save" class="space-y-4">
                {{-- General App Settings --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.title') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('agentoom-core::admin.configs.app_logo') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.app_logo_description') }}</flux:description>
                            <div class="flex items-center gap-4 mt-2">
                                @if($app_logo_url)
                                    <img src="{{ $app_logo_url }}" alt="App Logo" class="h-12 w-auto object-contain bg-gray-100 dark:bg-white/10 rounded p-1">
                                @endif
                                <flux:input type="file" wire:model="app_logo" accept="image/*" />
                            </div>
                            <flux:error name="app_logo" />
                        </flux:field>

                        <flux:editor
                            wire:model="email_footer_content"
                            label="{{ __('agentoom-core::admin.configs.email_footer_content') }}"
                            class="md:col-span-2" />
                    </div>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.common.save') }}
                    </flux:button>
                </div>
            </form>
        </flux:tab.panel>

        <flux:tab.panel name="ai" class="space-y-4 pt-4">
            <form wire:submit="save" class="space-y-4">
                {{-- Connection --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_connection') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if (count($providers) > 1)
                        <flux:select
                            wire:model.live="ai_provider"
                            searchable
                            label="{{ __('agentoom-core::admin.configs.provider') }}"
                            icon="server">
                            <flux:select.option value="0">{{ __('agentoom-core::admin.configs.select_provider') }}</flux:select.option>
                            @foreach($providers as $id => $name)
                                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @endif

                        <flux:input
                            wire:model="ai_api_key"
                            type="password"
                            label="{{ __('agentoom-core::admin.configs.api_key') }}"
                            placeholder="{{ __('agentoom-core::admin.configs.api_key_placeholder') }}"
                            viewable
                            icon="key"
                            autocomplete="off"
                            data-bwignore="true"
                            data-1p-ignore
                            data-lpignore="true" />
                    </div>

                    @php
                        $currentAiProviderName = isset($providers[$ai_provider]) ? $providers[$ai_provider] : '';
                    @endphp

                    @if ($currentAiProviderName === 'openrouter')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if (auth()->user()->hasRole('superadmin'))
                                <flux:select wire:model.live="ai_output_modality" label="{{ __('Output Modality') }}" icon="adjustments-horizontal">
                                    <flux:select.option value="all">{{ __('All Modalities') }}</flux:select.option>
                                    <flux:select.option value="text">{{ __('Text') }}</flux:select.option>
                                    <flux:select.option value="image">{{ __('Image') }}</flux:select.option>
                                    <flux:select.option value="audio">{{ __('Audio') }}</flux:select.option>
                                    <flux:select.option value="embeddings">{{ __('Embeddings') }}</flux:select.option>
                                    <flux:select.option value="transcription">{{ __('Transcription') }}</flux:select.option>
                                    <flux:select.option value="speech">{{ __('Speech (TTS)') }}</flux:select.option>
                                    <flux:select.option value="rerank">{{ __('Rerank') }}</flux:select.option>
                                    <flux:select.option value="video">{{ __('Video') }}</flux:select.option>
                                </flux:select>
                            @endif

                            <flux:select wire:model="ai_model_suffix" label="{{ __('agentoom-core::admin.agents.openrouter_suffix') }}"
                                         description="{{ __('agentoom-core::admin.agents.openrouter_suffix_help') }}">
                                <flux:select.option value="">{{ __('agentoom-core::admin.agents.openrouter_suffix_none') }}</flux:select.option>
                                <flux:select.option value="nitro">{{ __('agentoom-core::admin.agents.openrouter_suffix_nitro') }}</flux:select.option>
                                <flux:select.option value="floor">{{ __('agentoom-core::admin.agents.openrouter_suffix_floor') }}</flux:select.option>
                            </flux:select>
                        </div>
                    @endif

                    <flux:select
                        variant="listbox"
                        wire:model.live="ai_model"
                        label="{{ __('agentoom-core::admin.configs.model') }}"
                        placeholder="{{ __('agentoom-core::admin.configs.model_placeholder') }}"
                        searchable
                        icon="cpu-chip"
                        :disabled="!$ai_provider"
                        :description="auth()->user()->hasRole('superadmin') && $this->selectedModelPricing ? __('Cost per 1M tokens') . ': $' . number_format($this->selectedModelPricing->input_price_per_million, 2) . ' (' . __('input') . ') / $' . number_format($this->selectedModelPricing->output_price_per_million, 2) . ' (' . __('output') . ')' : ''"
                    >
                        @foreach($models as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                </flux:card>

                {{-- Transcription --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_transcription') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.configs.transcription_help') }}</flux:subheading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if (count($providers) > 1)
                        <flux:select
                            wire:model.live="ai_transcription_provider"
                            searchable
                            label="{{ __('agentoom-core::admin.configs.transcription_provider') }}"
                            icon="server">
                            <flux:select.option value="">{{ __('agentoom-core::admin.configs.select_provider') }}</flux:select.option>
                            @foreach($providers as $id => $name)
                                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @endif

                        @php
                            $currentTranscriptionProviderName = isset($providers[$ai_transcription_provider]) ? $providers[$ai_transcription_provider] : '';
                        @endphp

                        @if ($currentTranscriptionProviderName === 'openrouter' && auth()->user()->hasRole('superadmin'))
                            <flux:select wire:model.live="ai_transcription_output_modality" label="{{ __('Output Modality') }}" icon="adjustments-horizontal">
                                <flux:select.option value="all">{{ __('All Modalities') }}</flux:select.option>
                                <flux:select.option value="text">{{ __('Text') }}</flux:select.option>
                                <flux:select.option value="image">{{ __('Image') }}</flux:select.option>
                                <flux:select.option value="audio">{{ __('Audio') }}</flux:select.option>
                                <flux:select.option value="embeddings">{{ __('Embeddings') }}</flux:select.option>
                                <flux:select.option value="transcription">{{ __('Transcription') }}</flux:select.option>
                                <flux:select.option value="speech">{{ __('Speech (TTS)') }}</flux:select.option>
                                <flux:select.option value="rerank">{{ __('Rerank') }}</flux:select.option>
                                <flux:select.option value="video">{{ __('Video') }}</flux:select.option>
                            </flux:select>
                        @endif

                        <flux:select
                            variant="listbox"
                            wire:model.live="ai_transcription_model"
                            label="{{ __('agentoom-core::admin.configs.transcription_model') }}"
                            placeholder="{{ __('agentoom-core::admin.configs.model_placeholder') }}"
                            searchable
                            icon="cpu-chip"
                            :disabled="!$ai_transcription_provider"
                        >
                            <flux:select.option value="">{{ __('agentoom-core::admin.configs.model_placeholder') }}</flux:select.option>
                            @foreach($transcription_models as $id => $name)
                                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:card>

                {{-- Parameters --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_parameters') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('agentoom-core::admin.configs.temperature') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.temperature_description') }}</flux:description>
                            <flux:input
                                wire:model="ai_temperature"
                                type="number"
                                min="0"
                                max="2"
                                step="0.1"
                                placeholder="{{ __('agentoom-core::admin.configs.temperature_placeholder') }}" />
                            <flux:error name="ai_temperature" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('agentoom-core::admin.configs.top_p') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.top_p_description') }}</flux:description>
                            <flux:input
                                wire:model="ai_top_p"
                                type="number"
                                min="0"
                                max="1"
                                step="0.05"
                                placeholder="{{ __('agentoom-core::admin.configs.top_p_placeholder') }}" />
                            <flux:error name="ai_top_p" />
                        </flux:field>
                    </div>
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.compliance_title') }}</flux:heading>
                    <flux:field>
                        <flux:checkbox wire:model="ai_disclosure_enabled" label="{{ __('agentoom-core::admin.configs.ai_disclosure_enabled') }}" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.configs.ai_disclosure_text') }}</flux:label>
                        <flux:textarea wire:model="ai_disclosure_text" />
                        <flux:error name="ai_disclosure_text" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.configs.ai_disclosure_version') }}</flux:label>
                        <flux:input wire:model="ai_disclosure_version" />
                        <flux:error name="ai_disclosure_version" />
                    </flux:field>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.common.save') }}
                    </flux:button>
                </div>
            </form>
        </flux:tab.panel>
    @endif

    @if (auth()->user()->hasRole('superadmin'))
        <flux:tab.panel name="compliance" class="space-y-4 pt-4">
            <form wire:submit="save" class="space-y-4">
                {{-- Master Switches --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.compliance_master_switches') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.configs.compliance_master_switches_subtitle') }}</flux:subheading>

                    <div class="space-y-3">
                        <flux:switch wire:model="eu_compliance_enabled" :label="__('agentoom-core::admin.configs.eu_compliance_enabled_label')" :description="__('agentoom-core::admin.configs.eu_compliance_enabled_description')" />
                        <flux:switch wire:model="privacy_redaction_enabled" :label="__('agentoom-core::admin.configs.privacy_redaction_enabled_label')" :description="__('agentoom-core::admin.configs.privacy_redaction_enabled_description')" />
                    </div>
                </flux:card>

                {{-- Feature Toggles --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.compliance_feature_toggles') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.configs.compliance_feature_toggles_subtitle') }}</flux:subheading>

                    <div class="space-y-3">
                        <flux:switch wire:model="compliance_audit_logging" :label="__('agentoom-core::admin.configs.compliance_audit_logging_label')" :description="__('agentoom-core::admin.configs.compliance_audit_logging_description')" />
                        <flux:switch wire:model="compliance_emergency_stops" :label="__('agentoom-core::admin.configs.compliance_emergency_stops_label')" :description="__('agentoom-core::admin.configs.compliance_emergency_stops_description')" />
                        <flux:switch wire:model="compliance_auto_incidents" :label="__('agentoom-core::admin.configs.compliance_auto_incidents_label')" :description="__('agentoom-core::admin.configs.compliance_auto_incidents_description')" />
                        <flux:switch wire:model="compliance_auto_alerts" :label="__('agentoom-core::admin.configs.compliance_auto_alerts_label')" :description="__('agentoom-core::admin.configs.compliance_auto_alerts_description')" />
                        <flux:switch wire:model="compliance_governance_gate" :label="__('agentoom-core::admin.configs.compliance_governance_gate_label')" :description="__('agentoom-core::admin.configs.compliance_governance_gate_description')" />
                        <flux:switch wire:model="compliance_ai_disclosure" :label="__('agentoom-core::admin.configs.compliance_ai_disclosure_label')" :description="__('agentoom-core::admin.configs.compliance_ai_disclosure_description')" />
                        <flux:switch wire:model="compliance_risk_enforcement" :label="__('agentoom-core::admin.configs.compliance_risk_enforcement_label')" :description="__('agentoom-core::admin.configs.compliance_risk_enforcement_description')" />
                    </div>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.common.save') }}
                    </flux:button>
                </div>
            </form>
        </flux:tab.panel>

        <flux:tab.panel name="visibility" class="space-y-4 pt-4">
            @php
                $envValue = env('AGENTOOM_ACTIVE_VISIBILITY_GROUPS');
            @endphp

            @if($isOverridingVisibilityEnv)
                <flux:callout color="amber" icon="exclamation-triangle" class="mb-4">
                    <flux:callout.heading>{{ __('agentoom-core::admin.visibility_groups.override_active') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('agentoom-core::admin.visibility_groups.override_description') }}
                    </flux:callout.text>
                    <div class="mt-3">
                        <flux:button wire:click="resetVisibilityToEnv" variant="outline" size="sm">
                            {{ __('agentoom-core::admin.visibility_groups.reset_to_env') }}
                        </flux:button>
                    </div>
                </flux:callout>
            @else
                <flux:callout color="blue" icon="information-circle" class="mb-4">
                    <flux:callout.heading>{{ __('agentoom-core::admin.visibility_groups.using_env') }}</flux:callout.heading>
                    <flux:callout.text>
                        @if($envValue)
                            {{ __('agentoom-core::admin.visibility_groups.active_from_env') }} <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded text-sm">{{ $envValue }}</code>
                        @else
                            {{ __('agentoom-core::admin.visibility_groups.all_active') }}
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex gap-2 mb-4">
                <flux:button wire:click="activateAllVisibilityGroups" variant="outline" size="sm">{{ __('agentoom-core::admin.visibility_groups.activate_all') }}</flux:button>
                <flux:button wire:click="deactivateAllVisibilityGroups" variant="outline" size="sm">{{ __('agentoom-core::admin.visibility_groups.deactivate_all') }}</flux:button>
            </div>

            @if(empty($allVisibilityGroups))
                <flux:card class="text-center py-12">
                    <flux:heading size="lg" class="text-zinc-500">{{ __('agentoom-core::admin.visibility_groups.no_groups') }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('agentoom-core::admin.visibility_groups.no_groups_description') }}
                    </flux:subheading>
                </flux:card>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($allVisibilityGroups as $group)
                        <flux:card class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="base">{{ $group['name'] }}</flux:heading>
                                    <flux:subheading class="text-xs">
                                        {{ trans_choice('{1} :count item|:count items', $group['total'], ['count' => $group['total']]) }}
                                        &middot;
                                        {{ implode(', ', array_map(fn($s) => ucfirst($s), $group['sources'])) }}
                                    </flux:subheading>
                                </div>
                                <flux:switch
                                    wire:model.live="activeVisibilityGroups.{{ $group['name'] }}"
                                />
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            @endif

            @if(!empty($allVisibilityGroups))
                <div class="pt-4">
                    <flux:button wire:click="saveVisibility" variant="primary">
                        {{ __('agentoom-core::admin.visibility_groups.save') }}
                    </flux:button>
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="chat" class="space-y-4 pt-4">
            <form wire:submit="save" class="space-y-4">
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.chat_embed_script_title') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.configs.chat_embed_script_description') }}</flux:subheading>

                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.configs.chat_embed_script_label') }}</flux:label>
                        <div class="relative">
                            <flux:textarea
                                readonly
                                rows="4"
                                class="font-mono text-sm"
                            >&lt;iframe src="{{ url('/admin/chat/embed') }}" style="width:100%;height:600px;border:none;" allow="microphone"&gt;&lt;/iframe&gt;</flux:textarea>
                        </div>
                        <flux:description>{{ __('agentoom-core::admin.configs.chat_embed_script_help') }}</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.configs.chat_embed_allowed_domains_label') }}</flux:label>
                        <flux:textarea
                            wire:model="chat_embed_allowed_domains"
                            rows="4"
                            placeholder="https://example.com&#10;https://app.mysite.com&#10;192.168.1.100" />
                        <flux:description>{{ __('agentoom-core::admin.configs.chat_embed_allowed_domains_description') }}</flux:description>
                        <flux:error name="chat_embed_allowed_domains" />
                    </flux:field>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.common.save') }}
                    </flux:button>
                </div>
            </form>
        </flux:tab.panel>
    @endif

    <flux:tab.panel name="billing" class="space-y-4 pt-4">
            <form wire:submit="save" class="space-y-4">
                {{-- Credits info & alert --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_credits_info_alert') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('agentoom-core::admin.configs.app_ai_credits') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.app_ai_credits_description') }} <a href="{{ route('admin.ai-credits.index') }}" class="text-purple-600 dark:text-purple-400 font-medium hover:underline">{{ __('agentoom-core::admin.configs.manage_credits') }}</a></flux:description>
                            <flux:input
                                readonly
                                :value="'$' . number_format($app_ai_credits, 2)"
                                disabled />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('agentoom-core::admin.configs.low_credit_threshold') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.low_credit_threshold_description') }}</flux:description>
                            <flux:input
                                wire:model="low_credit_threshold"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="20" />
                            <flux:error name="low_credit_threshold" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('agentoom-core::admin.configs.notification_emails') }}</flux:label>
                            <flux:description>{{ __('agentoom-core::admin.configs.notification_emails_description') }}</flux:description>
                            <flux:input wire:model="credit_limit_notification_emails" placeholder="email1@example.com, email2@example.com" />
                            <flux:error name="credit_limit_notification_emails" />
                        </flux:field>
                    </div>
                </flux:card>

                @if (auth()->user()->hasRole('superadmin'))
                    {{-- Credit management --}}
                    <flux:card class="space-y-4">
                        <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_credit_cost') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('agentoom-core::admin.configs.openrouter_multiplier') }}</flux:label>
                                <flux:description>{{ __('agentoom-core::admin.configs.openrouter_multiplier_description') }}</flux:description>
                                <flux:input wire:model="openrouter_multiplier" type="number" step="0.01" min="1" />
                                <flux:error name="openrouter_multiplier" />
                            </flux:field>
                        </div>
                    </flux:card>
                @endif

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.common.save') }}
                    </flux:button>
                </div>
            </form>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.pricing_status') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.configs.pricing_status_description') }}</flux:description>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @forelse($pricingSyncs as $sync)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <div class="flex justify-between"><strong>{{ $sync->source }}</strong><span>{{ $sync->status }}</span></div>
                                <div class="text-xs text-zinc-500">{{ $sync->entry_count }} entries · {{ $sync->last_success_at ? \Illuminate\Support\Carbon::parse($sync->last_success_at)->diffForHumans() : __('agentoom-core::admin.configs.pricing_never_successful') }}</div>
                                @if($sync->error)<div class="text-xs text-red-600 mt-1">{{ $sync->error }}</div>@endif
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500">{{ __('agentoom-core::admin.configs.pricing_no_sync') }}</div>
                        @endforelse
                    </div>
                </flux:card>

            @if (auth()->user()->hasRole('superadmin'))
                {{-- Model Pricing --}}
                <flux:card class="space-y-4">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_model_pricing') }}</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
                            <flux:input wire:model="new_pricing.provider" label="{{ __('agentoom-core::admin.configs.pricing_provider_driver') }}" placeholder="openai, anthropic..." />
                            <flux:input wire:model="new_pricing.model" label="{{ __('agentoom-core::admin.configs.pricing_exact_model') }}" placeholder="gpt-4o, claude-3-sonnet..." />
                            <flux:input wire:model="new_pricing.input_price_per_million" type="number" step="0.000001" min="0" label="{{ __('agentoom-core::admin.configs.pricing_input') }}" />
                            <flux:input wire:model="new_pricing.output_price_per_million" type="number" step="0.000001" min="0" label="{{ __('agentoom-core::admin.configs.pricing_output') }}" />
                            <flux:button wire:click="addPricing" variant="filled" icon="plus">{{ __('agentoom-core::admin.configs.pricing_add_manual') }}</flux:button>
                        </div>

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('agentoom-core::admin.configs.pricing_provider_driver') }}</flux:table.column>
                                <flux:table.column>{{ __('agentoom-core::admin.configs.pricing_exact_model') }}</flux:table.column>
                                <flux:table.column>{{ __('agentoom-core::admin.configs.pricing_input') }}</flux:table.column>
                                <flux:table.column>{{ __('agentoom-core::admin.configs.pricing_output') }}</flux:table.column>
                                <flux:table.column>{{ __('agentoom-core::admin.configs.pricing_source_freshness') }}</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($allModelPricings as $pricing)
                                    <flux:table.row>
                                        <flux:table.cell class="font-medium">{{ $pricing->provider }}</flux:table.cell>
                                        <flux:table.cell>{{ $pricing->model ?? __('agentoom-core::admin.configs.pricing_legacy_ignored') }}</flux:table.cell>
                                        <flux:table.cell>{{ $pricing->input_price_per_million === null ? '—' : '$'.number_format($pricing->input_price_per_million, 6) }}</flux:table.cell>
                                        <flux:table.cell>{{ $pricing->output_price_per_million === null ? '—' : '$'.number_format($pricing->output_price_per_million, 6) }}</flux:table.cell>
                                        <flux:table.cell>{{ $pricing->source }} / {{ $pricing->last_seen_at?->diffForHumans() ?? __('agentoom-core::admin.configs.pricing_never_successful') }}</flux:table.cell>
                                        <flux:table.cell class="text-right">
                                            @if($pricing->source === 'manual')
                                                <flux:button wire:click="deletePricing({{ $pricing->id }})" variant="ghost" icon="trash" size="sm" />
                                            @else
                                                <span class="text-xs text-zinc-500">{{ __('agentoom-core::admin.configs.pricing_read_only') }}</span>
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </flux:card>
            @endif
        </flux:tab.panel>
    </flux:tab.group>
</div>
