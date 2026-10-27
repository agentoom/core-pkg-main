<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\VisibilityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class ConfigIndex extends Component
{
    use WithFileUploads;

    public string $ai_provider = '';

    public string $ai_api_key = '';

    public string $ai_model = '';

    public string $ai_output_modality = 'all';

    public string $ai_transcription_provider = '';

    public string $ai_transcription_model = '';

    public string $ai_transcription_output_modality = 'all';

    public string $ai_temperature = '0.4';

    public string $ai_top_p = '0.8';

    public string $ai_model_suffix = '';

    public bool $ai_disclosure_enabled = false;

    public string $ai_disclosure_text = '';

    public string $ai_disclosure_version = '';

    public float $low_credit_threshold = 1.0;

    public float $app_ai_credits = 0.0;

    public float $openrouter_multiplier = 1.25;

    public string $credit_limit_notification_emails = '';

    public $app_logo;

    public ?string $app_logo_url = null;

    public string $email_footer_content = '';

    public string $chat_embed_allowed_domains = '';

    public bool $eu_compliance_enabled = false;

    public bool $privacy_redaction_enabled = false;

    public bool $compliance_audit_logging = true;

    public bool $compliance_emergency_stops = true;

    public bool $compliance_auto_incidents = true;

    public bool $compliance_auto_alerts = true;

    public bool $compliance_governance_gate = true;

    public bool $compliance_ai_disclosure = true;

    public bool $compliance_risk_enforcement = true;

    public array $new_pricing = [
        'provider' => '',
        'model' => '',
        'input_price_per_million' => null,
        'output_price_per_million' => null,
    ];

    /** @var array<int, array{name: string, total: int, sources: array<int, string>}> */
    public array $allVisibilityGroups = [];

    /** @var array<string, bool> */
    public array $activeVisibilityGroups = [];

    public bool $isOverridingVisibilityEnv = false;

    public function mount(): void
    {
        $this->ai_provider = Configuration::getValue('ai_provider', config('ai.default', 'openai'));
        $this->ai_api_key = Configuration::getValue('ai_api_key', '');
        $this->ai_model = Configuration::getValue('ai_model', 'gpt-4o');
        $this->ai_output_modality = Configuration::getValue('ai_output_modality', 'all');
        $this->ai_temperature = Configuration::getValue('ai_temperature', '');
        $this->ai_top_p = Configuration::getValue('ai_top_p', '');
        $this->ai_model_suffix = Configuration::getValue('ai_model_suffix', '');
        $this->ai_disclosure_enabled = (bool) Configuration::getValue('ai_disclosure_enabled', false);
        $this->loadAiDisclosureText();
        $this->ai_disclosure_version = Configuration::getValue('ai_disclosure_version', '1.0.0');
        $this->ai_transcription_provider = Configuration::getValue('ai_transcription_provider', '');
        $this->ai_transcription_model = Configuration::getValue('ai_transcription_model', '');
        $this->ai_transcription_output_modality = Configuration::getValue('ai_transcription_output_modality', 'all');
        $this->low_credit_threshold = (float) Configuration::getValue('low_credit_threshold', 1.0);
        $this->app_ai_credits = (float) Configuration::getValue('app_ai_credits', 0.0);
        $this->openrouter_multiplier = (float) Configuration::getValue('openrouter_multiplier', 1.25);
        $this->credit_limit_notification_emails = Configuration::getValue('credit_limit_notification_emails', '');
        $this->app_logo_url = Configuration::getValue('app_logo');
        $this->email_footer_content = Configuration::getValue('email_footer_content', '');
        $this->chat_embed_allowed_domains = Configuration::getValue('chat_embed_allowed_domains', '');

        // Compliance settings — reads from DB with fallback to .env via the
        // compliance integration (no-op when the optional package is absent).
        $compliance = app(ComplianceIntegration::class);
        $this->eu_compliance_enabled = $compliance->isEnabled();
        $privacyMasterSwitch = Configuration::getValue('privacy_redaction_enabled');
        $this->privacy_redaction_enabled = $privacyMasterSwitch !== null
            ? (bool) $privacyMasterSwitch
            : (bool) config('agentoom-privacy.enabled', false);
        $this->compliance_audit_logging = $compliance->isFeatureEnabled('audit_logging');
        $this->compliance_emergency_stops = $compliance->isFeatureEnabled('emergency_stops');
        $this->compliance_auto_incidents = $compliance->isFeatureEnabled('auto_incidents');
        $this->compliance_auto_alerts = $compliance->isFeatureEnabled('auto_alerts');
        $this->compliance_governance_gate = $compliance->isFeatureEnabled('governance_gate');
        $this->compliance_ai_disclosure = $compliance->isFeatureEnabled('ai_disclosure');
        $this->compliance_risk_enforcement = $compliance->isFeatureEnabled('risk_enforcement');

        $allProviders = Provider::all();
        if ($allProviders->count() === 1) {
            $onlyProvider = $allProviders->first();
            $this->ai_provider = $onlyProvider->id;
            $this->ai_transcription_provider = $onlyProvider->id;

            if (! $this->ai_api_key) {
                $this->ai_api_key = $onlyProvider->api_key;
            }
        }

        // Visibility groups
        $visibilityService = app(VisibilityService::class);
        $this->allVisibilityGroups = $visibilityService->discoverAllGroups()->toArray();

        $dbValue = Configuration::getValue(VisibilityService::CONFIG_KEY);
        $this->isOverridingVisibilityEnv = is_string($dbValue) && $dbValue !== '';

        $currentActive = $visibilityService->getActiveGroups();
        foreach ($this->allVisibilityGroups as $group) {
            $name = $group['name'];
            $this->activeVisibilityGroups[$name] = $currentActive->isEmpty() || $currentActive->contains($name);
        }
    }

    protected function rules(): array
    {
        return [
            'ai_provider' => 'nullable|string|max:100',
            'ai_api_key' => 'nullable|string|max:500',
            'ai_model' => 'nullable|string|max:255',
            'ai_output_modality' => 'nullable|string',
            'ai_temperature' => 'nullable|string|max:50',
            'ai_top_p' => 'nullable|string|max:50',
            'ai_model_suffix' => 'nullable|string|max:50',
            'ai_disclosure_enabled' => 'boolean',
            'ai_disclosure_text' => 'nullable|string',
            'ai_disclosure_version' => 'nullable|string|max:20',
            'ai_transcription_provider' => 'nullable|string|max:100',
            'ai_transcription_model' => 'nullable|string|max:255',
            'ai_transcription_output_modality' => 'nullable|string',
            'low_credit_threshold' => 'required|numeric|min:0',
            'openrouter_multiplier' => 'required|numeric|min:1',
            'credit_limit_notification_emails' => 'nullable|string',
            'app_logo' => 'nullable|image|max:1024',
            'email_footer_content' => 'nullable|string',
            'chat_embed_allowed_domains' => 'nullable|string',
            'eu_compliance_enabled' => 'boolean',
            'privacy_redaction_enabled' => 'boolean',
            'compliance_audit_logging' => 'boolean',
            'compliance_emergency_stops' => 'boolean',
            'compliance_auto_incidents' => 'boolean',
            'compliance_auto_alerts' => 'boolean',
            'compliance_governance_gate' => 'boolean',
            'compliance_ai_disclosure' => 'boolean',
            'compliance_risk_enforcement' => 'boolean',
        ];
    }

    public function updatedAiProvider(): void
    {
        if ($this->ai_provider == 0) {
            $this->ai_api_key = '';
            $this->ai_model = '';

            return;
        }
        $provider = is_numeric($this->ai_provider)
            ? Provider::find($this->ai_provider)
            : Provider::where('name', $this->ai_provider)->first();

        if ($provider) {
            $this->ai_api_key = $provider->api_key;
        } else {
            $this->ai_api_key = '';
        }

        $this->ai_output_modality = 'all';
        $this->ai_model = '';
    }

    public function updatedAiOutputModality(): void
    {
        $this->ai_model = '';
    }

    public function updatedAiTranscriptionProvider(): void
    {
        $this->ai_transcription_output_modality = 'all';
        $this->ai_transcription_model = '';
    }

    public function updatedAiTranscriptionOutputModality(): void
    {
        $this->ai_transcription_model = '';
    }

    public function save(): void
    {
        $this->validate();

        $isSuperAdmin = auth()->user()->hasRole('superadmin');

        if ($this->app_logo && $isSuperAdmin) {
            $path = $this->app_logo->store('logos', 'public');
            $this->app_logo_url = '/storage/'.$path;
            Configuration::setValue('app_logo', $this->app_logo_url);
        }

        $keys = [
            'ai_provider', 'ai_api_key', 'ai_model', 'ai_output_modality', 'ai_temperature', 'ai_top_p', 'ai_model_suffix',
            'ai_disclosure_enabled', 'ai_disclosure_text', 'ai_disclosure_version',
            'ai_transcription_provider', 'ai_transcription_model', 'ai_transcription_output_modality',
            'low_credit_threshold',
            'openrouter_multiplier', 'credit_limit_notification_emails', 'email_footer_content',
            'chat_embed_allowed_domains',
        ];

        $complianceKeys = [
            'eu_compliance_enabled', 'privacy_redaction_enabled',
            'compliance_audit_logging', 'compliance_emergency_stops',
            'compliance_auto_incidents', 'compliance_auto_alerts',
            'compliance_governance_gate', 'compliance_ai_disclosure',
            'compliance_risk_enforcement',
        ];

        $superAdminOnlyKeys = [
            'ai_provider', 'ai_api_key', 'ai_model', 'ai_output_modality', 'ai_temperature', 'ai_top_p', 'ai_model_suffix',
            'ai_disclosure_enabled', 'ai_disclosure_text', 'ai_disclosure_version',
            'ai_transcription_provider', 'ai_transcription_model', 'ai_transcription_output_modality',
            'openrouter_multiplier', 'email_footer_content',
            'chat_embed_allowed_domains',
            ...$complianceKeys,
        ];

        foreach ($keys as $key) {
            if (in_array($key, $superAdminOnlyKeys) && ! $isSuperAdmin) {
                continue;
            }

            if ($key === 'ai_disclosure_text') {
                $this->persistAiDisclosureText();

                continue;
            }

            Configuration::setValue($key, $this->{$key} ?? '');
        }

        // Save compliance keys (superadmin-only)
        if ($isSuperAdmin) {
            foreach ($complianceKeys as $key) {
                Configuration::setValue($key, $this->{$key} ?? false);
            }
        }

        session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
    }

    public function addPricing(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return;
        }

        $this->validate([
            'new_pricing.provider' => 'required|string|max:100',
            'new_pricing.model' => 'required|string|max:255',
            'new_pricing.input_price_per_million' => 'required|numeric|min:0',
            'new_pricing.output_price_per_million' => 'required|numeric|min:0',
        ]);

        AiModelPricing::updateOrCreate(
            ['provider' => $this->new_pricing['provider'], 'model' => $this->new_pricing['model'], 'source' => 'manual'],
            [
                'input_price_per_million' => (float) $this->new_pricing['input_price_per_million'],
                'output_price_per_million' => (float) $this->new_pricing['output_price_per_million'],
                'priced_at' => now(),
                'last_seen_at' => now(),
            ]
        );

        $this->new_pricing = [
            'provider' => '', 'model' => '', 'input_price_per_million' => null, 'output_price_per_million' => null,
        ];
        session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
    }

    public function deletePricing(int $id): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return;
        }

        $pricing = AiModelPricing::find($id);
        if ($pricing?->source === 'manual') {
            $pricing->delete();
            session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
        }
    }

    public function getSelectedModelPricingProperty(): ?AiModelPricing
    {
        if (! $this->ai_model) {
            return null;
        }

        $id = $this->ai_model;
        $model = is_numeric($id) ? ProviderModel::with('provider')->find($id) : ProviderModel::with('provider')->where('model_name', $id)->first();

        if (! $model) {
            return null;
        }

        return AiModelPricing::where('provider', $model->provider->driver ?: $model->provider->name)
            ->where('model', $model->model_name)
            ->whereIn('source', ['manual', 'provider_native', 'litellm', 'models_dev'])
            ->orderByRaw("CASE source WHEN 'manual' THEN 1 WHEN 'provider_native' THEN 2 WHEN 'litellm' THEN 3 ELSE 4 END")
            ->first();
    }

    /**
     * Load the AI disclosure text for the current locale from the per-language JSON map.
     * Falls back to the translated default when no saved value exists.
     */
    protected function loadAiDisclosureText(): void
    {
        $raw = Configuration::getValue('ai_disclosure_text', '');

        if (empty($raw)) {
            $this->ai_disclosure_text = __('agentoom-core::admin.configs.ai_disclosure_default_text');

            return;
        }

        if (is_array($raw)) {
            $raw = json_encode($raw);
        }

        $map = json_decode($raw, true);

        if (! is_array($map)) {
            // Legacy single string
            $this->ai_disclosure_text = $raw;

            return;
        }

        $locale = app()->getLocale();

        $this->ai_disclosure_text = $map[$locale]
            ?? $map['en']
            ?? reset($map)
            ?? __('agentoom-core::admin.configs.ai_disclosure_default_text');
    }

    /**
     * Persist the AI disclosure text by merging the current locale's value
     * into the per-language JSON map, preserving values for other languages.
     */
    protected function persistAiDisclosureText(): void
    {
        $existing = Configuration::getValue('ai_disclosure_text', '');
        $map = [];

        if (! empty($existing)) {
            $existing = is_array($existing) ? json_encode($existing) : $existing;
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }

        $locale = app()->getLocale();
        $map[$locale] = $this->ai_disclosure_text;

        Configuration::setValue('ai_disclosure_text', json_encode($map));
    }

    public function toggleVisibilityGroup(string $groupName): void
    {
        $this->activeVisibilityGroups[$groupName] = ! ($this->activeVisibilityGroups[$groupName] ?? false);
    }

    public function activateAllVisibilityGroups(): void
    {
        foreach ($this->activeVisibilityGroups as $name => $active) {
            $this->activeVisibilityGroups[$name] = true;
        }
    }

    public function deactivateAllVisibilityGroups(): void
    {
        foreach ($this->activeVisibilityGroups as $name => $active) {
            $this->activeVisibilityGroups[$name] = false;
        }
    }

    public function saveVisibility(): void
    {
        $activeList = collect($this->activeVisibilityGroups)
            ->filter(fn ($active) => $active)
            ->keys()
            ->toArray();

        if (empty($activeList)) {
            Configuration::setValue(VisibilityService::CONFIG_KEY, '');
        } else {
            Configuration::setValue(VisibilityService::CONFIG_KEY, implode(',', $activeList));
        }

        $this->isOverridingVisibilityEnv = true;
        Cache::forget('agentoom_actionable_methods');

        // Re-discover tools to update availability based on new visibility settings
        app(McpDiscoveryService::class)->discoverAndRegister();

        session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
    }

    public function resetVisibilityToEnv(): void
    {
        Configuration::where('key', VisibilityService::CONFIG_KEY)->delete();
        $this->isOverridingVisibilityEnv = false;

        $visibilityService = app(VisibilityService::class);
        $currentActive = $visibilityService->getActiveGroups();

        foreach ($this->allVisibilityGroups as $group) {
            $name = $group['name'];
            $this->activeVisibilityGroups[$name] = $currentActive->isEmpty() || $currentActive->contains($name);
        }

        Cache::forget('agentoom_actionable_methods');

        // Re-discover tools to update availability based on new visibility settings
        app(McpDiscoveryService::class)->discoverAndRegister();

        session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
    }

    public function render()
    {
        $providers = Provider::all()->pluck('name', 'id');
        $allModelPricings = AiModelPricing::all();

        $models = collect();
        if ($this->ai_provider) {
            $models = $this->getModelsForProvider($this->ai_provider, $this->ai_output_modality);
        }

        $transcription_models = collect();
        if ($this->ai_transcription_provider) {
            $transcription_models = $this->getModelsForProvider($this->ai_transcription_provider, $this->ai_transcription_output_modality);
        }

        return view('agentoom-core::livewire.admin.config-index', [
            'providers' => $providers,
            'models' => $models,
            'transcription_models' => $transcription_models,
            'allModelPricings' => $allModelPricings,
            'pricingSyncs' => DB::table('ai_pricing_syncs')->orderBy('source')->get(),
        ])->layout('agentoom-core::layouts.admin', [
            'title' => __('agentoom-core::admin.configs.title'),
            'headerTitle' => __('agentoom-core::admin.configs.title'),
        ]);
    }

    protected function getModelsForProvider($providerId, ?string $modality = null)
    {
        $query = ProviderModel::where(function ($query) use ($providerId) {
            if (is_numeric($providerId)) {
                $query->where('provider_id', $providerId);
            } else {
                $query->whereHas('provider', function ($q) use ($providerId) {
                    $q->where('name', $providerId);
                });
            }
        })
            ->where('is_active', true);

        // Check if provider is OpenRouter
        $provider = is_numeric($providerId)
            ? Provider::find($providerId)
            : Provider::where('name', $providerId)->first();

        if ($provider && $provider->name === 'openrouter' && $modality && $modality !== 'all') {
            if ($modality === 'transcription') {
                $query->where(function ($q) {
                    $q->where('model_features->architecture->input_modalities', 'like', '%"audio"%')
                        ->orWhere('model_features->architecture->modality', 'like', '%audio%')
                        ->orWhere('model_name', 'like', '%whisper%')
                        ->orWhere('model_name', 'like', '%transcrib%')
                        ->orWhere('model_name', 'like', '%audio%')
                        ->orWhere('model_name', 'like', '%speech%')
                        ->orWhere('model_name', 'like', '%voxtral%');
                });
            } else {
                $query->where('model_features->architecture->output_modalities', 'like', '%"'.$modality.'"%');
            }
        }

        return $query->pluck('model_name', 'id');
    }
}
