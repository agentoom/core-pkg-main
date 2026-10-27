<div class="max-w-3xl mx-auto space-y-6">
    {{-- Step Indicator --}}
    <div class="mb-8 overflow-x-auto pb-2">
        <div class="flex items-center justify-center gap-4 min-w-max px-4">
            @foreach ($stepsList as $index => $stepData)
                @php
                    $stepNumber = $index + 1;
                    $isCurrent = $stepNumber === $step;
                    $isPast = $stepNumber < $step;
                    $isFuture = $stepNumber > $step;
                    $isOptional = $stepData['optional'] ?? false;
                    $isCompleted = $this->isStepCompleted($stepNumber);
                @endphp
                <div class="flex items-center">
                    <button
                        wire:click="goToStep({{ $stepNumber }})"
                        @class([
                            'flex items-center justify-center rounded-full w-8 h-8 text-sm font-medium transition-colors shrink-0',
                            'cursor-pointer' => ($isCompleted || $stepNumber < $step) && !$isCurrent,
                            'cursor-default' => !($isCompleted || $stepNumber < $step) || $isCurrent,
                            'bg-blue-600 text-white shadow-sm' => $isCurrent,
                            'bg-green-500 text-white' => $isPast || ($isCompleted && !$isCurrent),
                            'bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400' => $isFuture && !$isCompleted,
                        ])
                        @if (!($isCompleted || $stepNumber < $step) || $isCurrent) disabled @endif
                    >
                        @if ($isPast || ($isCompleted && !$isCurrent))
                            <flux:icon.check class="w-4 h-4" />
                        @else
                            {{ $stepNumber }}
                        @endif
                    </button>
                    <div class="ml-2 hidden lg:block">
                        <div @class([
                            'text-xs font-semibold whitespace-nowrap',
                            'text-blue-600 dark:text-blue-400' => $isCurrent,
                            'text-green-600 dark:text-green-400' => $isCompleted && !$isCurrent,
                            'text-zinc-400 dark:text-zinc-500' => $isFuture && !$isCompleted,
                        ])>
                            {{ $stepData['label'] }}
                        </div>
                    </div>
                </div>
                @if ($stepNumber < count($stepsList))
                    <div class="w-8 sm:w-12 h-px {{ $isPast || ($isCompleted && !$isCurrent) ? 'bg-green-400' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Current Step Card --}}
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $this->currentStepData()['label'] }}</flux:heading>
            <flux:subheading>{{ $this->currentStepData()['description'] }}</flux:subheading>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($syncError)
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ $syncError }}
            </flux:callout>
        @endif

        {{-- Step 1: Provider Setup --}}
        @if ($step === 1)
            <form wire:submit="saveProvider" class="space-y-4" autocomplete="off" data-bwignore="true">
                <flux:select
                    variant="listbox"
                    wire:model.live="providerName"
                    label="{{ __('agentoom-core::admin.onboarding.provider_label') }}"
                    placeholder="{{ __('agentoom-core::admin.onboarding.provider_placeholder') }}"
                    searchable
                    icon="server"
                >
                    @foreach ($providerOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="providerApiKey"
                    label="{{ __('agentoom-core::admin.onboarding.api_key_label') }}"
                    placeholder="{{ __('agentoom-core::admin.onboarding.api_key_placeholder') }}"
                    type="password"
                    icon="key"
                    viewable
                    autocomplete="off"
                    data-bwignore="true"
                    data-1p-ignore
                    data-lpignore="true"
                />
                @if ($providerName === 'custom')
                    <flux:input
                        wire:model="providerUrl"
                        label="Base URL"
                        placeholder="http://localhost:11434/v1"
                        icon="globe-alt" />
                    <flux:text>Use this for Ollama, LM Studio, vLLM, LocalAI, LiteLLM, or another OpenAI-compatible endpoint.</flux:text>
                @endif

                <div class="flex justify-end pt-4">
                    <flux:button type="submit" variant="primary" icon="arrow-right">
                        {{ __('agentoom-core::admin.onboarding.continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Step 2: Model Sync --}}
        @elseif ($step === 2)
            <div class="space-y-4">
                @if (! $modelsSynced)
                    <div>
                        <flux:description>{{ __('agentoom-core::admin.onboarding.sync_models_description') }}</flux:description>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="syncModels"
                            variant="primary"
                            icon="cloud-arrow-down"
                        >
                            {{ __('agentoom-core::admin.onboarding.sync_models_now') }}
                        </flux:button>
                        <flux:button
                            wire:click="previousStep"
                            variant="outline"
                            icon="arrow-left"
                        >
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                    </div>
                @else
                    <flux:callout variant="success" icon="check-circle">
                        {{ __('agentoom-core::admin.onboarding.models_synced_count', ['count' => $syncedCount]) }}
                    </flux:callout>

                    <div class="flex justify-between pt-4">
                        <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                        <div class="flex gap-3">
                            <flux:button
                                wire:click="syncModels"
                                variant="outline"
                                icon="arrow-path"
                            >
                                {{ __('agentoom-core::admin.onboarding.re_sync') }}
                            </flux:button>
                            <flux:button wire:click="nextStep" variant="primary" icon="arrow-right">
                                {{ __('agentoom-core::admin.onboarding.continue') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>

        {{-- Step 3: Agents --}}
        @elseif ($step === 3)
            <form wire:submit="assignSystemAgentModels" class="space-y-6">

                {{-- System Agents (Mandatory) --}}
                <div class="space-y-4">
                    <flux:heading size="md" class="flex items-center gap-2">
                        {{ __('agentoom-core::admin.onboarding.system_agents') }}
                        <flux:badge size="sm" variant="danger" inset="top bottom">{{ __('agentoom-core::admin.common.mandatory') }}</flux:badge>
                    </flux:heading>

                    @foreach ($systemAgents as $agent)
                        <flux:card class="space-y-3">
                            <flux:heading size="sm">{{ $agent->name }}</flux:heading>
                            <flux:description>{{ $agent->description }}</flux:description>

                            <flux:select
                                variant="listbox"
                                wire:model="agentModels.{{ $agent->id }}"
                                label="{{ __('agentoom-core::admin.onboarding.select_model') }}"
                                placeholder="{{ __('agentoom-core::admin.onboarding.select_model_placeholder') }}"
                                searchable
                                icon="cpu-chip"
                            >
                                <flux:select.option value="">{{ __('agentoom-core::admin.common.none') }}</flux:select.option>
                                @foreach ($syncedModels as $model)
                                    <flux:select.option value="{{ $model->id }}">{{ $model->model_name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @error("agentModels.{$agent->id}")
                                <p class="text-sm text-red-500 font-medium">{{ $message }}</p>
                            @enderror
                        </flux:card>
                    @endforeach
                </div>

                    <div class="space-y-4">
                        <flux:heading size="md" class="flex items-center gap-2">
                            {{ __('agentoom-core::admin.onboarding.other_agents') }}
                            <flux:badge size="sm" variant="neutral" inset="top bottom">{{ __('agentoom-core::admin.common.optional') }}</flux:badge>
                        </flux:heading>

                        @foreach ($otherAgents as $agent)
                            <flux:card class="space-y-3">
                                <flux:heading size="sm">{{ $agent->name }}</flux:heading>
                                <flux:description>{{ $agent->description }}</flux:description>

                                <flux:select
                                    variant="listbox"
                                    wire:model="agentModels.{{ $agent->id }}"
                                    label="{{ __('agentoom-core::admin.onboarding.select_model') }}"
                                    placeholder="{{ __('agentoom-core::admin.onboarding.select_model_placeholder') }}"
                                    searchable
                                    icon="cpu-chip"
                                >
                                    <flux:select.option value="">{{ __('agentoom-core::admin.common.none') }}</flux:select.option>
                                    @foreach ($syncedModels as $model)
                                        <flux:select.option value="{{ $model->id }}">{{ $model->model_name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:card>
                        @endforeach
                <flux:card class="space-y-3">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        {{ __('agentoom-core::admin.configs.transcription_model') }}
                        <flux:badge size="sm" variant="neutral" inset="top bottom">{{ __('agentoom-core::admin.common.optional') }}</flux:badge>
                    </flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.configs.transcription_help') }}</flux:description>
                    <flux:select
                        variant="listbox"
                        wire:model="transcriptionModel"
                        label="{{ __('agentoom-core::admin.configs.transcription_model') }}"
                        placeholder="{{ __('agentoom-core::admin.configs.model_placeholder') }}"
                        searchable
                        icon="microphone"
                    >
                        <flux:select.option value="">{{ __('agentoom-core::admin.common.none') }}</flux:select.option>
                        @foreach ($transcriptionModels as $model)
                            <flux:select.option value="{{ $model->id }}">{{ $model->model_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:card>
                    </div>

                <div class="flex justify-between pt-4">
                    <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                        {{ __('agentoom-core::admin.onboarding.back') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="arrow-right">
                        {{ __('agentoom-core::admin.onboarding.continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Step 4: Credits & Notifications --}}
        @elseif ($step === 4)
            <form wire:submit="saveCreditSettings" class="space-y-4">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.credit_threshold') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.credit_threshold_desc') }}</flux:description>

                    <flux:input
                        wire:model="lowCreditThreshold"
                        label="{{ __('agentoom-core::admin.onboarding.threshold_label') }}"
                        type="number"
                        step="0.01"
                        min="0"
                        icon="currency-dollar"
                    />
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.notification_emails') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.notification_emails_desc') }}</flux:description>

                    <flux:textarea
                        wire:model="notificationEmails"
                        label="{{ __('agentoom-core::admin.onboarding.emails_label') }}"
                        placeholder="{{ __('agentoom-core::admin.onboarding.emails_placeholder') }}"
                        rows="3"
                    />
                </flux:card>

                <div class="flex justify-between pt-4">
                    <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                        {{ __('agentoom-core::admin.onboarding.back') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="arrow-right">
                        {{ __('agentoom-core::admin.onboarding.continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Optional: Email/SMTP --}}
        @elseif ($step === 5)
            <form wire:submit="saveEmailSettings" class="space-y-4" autocomplete="off" data-bwignore="true">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.smtp_settings') }}</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input
                            wire:model="mailHost"
                            label="{{ __('agentoom-core::admin.onboarding.smtp_host') }}"
                            placeholder="smtp.example.com"
                            icon="server"
                            autocomplete="off"
                            data-bwignore="true"
                            data-1p-ignore
                            data-lpignore="true"
                        />
                        <flux:input
                            wire:model="mailPort"
                            label="{{ __('agentoom-core::admin.onboarding.smtp_port') }}"
                            placeholder="587"
                            icon="bolt"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input
                            wire:model="mailUsername"
                            label="{{ __('agentoom-core::admin.onboarding.smtp_username') }}"
                            icon="user"
                            autocomplete="off"
                            data-bwignore="true"
                            data-1p-ignore
                            data-lpignore="true"
                        />
                        <flux:input
                            wire:model="mailPassword"
                            label="{{ __('agentoom-core::admin.onboarding.smtp_password') }}"
                            type="password"
                            icon="key"
                            viewable
                            autocomplete="off"
                            data-bwignore="true"
                            data-1p-ignore
                            data-lpignore="true"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select
                            variant="listbox"
                            wire:model="mailEncryption"
                            label="{{ __('agentoom-core::admin.onboarding.smtp_encryption') }}"
                            icon="shield-check"
                        >
                            <flux:select.option value="tls">TLS</flux:select.option>
                            <flux:select.option value="ssl">SSL</flux:select.option>
                            <flux:select.option value="">None</flux:select.option>
                        </flux:select>
                        <flux:input
                            wire:model="mailFromAddress"
                            label="{{ __('agentoom-core::admin.onboarding.from_address') }}"
                            placeholder="noreply@example.com"
                            icon="envelope"
                        />
                    </div>

                    <flux:input
                        wire:model="mailFromName"
                        label="{{ __('agentoom-core::admin.onboarding.from_name') }}"
                        placeholder="{{ config('app.name') }}"
                        icon="tag"
                    />
                </flux:card>

                <div class="flex justify-between pt-4">
                    <div class="flex gap-3">
                        <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                        <flux:button wire:click="skipOptionalStep" variant="ghost" icon="arrow-right">
                            {{ __('agentoom-core::admin.onboarding.skip') }}
                        </flux:button>
                    </div>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.onboarding.save_and_continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Optional: EU AI Act Compliance --}}
        @elseif ($step === 6)
            <form wire:submit="saveComplianceSettings" class="space-y-4">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.eu_compliance') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.eu_compliance_desc') }}</flux:description>

                    <flux:switch
                        wire:model="euComplianceEnabled"
                        label="{{ __('agentoom-core::admin.onboarding.enable_eu_compliance') }}"
                    />
                </flux:card>

                <div class="flex justify-between pt-4">
                    <div class="flex gap-3">
                        <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                        <flux:button wire:click="skipOptionalStep" variant="ghost" icon="arrow-right">
                            {{ __('agentoom-core::admin.onboarding.skip') }}
                        </flux:button>
                    </div>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.onboarding.save_and_continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Optional: AI Disclosure --}}
        @elseif ($step === 7)
            <form wire:submit="saveDisclosureSettings" class="space-y-4">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.ai_disclosure') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.ai_disclosure_desc') }}</flux:description>

                    <flux:switch
                        wire:model="aiDisclosureEnabled"
                        label="{{ __('agentoom-core::admin.onboarding.enable_disclosure') }}"
                    />

                    @foreach ($activeLanguages as $language)
                        <flux:textarea
                            wire:model="aiDisclosureText.{{ $language->code }}"
                            label="{{ __('agentoom-core::admin.onboarding.disclosure_text') }} ({{ strtoupper($language->code) }})"
                            rows="4"
                        />
                    @endforeach

                    <flux:input
                        wire:model="aiDisclosureVersion"
                        label="{{ __('agentoom-core::admin.onboarding.disclosure_version') }}"
                        icon="tag"
                    />
                </flux:card>

                <div class="flex justify-between pt-4">
                    <div class="flex gap-3">
                        <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                        <flux:button wire:click="skipOptionalStep" variant="ghost" icon="arrow-right">
                            {{ __('agentoom-core::admin.onboarding.skip') }}
                        </flux:button>
                    </div>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.onboarding.save_and_continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Optional: Appearance --}}
        @elseif ($step === 8)
            <form wire:submit="saveAppearanceSettings" class="space-y-4">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.appearance') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.appearance_desc') }}</flux:description>

                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.configs.app_logo') }}</flux:label>
                        <flux:description>{{ __('agentoom-core::admin.configs.app_logo_description') }}</flux:description>
                        <div class="flex items-center gap-4 mt-2">
                            @if($appLogoUrl)
                                <img src="{{ $appLogoUrl }}" alt="App Logo" class="h-12 w-auto object-contain bg-gray-100 dark:bg-white/10 rounded p-1">
                            @endif
                            <flux:input type="file" wire:model="appLogo" accept="image/*" />
                        </div>
                        <flux:error name="appLogo" />
                        <flux:description class="mt-1">
                            {{ __('agentoom-core::admin.onboarding.appearance_later_hint') }}
                        </flux:description>
                    </flux:field>
                </flux:card>

                <div class="flex justify-between pt-4">
                    <div class="flex gap-3">
                        <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                            {{ __('agentoom-core::admin.onboarding.back') }}
                        </flux:button>
                        <flux:button wire:click="skipOptionalStep" variant="ghost" icon="arrow-right">
                            {{ __('agentoom-core::admin.onboarding.skip') }}
                        </flux:button>
                    </div>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('agentoom-core::admin.onboarding.save_and_continue') }}
                    </flux:button>
                </div>
            </form>

        {{-- Step 9: Review & Complete --}}
        @elseif ($step === 9)
            <div class="space-y-4">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('agentoom-core::admin.onboarding.review_summary') }}</flux:heading>
                    <flux:description>{{ __('agentoom-core::admin.onboarding.review_description') }}</flux:description>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($summary as $item)
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $item['label'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">{{ $item['value'] }}</span>
                                    @if ($item['status'] === 'configured')
                                        <flux:badge variant="solid" color="green" size="sm">
                                            {{ __('agentoom-core::admin.onboarding.configured') }}
                                        </flux:badge>
                                    @elseif ($item['status'] === 'missing')
                                        <flux:badge variant="solid" color="red" size="sm">
                                            {{ __('agentoom-core::admin.onboarding.missing') }}
                                        </flux:badge>
                                    @elseif ($item['status'] === 'warning')
                                        <flux:badge variant="solid" color="amber" size="sm">
                                            {{ __('agentoom-core::admin.onboarding.warning') }}
                                        </flux:badge>
                                    @elseif ($item['status'] === 'skipped')
                                        <flux:badge variant="solid" color="zinc" size="sm">
                                            {{ __('agentoom-core::admin.onboarding.skipped') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge variant="solid" color="zinc" size="sm">
                                            {{ __('agentoom-core::admin.onboarding.not_set') }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                @if (! $this->canComplete())
                    <flux:callout variant="warning" icon="exclamation-circle">
                        {{ __('agentoom-core::admin.onboarding.complete_warning') }}
                    </flux:callout>
                @endif

                <div class="flex justify-between pt-4">
                    <flux:button wire:click="previousStep" variant="outline" icon="arrow-left">
                        {{ __('agentoom-core::admin.onboarding.back') }}
                    </flux:button>
                    <flux:button wire:click="complete" variant="primary" icon="check" :disabled="!$this->canComplete()">
                        {{ __('agentoom-core::admin.onboarding.complete_setup') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:card>
</div>
