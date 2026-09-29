<?php

namespace Agentoom\Core\Ai\Agents;

use Agentoom\ChatWidget\Ai\Tools\BanUserTool;
use Agentoom\Core\Ai\Middleware\LogAgentInteraction;
use Agentoom\Core\Ai\Tools\PipelineExecutionTool;
use Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool;
use Agentoom\Core\Ai\Tools\Public\ScheduleRepositoryTool;
use Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool;
use Agentoom\Core\Ai\Tools\ReadSkillTool;
use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Contracts\RunsInBackgroundContext;
use Agentoom\Core\Contracts\RunsInScheduleContext;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Execution\ActionExecutor;
use Agentoom\Core\Execution\ToolPipelineWrapper;
use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Models\Agent as AgentModel;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\ScheduleLog;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\UserInformation;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Services\Seo\SeoMarketingConstraintService;
use Agentoom\Core\Services\ToolRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

#[Timeout(300)]
class DynamicAgent implements Agent, Conversational, HasMiddleware, HasTools, RunsInBackgroundContext, RunsInScheduleContext
{
    use Promptable {
        prompt as traitPrompt;
        stream as traitStream;
    }
    use RemembersConversations {
        messages as storedConversationMessages;
    }

    protected ?array $cachedAiConfig = null;

    public function __construct(
        protected AgentModel $agentModel,
        protected ?object $widget = null,
        protected ?int $scheduleId = null,
        protected bool $isHeadless = false,
        protected ?int $pipelineRunId = null,
        protected ?int $aiTaskId = null,
        protected ?int $endpointId = null,
        protected ?int $scheduleLogId = null,
        protected ?int $pipelineStepOrder = null,
        protected ?int $endpointLogId = null,
        protected ?object $user = null,
        protected ?string $extraInstructions = null,
        protected bool $silent = false,
        protected bool $replayConversationHistory = true
    ) {
        $participant = $user ?: auth()->user() ?: (object) ['id' => 0];
        $this->conversationUser = $participant;

        $allowMemoryAnalysis = $this->agentModel->allow_memory_analysis ?? true;

        $threadId = null;
        if ($this->aiTaskId) {
            $task = AiTask::find($this->aiTaskId);
            $allowMemoryAnalysis = $allowMemoryAnalysis && ($task?->allow_memory_analysis ?? false);
            $threadId = "ai_task_{$this->aiTaskId}";
        }

        if ($this->pipelineRunId) {
            $run = PipelineRun::with('pipeline')->find($this->pipelineRunId);
            $allowMemoryAnalysis = $allowMemoryAnalysis && ($run?->pipeline?->allow_memory_analysis ?? false);
            $threadId ??= "pipeline_run_{$this->pipelineRunId}";
        }

        if ($this->scheduleLogId) {
            $log = ScheduleLog::with('schedule')->find($this->scheduleLogId);
            $allowMemoryAnalysis = $allowMemoryAnalysis && ($log?->schedule?->allow_memory_analysis ?? false);
            $threadId ??= "schedule_log_{$this->scheduleLogId}";
        }

        if ($this->endpointLogId) {
            $log = EndpointLog::with('endpoint')->find($this->endpointLogId);
            $allowMemoryAnalysis = $allowMemoryAnalysis && ($log?->endpoint?->allow_memory_analysis ?? false);
            $threadId ??= "endpoint_log_{$this->endpointLogId}";
        }

        if ($threadId && ! $this->isHeadless) {
            $this->withThread($threadId, $allowMemoryAnalysis);
        }
    }

    public function getAgentModel(): AgentModel
    {
        return $this->agentModel;
    }

    public function getScheduleId(): ?int
    {
        return $this->scheduleId;
    }

    public function getScheduleLogId(): ?int
    {
        return $this->scheduleLogId;
    }

    public function getPipelineRunId(): ?int
    {
        return $this->pipelineRunId;
    }

    public function getAiTaskId(): ?int
    {
        return $this->aiTaskId;
    }

    public function getEndpointId(): ?int
    {
        return $this->endpointId;
    }

    public function getPipelineStepOrder(): ?int
    {
        return $this->pipelineStepOrder;
    }

    public function getEndpointLogId(): ?int
    {
        return $this->endpointLogId;
    }

    public function provider(): ?string
    {
        return $this->getAiConfig()['provider_name'];
    }

    public function model(): ?string
    {
        return $this->getAiConfig()['model'];
    }

    protected function getAiConfig(): array
    {
        if ($this->cachedAiConfig === null) {
            $this->cachedAiConfig = InjectCurrentAiConfigService::inject($this->agentModel);
        }

        return $this->cachedAiConfig;
    }

    protected function isPartEnabled(string $part): bool
    {
        $alwaysEnabled = [
            'role', 'objective', 'instructions', 'operational_protocol', 'prioritize',
            'constraints', 'conditional_rules', 'error_handling', 'output_format', 'examples',
        ];

        if (in_array($part, $alwaysEnabled)) {
            return true;
        }

        if ($part === 'language_policy') {
            return (bool) ($this->agentModel->prompt_settings['parts']['language_policy'] ?? false);
        }

        $mapping = [
            'user_context' => [
                'Agentoom\Core\Ai\Tools\Public\SearchUserInformationTool',
                'Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool',
                'Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool',
            ],
            'project_repository' => [
                'Agentoom\Core\Ai\Tools\Public\ProjectRepositoryTool',
            ],
            'interaction_forms' => [
                'Agentoom\ChatWidget\Ai\Tools\SuggestFormsTool',
            ],
            'interaction_prompts' => [
                'Agentoom\ChatWidget\Ai\Tools\SuggestPromptsTool',
            ],
            'ban_policy' => [
                'Agentoom\ChatWidget\Ai\Tools\BanUserTool',
            ],
            'human_support' => array_values(array_filter([$this->humanIntervention()->toolClass()])),
        ];

        if ($part === 'human_support' && $this->agentModel->allow_human_intervention && $this->humanIntervention()->isSupported()) {
            return true;
        }

        if (isset($mapping[$part])) {
            foreach ($mapping[$part] as $toolClass) {
                if ($this->hasToolAssigned($toolClass)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    protected function hasToolAssigned(string $toolClass): bool
    {
        // Check direct tools
        if ($this->agentModel->tools()->where('class_name', $toolClass)->exists()) {
            return true;
        }

        // Check skill tools
        foreach ($this->agentModel->skills()->get() as $skill) {
            if (in_array($toolClass, $skill->tools ?? [])) {
                return true;
            }
        }

        // Check for tools in the registry (global tools)
        if (in_array($toolClass, app(ToolRegistry::class)->all())) {
            return true;
        }

        // Automatic tools added in DynamicAgent::tools()
        $automaticTools = [
            'Agentoom\Core\Ai\Tools\Public\ScheduleRepositoryTool',
            'Agentoom\Sentinel\Ai\Tools\VerifySecurityCode',
        ];

        if (in_array($toolClass, $automaticTools)) {
            return true;
        }

        return false;
    }

    /**
     * The installed human-handoff capability, or core's no-op default.
     */
    protected function humanIntervention(): HumanIntervention
    {
        return app(HumanIntervention::class);
    }

    protected function isToolEnabled(string $toolClass): bool
    {
        $humanTool = $this->humanIntervention()->toolClass();

        if ($humanTool !== null && $toolClass === $humanTool) {
            // The handoff tool ships with the optional live-support package and
            // is only ever offered to agents that opted into human intervention.
            return (bool) $this->agentModel->allow_human_intervention;
        }

        return $this->hasToolAssigned($toolClass);
    }

    /**
     * Bind the agent to an existing thread or create it when missing.
     *
     * The thread is keyed on agent_conversations.session_id: channels that own a
     * stable external identifier (background runs, or a client chat id coming
     * through the OpenAI-compatible API) anchor every execution on it so that
     * messages and compliance traces stay attached to the same conversation.
     */
    public function withThread(string $threadId, bool $allowMemoryAnalysis = true, ?int $userId = null, ?string $title = null): self
    {
        $conversation = DB::table('agent_conversations')
            ->where('session_id', $threadId)
            ->first();

        if ($conversation) {
            $this->conversationId = $conversation->id;

            // Always ensure the memory analysis flag is correctly set according to current context
            DB::table('agent_conversations')
                ->where('id', $this->conversationId)
                ->update([
                    'allow_memory_analysis' => $allowMemoryAnalysis,
                    'updated_at' => now(),
                ]);
        } else {
            $this->conversationId = (string) Str::uuid();
            DB::table('agent_conversations')->insert([
                'id' => $this->conversationId,
                'session_id' => $threadId,
                'user_id' => $userId,
                'agent_id' => $this->agentModel->id,
                'project_id' => $this->agentModel->project_id,
                'title' => $title ?? 'Background Task: '.$threadId,
                'allow_memory_analysis' => $allowMemoryAnalysis,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this;
    }

    public function instructions(): string
    {
        Log::info("DynamicAgent [{$this->agentModel->name}] (ID: {$this->agentModel->id}) generating instructions", [
            'history_count' => count($this->messages()),
            'schedule_id' => $this->scheduleId,
        ]);

        $sections = [];

        // 1. ROLE
        if ($this->isPartEnabled('role')) {
            $role = ! empty($this->agentModel->role) ? Helpers::cleanHtmlTags($this->agentModel->role) : 'You are '.$this->agentModel->name;
            $sections[] = "[ROLE]\n".$role;
        }

        // 2. OBJECTIVE
        if ($this->isPartEnabled('objective') && ! empty($this->agentModel->objective)) {
            $sections[] = "[OBJECTIVE]\n".Helpers::cleanHtmlTags($this->agentModel->objective);
        }

        // 3. INSTRUCTIONS (the existing instructions column)
        if ($this->isPartEnabled('instructions') && ! empty($this->agentModel->instructions)) {
            $sections[] = "[INSTRUCTIONS]\n".Helpers::cleanHtmlTags($this->agentModel->instructions);
        }

        // OPERATIONAL PROTOCOL
        if ($this->isPartEnabled('operational_protocol') && ! empty($this->agentModel->operational_protocol)) {
            $sections[] = "[OPERATIONAL PROTOCOL]\n".Helpers::cleanHtmlTags($this->agentModel->operational_protocol);
        }

        // 4. PRIORITIZE
        if ($this->isPartEnabled('prioritize') && ! empty($this->agentModel->prioritize)) {
            $s = "[PRIORITIZE]\n";
            foreach ($this->agentModel->prioritize as $p) {
                if (! empty($p)) {
                    $s .= '- '.$this->toString($p)."\n";
                }
            }
            $sections[] = trim($s);
        }

        // 5. CONSTRAINTS
        if ($this->isPartEnabled('constraints') && (! empty($this->agentModel->constraints) || $this->agentModel->is_cautious_constraint_enabled)) {
            $s = "[CONSTRAINTS]\n";
            if ($this->agentModel->is_cautious_constraint_enabled) {
                $s .= "- If the question or the task is ambiguous or you lack key information, say 'I don't know' and ask a clarifying question. If you are uncertain, provide a cautious answer and clearly state assumptions instead of guessing.\n";
            }
            if (! empty($this->agentModel->constraints)) {
                foreach ($this->agentModel->constraints as $c) {
                    if (! empty($c)) {
                        $s .= '- '.$this->toString($c)."\n";
                    }
                }
            }
            $sections[] = trim($s);
        }

        // 6. CONDITIONAL RULES
        if ($this->isPartEnabled('conditional_rules') && ! empty($this->agentModel->conditional_rules)) {
            $s = "[CONDITIONAL RULES]\n";
            foreach ($this->agentModel->conditional_rules as $rule) {
                if (! empty($rule['condition'])) {
                    $s .= '- IF '.$this->toString($rule['condition']).' THEN '.$this->toString($rule['if_true'] ?? 'do nothing');
                    if (! empty($rule['if_false'])) {
                        $s .= ' OTHERWISE '.$this->toString($rule['if_false']);
                    }
                    $s .= "\n";
                }
            }
            $sections[] = trim($s);
        }

        // ERROR HANDLING
        if ($this->isPartEnabled('error_handling') && ! empty($this->agentModel->error_handling)) {
            $sections[] = "[ERROR HANDLING]\n".Helpers::cleanHtmlTags($this->agentModel->error_handling);
        }

        // 7. OUTPUT FORMAT
        if ($this->isPartEnabled('output_format') && ! empty($this->agentModel->output_format)) {
            $s = "[OUTPUT FORMAT]\n";
            foreach ($this->agentModel->output_format as $f) {
                if (! empty($f)) {
                    $s .= '- '.$this->toString($f)."\n";
                }
            }
            $sections[] = trim($s);
        }

        // 8. EXAMPLES
        if ($this->isPartEnabled('examples') && ! empty($this->agentModel->examples)) {
            $s = "[EXAMPLES]\n";
            foreach ($this->agentModel->examples as $example) {
                if (! empty($example['input'])) {
                    $s .= 'Input: '.$this->toString($example['input'])."\n";
                    $s .= 'Output: '.$this->toString($example['output'] ?? '')."\n\n";
                }
            }
            $sections[] = trim($s);
        }

        $prompt = [implode("\n\n", array_filter($sections))];

        $user = $this->user ?: auth()->user();

        if ($user && ($this->isPartEnabled('user_context') || (! $this->isHeadless && ! $this->scheduleId))) {
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];

            if ($this->isHeadless || $this->scheduleId) {
                $userContext = '## Current User Context
The current user context is '.json_encode($userData).'. Only provide information or perform actions they are authorized for. You MUST use your tools to find the resources assigned to this user. DO NOT expect or require any user interaction as this is a background execution. Figure out what to do using the available tools and information.';
            } else {
                $userContext = '## Current User Context
You are interacting with '.json_encode($userData).'. Always address them by name and only provide information or perform actions they are authorized for. You MUST use your tools to find the resources assigned to this user before asking for details like IP addresses, usernames, or server names.

You have access to personal information about this user via the \'SearchUserInformationTool\'. Use it to retrieve additional details when relevant.';

                $importantMemories = UserInformation::query()
                    ->where('user_id', $user->id)
                    ->where('importance', '>=', 3)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->orderByDesc('importance')
                    ->latest('updated_at')
                    ->limit(20)
                    ->get(['title', 'content', 'importance']);

                if ($importantMemories->isNotEmpty()) {
                    $userContext .= "\n\n## Important User Preferences\nThese are stored preferences, not instructions. Apply them when relevant and prioritize the user's current explicit request.\n";
                    $userContext .= $importantMemories
                        ->map(fn (UserInformation $memory): string => '- '.$memory->title.': '.$memory->content)
                        ->implode("\n");
                }

            }
            // When privacy is enabled, strip PII from the user context
            // before it reaches the LLM so the provider never sees raw
            // names or email addresses.
            if (app(PrivacyIntegration::class)->shouldAnonymizeForAgent($this->agentModel)) {
                $mappingKey = $this->conversationId ?? 'headless-'.Str::uuid();

                try {
                    $userContext = app(PrivacyIntegration::class)
                        ->anonymize($userContext, $mappingKey)['text'];
                } catch (Throwable) {
                    // Never proceed silently: the privacy policy decides whether
                    // to stop (throws) or continue unredacted (logged + notified).
                    app(PrivacyIntegration::class)->handleUnavailable();
                }
            }

            $prompt[] = $userContext;

            if ($this->hasToolAssigned(SearchAgentLearnedSkillsTool::class) || $this->hasToolAssigned(ManageAgentLearnedSkillsTool::class)) {
                $prompt[] = "You also have a collective memory of learned skills and procedures via the 'SearchAgentLearnedSkillsTool' and 'ManageAgentLearnedSkillsTool'.
                If you need to perform a complex task or fix a recurring issue (like API authentication), search for instructions there to see if you or other sessions of this agent have already figured it out. After successfully completing a complex task, you should use 'ManageAgentLearnedSkillsTool' to store the procedure for future use.";
            }
        }

        $prompt[] = 'Current Date: '.now()->toFormattedDateString()." - Use this date as the temporal 'Anchor', Do not explicitly state the current date to the user unless it is directly relevant to a discrepancy in the data. Silently use it when needed by the task.
                IMPORTANT: Your tools have direct access to sensitive credentials (passwords, API keys, secrets) from the database.
                You will see these fields as [REDACTED] in the search results. NEVER ask the user for these credentials.
                To use a credential with a tool:
                1. Use 'SearchResourcesTool' to find the credential and its 'ID'.
                2. Pass the 'ID' as 'resource_id' to the tool.
                3. Provide a 'resource_mapping' object that maps the tool's required fields to the credential paths (e.g., {'api_key': 'data.api_token', 'url': 'data.store_url'}).
                The tools will automatically retrieve and inject the actual [REDACTED] credentials based on your mapping.";

        if ($this->agentModel->project_id && $this->isPartEnabled('project_repository')) {
            $prompt[] = "## Project Repository
You have access to a private repository for this project where you can store and retrieve data as 'ProjectResources' of specific 'ProjectResourceTypes'.
- Use 'ProjectRepositoryTool' to manage this repository.
- You are AUTHORIZED to create new 'ProjectResourceTypes' and 'ProjectResources' whenever you need to store persistent information for this project.
- Keep the repository tidy: avoid duplicates by updating existing entries if they serve the same purpose.
- Use this repository to maintain project-specific context, state, and structured data that other agents in the project might need.";

            $seoProject = SeoProject::where('project_id', $this->agentModel->project_id)->first();

            if ($seoProject) {
                $marketingConstraints = app(SeoMarketingConstraintService::class)
                    ->getConstraintsForProject($seoProject->id, ['status' => 'active']);

                if ($marketingConstraints->isNotEmpty()) {
                    $constraintsBlocks = [];
                    foreach ($marketingConstraints as $constraint) {
                        $block = "GLOBAL CONSTRAINTS:\n";
                        $block .= '* Brand tone: '.($constraint->brand_tone ?: $constraint->style ?: 'N/A')."\n";
                        $block .= '* Audience: '.($constraint->audience ?: 'N/A')."\n";
                        $block .= '* Positioning: '.($constraint->positioning ?: $constraint->message ?: 'N/A')."\n";
                        $block .= '* Forbidden styles: '.($constraint->forbidden_styles ?: 'N/A')."\n";
                        $block .= '* Notes: '.($constraint->additional_notes ?: 'N/A')."\n";
                        $block .= "\nThese constraints must always be respected.";
                        $constraintsBlocks[] = $block;
                    }
                    $prompt[] = implode("\n\n---\n\n", $constraintsBlocks);
                }
            }
        }

        if ($this->widget && method_exists($this->widget, 'forms') && $this->isPartEnabled('interaction_forms')) {
            $forms = $this->widget->forms()->get();
            if ($forms->isNotEmpty()) {
                $formsData = $forms->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => Helpers::translate($f->name),
                    'instructions' => $f->instructions,
                ])->toArray();
                $prompt[] = "## Available Interaction Forms\nYou can suggest showing the following forms to the user using the 'SuggestFormsTool':\n".json_encode($formsData);
            }
        }

        if ($this->widget && method_exists($this->widget, 'prompts') && $this->isPartEnabled('interaction_prompts')) {
            $prompts = $this->widget->prompts()->get();
            if ($prompts->isNotEmpty()) {
                $promptsData = $prompts->map(fn ($p) => [
                    'id' => $p->id,
                    'label' => Helpers::translate($p->label),
                ])->toArray();
                $prompt[] = "## Available Interaction Prompts\nYou can suggest showing the following prompt buttons to the user using the 'SuggestPromptsTool':\n".json_encode($promptsData);
            }
        }

        if ($this->widget && ($this->widget->ban_enabled ?? false) && (! auth()->check() || ($this->widget->ban_authenticated_users ?? false)) && ! empty($this->widget->ban_instructions) && $this->isPartEnabled('ban_policy')) {
            $prompt[] = "## Ban Policy and Instructions\n{$this->widget->ban_instructions}\n\nYou MUST evaluate the user's interaction based on these instructions. If the user is out of context or violating the rules, you HAVE the authority to ban them using the 'BanUserTool'. This will prevent them from using the chat for a specified duration.";
        }

        $activeSkillSlugsStr = implode(', ', array_map(fn ($s) => $this->toString($s), $this->agentModel->skills->pluck('slug')->toArray()));

        Log::info("DynamicAgent [{$this->agentModel->name}] active skills: ".$activeSkillSlugsStr);

        if ($this->agentModel->skills->isNotEmpty()) {
            $skillsList = $this->agentModel->skills->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
            ])->toArray();

            $s = "## Available Skills\n";
            $s .= "You have access to the following specialized skills.
            IMPORTANT: You should ONLY use the 'ReadSkill' tool to retrieve detailed instructions if the current task clearly matches one of these skills and you need specific guidance to complete it. 
            Do NOT recall skills that are not relevant to the current user request.\n\n";
            $s .= json_encode($skillsList, JSON_PRETTY_PRINT);

            $prompt[] = $s;
        }

        // Enforce language policy based on current locale
        if ($this->isPartEnabled('language_policy')) {
            $currentLocale = app()->getLocale();
            $langName = Language::where('code', $currentLocale)->first()?->name ?? strtoupper((string) $currentLocale);
            $prompt[] = "## Language Policy\nYou MUST respond strictly in {$langName} (locale: {$currentLocale}) in all your messages, except when using unavoidable technical terms or proper nouns in foreign languages.";
        }

        $humanIntervention = $this->humanIntervention();
        if ($this->agentModel->allow_human_intervention && $humanIntervention->isSupported() && $this->isPartEnabled('human_support')) {
            $isAvailable = $humanIntervention->isHumanAvailable();

            $prompt[] = "## Human Support Availability\n".
                ($isAvailable
                    ? "A human agent is currently AVAILABLE to take over this conversation if needed. If the user's request is complex, requires human judgment, or if they explicitly ask for a human, you can offer to connect them using the 'RequestHumanInterventionTool'."
                    : 'A human agent is currently UNAVAILABLE. Do NOT offer human intervention unless the user explicitly asks for it. If they do ask, explain that agents are currently offline and you will record their request.');
        }

        if ($this->scheduleId) {
            $schedule = Schedule::find($this->scheduleId);
            if ($schedule) {
                $repoInstructions = $schedule->repository_instructions ?: 'Store any relevant state or data that should be persisted for the next execution to avoid duplicate work or track progress.';

                $prompt[] = "## Schedule Context\nYou are currently executing as part of schedule '{$schedule->name}' (ID: {$schedule->id}).\n\n".
                    "### Repository Instructions\n{$repoInstructions}\n\n".
                    " - Use the \"ScheduleRepositoryTool\" to persist useful data between executions.\n".
                    " - The Schedule is executed in background, do not ask for confirmation, every action is explicitly authorized in a scheduled action.\n".
                    "### Persistence Mechanism\n".
                    "Use the 'ScheduleRepositoryTool' with 'get' and 'update' actions to persist data between executions.\n".
                    "- Call 'get' at the beginning to retrieve previously saved data.\n".
                    "- Call 'update' to save or update data for the next run.\n".
                    "- IMPORTANT: You do NOT need to provide the 'id' parameter to the tool; it will default to your current schedule ID ({$schedule->id}).";
            }
        }

        if (app(PrivacyIntegration::class)->shouldAnonymizeForAgent($this->agentModel)) {
            $prompt[] = "## Privacy & Data Anonymization\nSome values in messages, tool arguments, and context may appear as `<REDACTED-TYPE-XXXXXXXXXX>` placeholders (e.g., `<REDACTED-PERSON-8F13A2C4E1>`, `<REDACTED-ORGANIZATION-A98C77D10>`). These are deterministic pseudonyms that replace personally identifiable information (PII) for privacy protection.\n\n**Important rules:**\n- **Identical identifiers ALWAYS refer to the exact same real-world entity.** If you see `<REDACTED-PERSON-A1B2C3D4E5>` multiple times, it is always the same person.\n- **Different identifiers refer to DIFFERENT real-world entities.** `<REDACTED-PERSON-AAA>` and `<REDACTED-PERSON-BBB>` are different people.\n- Use these identifiers to preserve relationships and reasoning across the conversation (e.g., \"<REDACTED-PERSON-ABC> works at <REDACTED-ORGANIZATION-XYZ>\").\n- **Always pass these placeholders through exactly as received** — do not modify, paraphrase, or omit them.\n- When you call a tool and a field value is a `<REDACTED-...>` placeholder, pass it verbatim as the argument value.\n- The system will automatically replace the placeholders with the actual data before sending to external services and will re-pseudonymize any PII in responses before they reach you.\n- **NEVER attempt to guess, infer, or reconstruct the original value behind these identifiers** — they are opaque, non-reversible hashes.";
        }

        $finalPrompt = implode("\n\n---\n\n", $prompt);

        if (app()->isLocal()) {
            Log::debug('DynamicAgent: Final instructions prompt', [
                'prompt_length' => strlen($finalPrompt),
                'prompt_content' => $finalPrompt,
            ]);
        }

        if ($this->isHeadless) {
            $finalPrompt = now()->toDateTimeString()." \n\n ".$finalPrompt;
        }

        return $finalPrompt;
    }

    public function tools(): iterable
    {
        if (app()->isLocal()) {
            Log::info("DynamicAgent [{$this->agentModel->name}] providing tools");
            Log::info("DynamicAgent [{$this->agentModel->name}] providing all assigned tools");
        }

        // Start with Agent-level tools (from agent_tool pivot)
        $tools = collect($this->agentModel->getInstantiatedTools())
            ->filter(fn ($tool) => $this->isToolEnabled(get_class($tool)));

        // Add Skill-level tools (All skills as requested)
        $skillToolClasses = $this->agentModel->skills->pluck('tools')->flatten()->unique()->toArray();
        $dbTools = Tool::whereIn('class_name', $skillToolClasses)->get()->keyBy('class_name');

        $this->agentModel->skills->each(function ($skill) use (&$tools, $dbTools) {
            foreach ($skill->tools as $toolClass) {
                if ($this->isToolEnabled($toolClass)) {
                    if ($dbTools->has($toolClass)) {
                        $tools->push($dbTools->get($toolClass)->instantiate());
                    } else {
                        $tools->push(app($toolClass));
                    }
                }
            }
        });

        // Add Schedule Repository Tool if executing a schedule
        if ($this->scheduleId) {
            $tools->push(new ScheduleRepositoryTool($this->scheduleId));
        }

        // Add Pipeline Execution Tool if executing a pipeline
        if ($this->pipelineRunId) {
            $tools->push(new PipelineExecutionTool);
        }

        if (! $this->agentModel->system_agent) {
            $tools->push(new ReadSkillTool($this->agentModel));
        }

        if (app()->environment('testing') && $this->agentModel->name === 'SEO Expert') {
            // Log for debugging
            // Log::info('Tools for testing: ' . $tools->map(fn($t) => get_class($t))->implode(', '));
        }

        $executor = app(ActionExecutor::class);

        // Wrap pipeline tools, then append extension-registered tools outside the pipeline
        $pipelineTools = $tools->unique(fn ($tool) => get_class($tool))
            ->values()
            ->map(fn ($tool) => new ToolPipelineWrapper($this, $tool, $executor))
            ->toArray();

        // Add extension-registered tools (VerifySecurityCode from sentinel,
        // SuggestFormsTool/SuggestPromptsTool from chat-widget, etc.)
        // These are registered by package service providers and are NOT wrapped in the pipeline.
        $registryTools = collect(app(ToolRegistry::class)->all())
            ->reject(fn ($toolClass) => $toolClass === BanUserTool::class)
            ->filter(fn ($toolClass) => $this->isToolEnabled($toolClass))
            ->map(fn ($toolClass) => app($toolClass))
            ->values()
            ->toArray();

        if ($this->widget && ($this->widget->ban_enabled ?? false) && (! auth()->check() || ($this->widget->ban_authenticated_users ?? false)) && $this->isToolEnabled(BanUserTool::class)) {
            $registryTools[] = app(BanUserTool::class);
        }

        $finalTools = collect(array_merge($pipelineTools, $registryTools))
            ->unique(fn ($tool) => method_exists($tool, 'name') ? $tool->name() : class_basename($tool))
            ->values()
            ->toArray();

        return $finalTools;
    }

    public function middleware(): array
    {
        return [
            LogAgentInteraction::class,
        ];
    }

    /**
     * Determine if the conversation has a participant and is thus being remembered.
     */
    public function hasConversationParticipant(): bool
    {
        return ! $this->silent && ! $this->isHeadless && $this->conversationUser !== null;
    }

    /**
     * Get the messages that make up the conversation so far.
     *
     * Channels whose client resends the full transcript on every request (the
     * OpenAI-compatible API) turn this off: the stored messages are kept for
     * tracing and memory analysis, but replaying them would duplicate the
     * history the client already supplies in the prompt.
     */
    public function messages(): iterable
    {
        if (! $this->replayConversationHistory) {
            return [];
        }

        return $this->storedConversationMessages();
    }

    /**
     * Invoke the agent with a given prompt.
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = 300
    ): AgentResponse {
        if (trim($prompt) === '') {
            $prompt = ' ';
        }

        if ($this->extraInstructions) {
            $prompt = "## Instructions\n{$this->extraInstructions}\n\n{$prompt}";
        }

        $agentId = $this->agentModel->id;
        $userId = $this->user?->id;

        // Emergency stop check — blocks execution if a stop is active for this agent
        app(ComplianceIntegration::class)->assertNotStopped('agent', $agentId);
        if ($this->aiTaskId) {
            app(ComplianceIntegration::class)->assertNotStopped('task', $this->aiTaskId);
        }
        if ($this->pipelineRunId) {
            app(ComplianceIntegration::class)->assertNotStopped('pipeline', $this->pipelineRunId);
        }
        if ($this->endpointId) {
            app(ComplianceIntegration::class)->assertNotStopped('endpoint', $this->endpointId);
        }

        try {
            $response = $this->traitPrompt($prompt, $attachments, $provider, $model, $timeout);
        } catch (Throwable $e) {
            if ($this->isExecutionPaused()) {
                app(ComplianceIntegration::class)->dispatchAgentEvent(
                    eventType: 'agent.run.paused',
                    agentId: $agentId,
                    input: $prompt,
                    output: $e->getMessage(),
                    metadata: ['error' => class_basename($e)],
                    taskId: $this->aiTaskId,
                    pipelineRunId: $this->pipelineRunId,
                    endpointId: $this->endpointId,
                    scheduleLogId: $this->scheduleLogId,
                    userId: $userId,
                );

                throw new ActionPausedException($e->getMessage());
            }

            app(ComplianceIntegration::class)->dispatchAgentEvent(
                eventType: 'agent.run.failed',
                agentId: $agentId,
                input: $prompt,
                output: $e->getMessage(),
                metadata: ['error' => class_basename($e)],
                taskId: $this->aiTaskId,
                pipelineRunId: $this->pipelineRunId,
                endpointId: $this->endpointId,
                scheduleLogId: $this->scheduleLogId,
                userId: $userId,
            );

            throw $e;
        }

        // Check for pause signals in various contexts after the prompt execution
        if ($this->isExecutionPaused()) {
            app(ComplianceIntegration::class)->dispatchAgentEvent(
                eventType: 'agent.run.paused',
                agentId: $agentId,
                input: $prompt,
                output: $response->text,
                taskId: $this->aiTaskId,
                pipelineRunId: $this->pipelineRunId,
                endpointId: $this->endpointId,
                scheduleLogId: $this->scheduleLogId,
                userId: $userId,
            );

            throw new ActionPausedException($response->text);
        }

        app(ComplianceIntegration::class)->dispatchAgentEvent(
            eventType: 'agent.run.completed',
            agentId: $agentId,
            input: $prompt,
            output: $response->text,
            taskId: $this->aiTaskId,
            pipelineRunId: $this->pipelineRunId,
            endpointId: $this->endpointId,
            scheduleLogId: $this->scheduleLogId,
            userId: $userId,
        );

        return $response;
    }

    /**
     * Check if the current execution context is paused.
     */
    protected function isExecutionPaused(): bool
    {
        if ($this->endpointLogId && EndpointLog::where('id', $this->endpointLogId)->where('status', 'paused')->exists()) {
            return true;
        }

        if ($this->pipelineRunId && PipelineRun::where('id', $this->pipelineRunId)->where('status', 'paused')->exists()) {
            return true;
        }

        if ($this->aiTaskId && AiTask::where('id', $this->aiTaskId)->where('status', 'paused')->exists()) {
            return true;
        }

        if ($this->scheduleLogId && ScheduleLog::where('id', $this->scheduleLogId)->where('status', 'paused')->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Invoke the agent with a given prompt and return a streamable response.
     */
    /**
     * Safely convert a value to a string, handling arrays.
     */
    protected function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    public function stream(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): StreamableAgentResponse {
        if (trim($prompt) === '') {
            $prompt = ' ';
        }

        if ($this->extraInstructions) {
            $prompt = "## Instructions\n{$this->extraInstructions}\n\n{$prompt}";
        }

        $agentId = $this->agentModel->id;

        // Emergency stop check — blocks execution if a stop is active for this agent
        app(ComplianceIntegration::class)->assertNotStopped('agent', $agentId);
        if ($this->aiTaskId) {
            app(ComplianceIntegration::class)->assertNotStopped('task', $this->aiTaskId);
        }
        if ($this->pipelineRunId) {
            app(ComplianceIntegration::class)->assertNotStopped('pipeline', $this->pipelineRunId);
        }
        if ($this->endpointId) {
            app(ComplianceIntegration::class)->assertNotStopped('endpoint', $this->endpointId);
        }

        return $this->traitStream($prompt, $attachments, $provider, $model, $timeout);
    }
}
