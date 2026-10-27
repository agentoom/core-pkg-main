<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\ProviderModelSyncService;
use Illuminate\Validation\Rule;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;
use Livewire\WithFileUploads;

class OnboardingWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    // Step 1: Provider
    public string $providerName = '';

    public string $providerApiKey = '';

    public string $providerUrl = '';

    public ?int $providerId = null;

    // Step 2: Model sync
    public bool $modelsSynced = false;

    public int $syncedCount = 0;

    public ?string $syncError = null;

    // Step 3: Agents
    public array $agentModels = [];

    public string $transcriptionModel = '';

    // Step 4: Credits & notifications
    public bool $creditsConfigured = false;

    public float $lowCreditThreshold = 1.0;

    public string $notificationEmails = '';

    // Optional step: Email/SMTP
    public bool $stepEmailSkipped = false;

    public string $mailHost = '';

    public string $mailPort = '587';

    public string $mailUsername = '';

    public string $mailPassword = '';

    public string $mailEncryption = 'tls';

    public string $mailFromAddress = '';

    public string $mailFromName = '';

    // Optional step: EU AI Act Compliance
    public bool $stepComplianceSkipped = false;

    public bool $euComplianceEnabled = false;

    // Optional step: AI Disclosure
    public bool $stepDisclosureSkipped = false;

    public bool $aiDisclosureEnabled = false;

    /** @var array<string, string> Language code => disclosure text */
    public array $aiDisclosureText = [];

    public string $aiDisclosureVersion = '1.0.0';

    // Optional step: Appearance
    public bool $stepAppearanceSkipped = false;

    public $appLogo = null;

    public ?string $appLogoUrl = null;

    public function mount(): void
    {
        // Pre-populate provider data from existing configuration
        $provider = Provider::first();
        if ($provider) {
            $this->providerId = $provider->id;
            $this->providerName = $provider->name;
            $this->providerApiKey = $provider->api_key ?? '';
            $this->providerUrl = $provider->url ?? '';
            $this->modelsSynced = $provider->models()->count() > 0;
            $this->syncedCount = $provider->models()->count();
        }

        // Pre-populate agents configuration
        $agents = Agent::whereNull('project_id')->get();
        foreach ($agents as $agent) {
            $this->agentModels[$agent->id] = $agent->config['provider_model_id'] ?? '';
        }

        $this->transcriptionModel = (string) Configuration::getValue('ai_transcription_model', '');

        $savedCreditThreshold = Configuration::getValue('low_credit_threshold', null);
        $savedNotificationEmails = Configuration::getValue('credit_limit_notification_emails', null);
        $this->creditsConfigured = $savedCreditThreshold !== null || $savedNotificationEmails !== null;
        $this->lowCreditThreshold = (float) ($savedCreditThreshold ?? 1.0);
        $this->notificationEmails = (string) ($savedNotificationEmails ?? '');

        // Pre-populate email settings
        $mailKeys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name'];
        foreach ($mailKeys as $key) {
            $prop = lcfirst(str_replace('_', '', ucwords($key, '_')));
            if (property_exists($this, $prop)) {
                $this->{$prop} = (string) Configuration::getValue($key, $this->{$prop} ?? '');
            }
        }

        // Pre-populate compliance
        $this->euComplianceEnabled = app(ComplianceIntegration::class)->isEnabled();

        // Pre-populate AI disclosure
        $this->aiDisclosureEnabled = (bool) Configuration::getValue('ai_disclosure_enabled', false);
        $this->aiDisclosureVersion = Configuration::getValue('ai_disclosure_version', '1.0.0');
        $this->populateAiDisclosureTexts();

        // Pre-populate appearance
        $this->appLogoUrl = Configuration::getValue('app_logo');

        // Jump to the first incomplete step on reload
        $this->step = $this->determineStartingStep();
    }

    /**
     * @return array{key: string, label: string, description: string, optional: bool, icon: string}[]
     */
    public function steps(): array
    {
        return [
            ['key' => 'provider', 'label' => __('agentoom-core::admin.onboarding.step_provider'), 'description' => __('agentoom-core::admin.onboarding.step_provider_desc'), 'optional' => false, 'icon' => 'server'],
            ['key' => 'models', 'label' => __('agentoom-core::admin.onboarding.step_models'), 'description' => __('agentoom-core::admin.onboarding.step_models_desc'), 'optional' => false, 'icon' => 'cloud-arrow-down'],
            ['key' => 'agents', 'label' => __('agentoom-core::admin.onboarding.step_agents'), 'description' => __('agentoom-core::admin.onboarding.step_agents_desc'), 'optional' => false, 'icon' => 'cpu-chip'],
            ['key' => 'credits', 'label' => __('agentoom-core::admin.onboarding.step_credits'), 'description' => __('agentoom-core::admin.onboarding.step_credits_desc'), 'optional' => false, 'icon' => 'credit-card'],
            ['key' => 'email', 'label' => __('agentoom-core::admin.onboarding.step_email'), 'description' => __('agentoom-core::admin.onboarding.step_email_desc'), 'optional' => true, 'icon' => 'envelope'],
            ['key' => 'compliance', 'label' => __('agentoom-core::admin.onboarding.step_compliance'), 'description' => __('agentoom-core::admin.onboarding.step_compliance_desc'), 'optional' => true, 'icon' => 'shield-check'],
            ['key' => 'disclosure', 'label' => __('agentoom-core::admin.onboarding.step_disclosure'), 'description' => __('agentoom-core::admin.onboarding.step_disclosure_desc'), 'optional' => true, 'icon' => 'document-text'],
            ['key' => 'appearance', 'label' => __('agentoom-core::admin.onboarding.step_appearance'), 'description' => __('agentoom-core::admin.onboarding.step_appearance_desc'), 'optional' => true, 'icon' => 'paint-brush'],
            ['key' => 'review', 'label' => __('agentoom-core::admin.onboarding.step_review'), 'description' => __('agentoom-core::admin.onboarding.step_review_desc'), 'optional' => false, 'icon' => 'clipboard-document-check'],
        ];
    }

    public function totalSteps(): int
    {
        return count($this->steps());
    }

    public function currentStepData(): array
    {
        $steps = $this->steps();

        return $steps[$this->step - 1] ?? $steps[0];
    }

    public function isCurrentStepOptional(): bool
    {
        return $this->currentStepData()['optional'] ?? false;
    }

    public function nextStep(): void
    {
        if ($this->step < $this->totalSteps()) {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $targetStep): void
    {
        if ($targetStep >= 1 && $targetStep <= $this->totalSteps() && ($this->isStepCompleted($targetStep) || $targetStep < $this->step)) {
            $this->step = $targetStep;
        }
    }

    /**
     * Determine the first incomplete step so the user resumes where they left off.
     */
    public function determineStartingStep(): int
    {
        foreach ($this->steps() as $index => $stepData) {
            $stepNumber = $index + 1;
            if (! $this->isStepCompleted($stepNumber) && ! $stepData['optional']) {
                return $stepNumber;
            }
        }

        // All mandatory steps are done; return step 1 so user can navigate freely
        return 1;
    }

    /**
     * Check if a step has been completed (data has been saved for it).
     */
    public function isStepCompleted(int $stepNumber): bool
    {
        return match ($stepNumber) {
            1 => $this->providerId !== null && $this->providerName !== '',
            2 => $this->modelsSynced,
            3 => $this->areSystemAgentsAssigned(),
            4 => $this->creditsConfigured,
            5 => $this->stepEmailSkipped || $this->mailHost !== '',
            6 => $this->stepComplianceSkipped || $this->euComplianceEnabled,
            7 => $this->stepDisclosureSkipped || $this->aiDisclosureEnabled,
            8 => $this->stepAppearanceSkipped || $this->appLogoUrl !== null,
            9 => false,
            default => false,
        };
    }

    /**
     * Step 1: Save AI provider configuration.
     */
    public function saveProvider(): void
    {
        $this->validate([
            'providerName' => 'required|string|in:'.implode(',', array_keys($this->getProviderOptions())),
            'providerApiKey' => 'nullable|string|max:500',
            'providerUrl' => $this->providerName === 'custom' ? 'required|url|max:2048' : 'nullable|url|max:2048',
        ]);

        $provider = Provider::updateOrCreate(
            ['name' => $this->providerName],
            [
                'driver' => $this->providerName === 'custom' ? 'openai' : $this->providerName,
                'api_key' => $this->providerApiKey ?: null,
                'url' => $this->providerUrl ?: null,
            ]
        );

        $this->providerId = $provider->id;

        // Store as default AI provider
        Configuration::setValue('ai_provider', $this->providerName);

        session()->flash('success', __('agentoom-core::admin.onboarding.provider_saved'));
        $this->nextStep();
    }

    /**
     * Step 2: Sync models from the provider.
     */
    public function syncModels(ProviderModelSyncService $syncService): void
    {
        if (! $this->providerId) {
            $this->syncError = __('agentoom-core::admin.onboarding.no_provider_error');

            return;
        }

        $provider = Provider::find($this->providerId);
        if (! $provider) {
            $this->syncError = __('agentoom-core::admin.onboarding.provider_not_found');

            return;
        }

        $this->syncError = null;

        try {
            $result = $syncService->syncProvider($provider);

            if ($result['success']) {
                $this->modelsSynced = true;
                $this->syncedCount = $result['synced_count'];
                session()->flash('success', __('agentoom-core::admin.onboarding.models_synced', ['count' => $this->syncedCount]));
            } else {
                $this->syncError = $result['error'] ?? __('agentoom-core::admin.onboarding.sync_error');
            }
        } catch (\Throwable $e) {
            $this->syncError = $e->getMessage();
        }
    }

    /**
     * Check if all system agents have a model assigned.
     */
    protected function areSystemAgentsAssigned(): bool
    {
        $systemAgents = Agent::where('system_agent', true)->whereNull('project_id')->get();

        foreach ($systemAgents as $agent) {
            if (empty($this->agentModels[$agent->id] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Step 3: Save agent model assignments.
     */
    public function assignSystemAgentModels(): void
    {
        $systemAgents = Agent::where('system_agent', true)->whereNull('project_id')->get();
        $rules = [];
        $messages = [];

        foreach ($systemAgents as $agent) {
            $rules["agentModels.{$agent->id}"] = 'required|exists:provider_models,id';
            $messages["agentModels.{$agent->id}.required"] = __('agentoom-core::admin.onboarding.model_required_for_system_agent', ['name' => $agent->name]);
        }

        $this->validate([
            'transcriptionModel' => [
                'nullable',
                Rule::exists('provider_models', 'id')->where('provider_id', $this->providerId),
            ],
        ]);

        $this->validate($rules, $messages);

        Configuration::setValue('ai_transcription_provider', $this->providerName);
        Configuration::setValue('ai_transcription_model', $this->transcriptionModel);

        foreach ($this->agentModels as $agentId => $modelId) {
            $agent = Agent::find($agentId);
            if (! $agent) {
                continue;
            }

            $config = $agent->config;
            $config['provider_model_id'] = $modelId ?: null;
            $agent->config = $config;
            $agent->save();

            // Special handling for backward compatibility / specific config keys
            if ($agent->slug === 'summarizer') {
                $specializedAgents = Configuration::getValue('ai_specialized_agents', []);
                $specializedAgents['summarizer'] = array_merge(
                    $specializedAgents['summarizer'] ?? [],
                    [
                        'agent_id' => $agent->id,
                        'model_id' => $modelId,
                    ]
                );
                Configuration::setValue('ai_specialized_agents', $specializedAgents);
            } elseif ($agent->slug === 'memory-worker') {
                Configuration::setValue('worker_agent_model_id', $modelId);
                Configuration::setValue('worker_agent_id', $agent->id);
            }
        }

        session()->flash('success', __('agentoom-core::admin.onboarding.agents_saved'));
        $this->nextStep();
    }

    /**
     * Step 4: Save credit threshold and notification settings.
     */
    public function saveCreditSettings(): void
    {
        $this->validate([
            'lowCreditThreshold' => 'required|numeric|min:0',
            'notificationEmails' => 'nullable|string',
        ]);

        Configuration::setValue('low_credit_threshold', $this->lowCreditThreshold);
        Configuration::setValue('credit_limit_notification_emails', $this->notificationEmails);
        $this->creditsConfigured = true;

        session()->flash('success', __('agentoom-core::admin.onboarding.credits_saved'));
        $this->nextStep();
    }

    /**
     * Optional step: Save email/SMTP configuration.
     */
    public function saveEmailSettings(): void
    {
        $this->validate([
            'mailHost' => 'nullable|string|max:255',
            'mailPort' => 'nullable|string|max:10',
            'mailUsername' => 'nullable|string|max:255',
            'mailPassword' => 'nullable|string|max:255',
            'mailEncryption' => 'nullable|string|max:20',
            'mailFromAddress' => 'nullable|email|max:255',
            'mailFromName' => 'nullable|string|max:255',
        ]);

        $keys = [
            'mailHost' => 'mail_host',
            'mailPort' => 'mail_port',
            'mailUsername' => 'mail_username',
            'mailPassword' => 'mail_password',
            'mailEncryption' => 'mail_encryption',
            'mailFromAddress' => 'mail_from_address',
            'mailFromName' => 'mail_from_name',
        ];

        foreach ($keys as $prop => $key) {
            Configuration::setValue($key, $this->{$prop} ?? '');
        }

        $this->stepEmailSkipped = false;
        session()->flash('success', __('agentoom-core::admin.onboarding.email_saved'));
        $this->nextStep();
    }

    /**
     * Optional step: Save compliance settings.
     */
    public function saveComplianceSettings(): void
    {
        Configuration::setValue('eu_compliance_enabled', $this->euComplianceEnabled ? '1' : '0');

        $this->stepComplianceSkipped = false;
        session()->flash('success', __('agentoom-core::admin.onboarding.compliance_saved'));
        $this->nextStep();
    }

    /**
     * Populate per-language AI disclosure texts with translated defaults.
     *
     * Loads previously saved per-language texts from Configuration, falling back to
     * the translated default string for each active language.
     */
    protected function populateAiDisclosureTexts(): void
    {
        $saved = Configuration::getValue('ai_disclosure_text', '');
        $savedMap = [];

        if (is_string($saved) && ! empty($saved)) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded)) {
                $savedMap = $decoded;
            } else {
                // Legacy single string — use as default for all languages
                $savedMap = ['_legacy' => $saved];
            }
        }

        $languages = Language::where('is_active', true)->orderBy('is_default', 'desc')->get();

        foreach ($languages as $language) {
            $this->aiDisclosureText[$language->code] = $savedMap[$language->code]
                ?? $savedMap['_legacy']
                ?? '';

            // If still empty, pre-fill with the translated default
            if (empty($this->aiDisclosureText[$language->code])) {
                // Temporarily set locale to get the correct translation
                $originalLocale = app()->getLocale();
                app()->setLocale($language->code);
                $this->aiDisclosureText[$language->code] = __('agentoom-core::admin.configs.ai_disclosure_default_text');
                app()->setLocale($originalLocale);
            }
        }
    }

    /**
     * Optional step: Save AI disclosure settings.
     */
    public function saveDisclosureSettings(): void
    {
        Configuration::setValue('ai_disclosure_enabled', $this->aiDisclosureEnabled ? '1' : '0');
        Configuration::setValue('ai_disclosure_text', json_encode($this->aiDisclosureText));
        Configuration::setValue('ai_disclosure_version', $this->aiDisclosureVersion);

        $this->stepDisclosureSkipped = false;
        session()->flash('success', __('agentoom-core::admin.onboarding.disclosure_saved'));
        $this->nextStep();
    }

    /**
     * Optional step: Save appearance settings (logo).
     */
    public function saveAppearanceSettings(): void
    {
        $this->validate([
            'appLogo' => 'nullable|image|max:1024',
        ]);

        if ($this->appLogo) {
            $path = $this->appLogo->store('logos', 'public');
            $this->appLogoUrl = '/storage/'.$path;
            Configuration::setValue('app_logo', $this->appLogoUrl);
        }

        $this->stepAppearanceSkipped = false;
        session()->flash('success', __('agentoom-core::admin.onboarding.appearance_saved'));
        $this->nextStep();
    }

    /**
     * Skip an optional step.
     */
    public function skipOptionalStep(): void
    {
        $stepKey = $this->currentStepData()['key'] ?? '';

        match ($stepKey) {
            'email' => $this->stepEmailSkipped = true,
            'compliance' => $this->stepComplianceSkipped = true,
            'disclosure' => $this->stepDisclosureSkipped = true,
            'appearance' => $this->stepAppearanceSkipped = true,
            default => null,
        };

        $this->nextStep();
    }

    /**
     * Final step: Complete onboarding and redirect to dashboard.
     */
    public function complete(): void
    {
        Configuration::setValue('onboarding_complete', '1');

        $this->redirect(route('admin.dashboard'));
    }

    public function getProviderOptions(): array
    {
        $options = [];
        foreach (Lab::cases() as $lab) {
            $readableName = config('agentoom.providers.'.$lab->value.'.name', ucfirst($lab->value));
            $options[$lab->value] = $readableName;
        }

        $options['custom'] = 'Custom (OpenAI-compatible)';

        return $options;
    }

    /**
     * Get synced models for the provider.
     *
     * @return Collection
     */
    public function getSyncedModels()
    {
        if (! $this->providerId) {
            return collect();
        }

        return ProviderModel::where('provider_id', $this->providerId)
            ->where('is_active', true)
            ->orderBy('model_name')
            ->get();
    }

    /**
     * Get transcription-capable models for the provider.
     * Matches audio input modality and audio/transcription model naming,
     * falling back to all synced models if none specifically match.
     *
     * @return Collection
     */
    public function getTranscriptionModels()
    {
        if (! $this->providerId) {
            return collect();
        }

        $audioModels = ProviderModel::query()
            ->where('provider_id', $this->providerId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('model_features->architecture->input_modalities', 'like', '%"audio"%')
                    ->orWhere('model_features->architecture->output_modalities', 'like', '%"transcription"%')
                    ->orWhere('model_features->architecture->modality', 'like', '%audio%')
                    ->orWhere('model_name', 'like', '%whisper%')
                    ->orWhere('model_name', 'like', '%transcrib%')
                    ->orWhere('model_name', 'like', '%audio%')
                    ->orWhere('model_name', 'like', '%speech%')
                    ->orWhere('model_name', 'like', '%voxtral%');
            })
            ->orderBy('model_name')
            ->get();

        return $audioModels->isNotEmpty() ? $audioModels : $this->getSyncedModels();
    }

    /**
     * Build a summary of the onboarding configuration for the review step.
     *
     * @return array<int, array{label: string, value: string, status: string}>
     */
    public function getSummary(): array
    {
        $summary = [];

        // Provider
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_provider'),
            'value' => $this->providerName ?: __('agentoom-core::admin.onboarding.not_configured'),
            'status' => $this->providerName ? 'configured' : 'missing',
        ];

        // Models
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_models'),
            'value' => $this->modelsSynced ? __('agentoom-core::admin.onboarding.summary_models_count', ['count' => $this->syncedCount]) : __('agentoom-core::admin.onboarding.not_synced'),
            'status' => $this->modelsSynced ? 'configured' : 'missing',
        ];

        // System agents
        $systemAgents = Agent::where('system_agent', true)->whereNull('project_id')->get();
        foreach ($systemAgents as $agent) {
            $modelId = $this->agentModels[$agent->id] ?? null;
            $summary[] = [
                'label' => __('agentoom-core::admin.onboarding.summary_agent_model', ['name' => $agent->name]),
                'value' => $modelId ? $this->getModelNameById($modelId) : __('agentoom-core::admin.onboarding.not_assigned'),
                'status' => $modelId ? 'configured' : 'warning',
            ];
        }

        $summary[] = [
            'label' => __('agentoom-core::admin.configs.transcription_model'),
            'value' => $this->transcriptionModel ? $this->getModelNameById($this->transcriptionModel) : __('agentoom-core::admin.onboarding.not_set'),
            'status' => $this->transcriptionModel ? 'configured' : 'skipped',
        ];

        // Other agents
        $otherAgents = Agent::where('system_agent', false)->whereNull('project_id')->get();
        foreach ($otherAgents as $agent) {
            $modelId = $this->agentModels[$agent->id] ?? null;
            if ($modelId) {
                $summary[] = [
                    'label' => __('agentoom-core::admin.onboarding.summary_agent_model', ['name' => $agent->name]),
                    'value' => $this->getModelNameById($modelId),
                    'status' => 'configured',
                ];
            }
        }

        // Credits
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_credit_threshold'),
            'value' => '$'.number_format($this->lowCreditThreshold, 2),
            'status' => 'configured',
        ];

        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_notification_emails'),
            'value' => $this->notificationEmails ?: __('agentoom-core::admin.onboarding.not_set'),
            'status' => $this->notificationEmails ? 'configured' : 'neutral',
        ];

        // Email
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_email'),
            'value' => $this->stepEmailSkipped ? __('agentoom-core::admin.onboarding.skipped') : ($this->mailHost ? $this->mailHost : __('agentoom-core::admin.onboarding.skipped')),
            'status' => $this->stepEmailSkipped ? 'skipped' : ($this->mailHost ? 'configured' : 'skipped'),
        ];

        // Compliance
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_compliance'),
            'value' => $this->stepComplianceSkipped ? __('agentoom-core::admin.onboarding.skipped') : ($this->euComplianceEnabled ? __('agentoom-core::admin.onboarding.enabled') : __('agentoom-core::admin.onboarding.disabled')),
            'status' => $this->stepComplianceSkipped ? 'skipped' : 'configured',
        ];

        // AI Disclosure
        $summary[] = [
            'label' => __('agentoom-core::admin.onboarding.summary_disclosure'),
            'value' => $this->stepDisclosureSkipped ? __('agentoom-core::admin.onboarding.skipped') : ($this->aiDisclosureEnabled ? __('agentoom-core::admin.onboarding.enabled') : __('agentoom-core::admin.onboarding.disabled')),
            'status' => $this->stepDisclosureSkipped ? 'skipped' : 'configured',
        ];

        return $summary;
    }

    /**
     * Check if all mandatory steps are completed.
     */
    public function canComplete(): bool
    {
        return $this->providerName !== '' && $this->modelsSynced;
    }

    public function render(): mixed
    {
        return view('agentoom-core::livewire.admin.onboarding-wizard', [
            'providerOptions' => $this->getProviderOptions(),
            'syncedModels' => $this->getSyncedModels(),
            'transcriptionModels' => $this->getTranscriptionModels(),
            'summary' => $this->getSummary(),
            'stepsList' => $this->steps(),
            'systemAgents' => Agent::where('system_agent', true)->whereNull('project_id')->get(),
            'otherAgents' => Agent::where('system_agent', false)->whereNull('project_id')->get(),
            'activeLanguages' => Language::where('is_active', true)->orderBy('is_default', 'desc')->get(),
        ])->layout('agentoom-core::layouts.admin', [
            'title' => __('agentoom-core::admin.onboarding.title'),
            'headerTitle' => __('agentoom-core::admin.onboarding.title'),
        ]);
    }

    private function getModelNameById(string $modelId): string
    {
        return ProviderModel::find($modelId)?->model_name ?? $modelId;
    }
}
