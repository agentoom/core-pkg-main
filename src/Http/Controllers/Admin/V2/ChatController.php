<?php

namespace Agentoom\Core\Http\Controllers\Admin\V2;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Jobs\ProcessAgentChat;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentConversation;
use Agentoom\Core\Models\AgentConversationMessage;
use Agentoom\Core\Models\AgentConversationShare;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Storage\AgentAwareConversationStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $conversationId = $request->query('conversationId');
        $selectedAgentId = $this->resolveSelectedAgentId($conversationId);
        $search = $request->query('search', '');

        $aiDisclosureText = $this->resolveAiDisclosureText();

        $aiDisclosureVersion = Configuration::getValue('ai_disclosure_version', '');
        if (empty($aiDisclosureVersion)) {
            $aiDisclosureVersion = '1.0.0';
        }

        return Inertia::render('Admin/V2/DashboardChat', [
            'availableAgents' => $this->getAvailableAgents(),
            'recentConversations' => $this->getRecentConversations($search),
            'aiDisclosureEnabled' => (bool) Configuration::getValue('ai_disclosure_enabled', false),
            'aiDisclosureText' => $aiDisclosureText,
            'aiDisclosureVersion' => $aiDisclosureVersion,
            'search' => $search,
            'conversationId' => $conversationId,
            'selectedAgentId' => $selectedAgentId,
            'initialMessages' => $conversationId ? $this->getMessages($conversationId) : [],
            'translations' => $this->getTranslations(),
            'hasCredits' => app(AiCreditService::class)->hasSufficientCredits(auth()->user()),
            'userCredits' => auth()->user()->ai_credits ?? 0,
            'shareableUsers' => $this->getShareableUsers(),
            'currentConversationShares' => $conversationId ? $this->getConversationShares($conversationId) : [],
            'privacyEnabled' => (bool) app(PrivacyIntegration::class)->isEnabled(),
        ]);
    }

    public function embed(Request $request)
    {
        Inertia::setRootView('agentoom-core::inertia-embed');

        $conversationId = $request->query('conversationId');
        $selectedAgentId = $this->resolveSelectedAgentId($conversationId);
        $search = $request->query('search', '');

        $aiDisclosureText = $this->resolveAiDisclosureText();

        $aiDisclosureVersion = Configuration::getValue('ai_disclosure_version', '');
        if (empty($aiDisclosureVersion)) {
            $aiDisclosureVersion = '1.0.0';
        }

        return Inertia::render('Admin/V2/DashboardChat', [
            'availableAgents' => $this->getAvailableAgents(),
            'recentConversations' => $this->getRecentConversations($search),
            'aiDisclosureEnabled' => (bool) Configuration::getValue('ai_disclosure_enabled', false),
            'aiDisclosureText' => $aiDisclosureText,
            'aiDisclosureVersion' => $aiDisclosureVersion,
            'search' => $search,
            'conversationId' => $conversationId,
            'selectedAgentId' => $selectedAgentId,
            'initialMessages' => $conversationId ? $this->getMessages($conversationId) : [],
            'translations' => $this->getTranslations(),
            'hasCredits' => app(AiCreditService::class)->hasSufficientCredits(auth()->user()),
            'userCredits' => auth()->user()->ai_credits ?? 0,
            'shareableUsers' => $this->getShareableUsers(),
            'currentConversationShares' => $conversationId ? $this->getConversationShares($conversationId) : [],
            'privacyEnabled' => (bool) app(PrivacyIntegration::class)->isEnabled(),
            'isEmbedded' => true,
        ]);
    }

    public function selectAgent(Request $request)
    {
        $request->validate([
            'agentId' => ['required', Rule::exists('agents', 'id')->where('system_agent', 0)],
        ]);

        $agentId = $request->input('agentId');
        $agent = Agent::findOrFail($agentId);

        return response()->json([
            'agent' => $agent,
        ]);
    }

    public function newChat()
    {
        return redirect()->route('admin.v2.chat.index');
    }

    public function send(Request $request)
    {
        $request->validate([
            'prompt' => 'nullable|required_without:attachments|string|max:10000',
            'agentId' => ['required', Rule::exists('agents', 'id')->where('system_agent', 0)],
            'conversationId' => 'nullable|string',
            'anonymize' => 'nullable|boolean',
            'attachments' => 'array',
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt,json,xml,png,jpg,jpeg,webp,webm,mp3,wav,m4a,ogg'],
        ]);

        $user = auth()->user();
        if (! app(AiCreditService::class)->hasSufficientCredits($user)) {
            return response()->json(['error' => __('agentoom-core::front.credits_exhausted')], 403);
        }

        $agentId = $request->input('agentId');
        $conversationId = $request->input('conversationId');
        $prompt = trim((string) $request->input('prompt', ''));

        if (! $conversationId || ! AgentConversation::whereKey($conversationId)->exists()) {
            $conversationId = app(AgentAwareConversationStore::class)->storeConversation($user->id, 'New Chat', (int) $agentId);
        }

        $uploadedAttachments = $request->file('attachments', []);
        $allAttachments = [];

        foreach ($uploadedAttachments as $attachment) {
            if (! $attachment) {
                continue;
            }

            $mimeType = $attachment->getMimeType();
            $extension = strtolower($attachment->getClientOriginalExtension());
            $audioExtensions = ['webm', 'mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'];

            $isImage = is_string($mimeType) && str_starts_with($mimeType, 'image/');
            $isAudio = (is_string($mimeType) && str_starts_with($mimeType, 'audio/')) || in_array($extension, $audioExtensions);
            $path = $attachment->store('attachments', 'public');

            $allAttachments[] = [
                'name' => $attachment->getClientOriginalName(),
                'path' => $path,
                'real_path' => storage_path('app/public/'.$path),
                'mime_type' => $mimeType,
                'type' => $isImage ? 'image' : ($isAudio ? 'audio' : 'document'),
            ];
        }

        if ($prompt === '' && count($allAttachments) > 0) {
            $hasAudio = collect($allAttachments)->contains('type', 'audio');
            $prompt = $hasAudio ? '' : 'I have attached some files for your review.';
        }

        $messageId = app(AgentAwareConversationStore::class)->ensureUserMessageStored(
            conversationId: $conversationId,
            userId: $user->id,
            agent: DynamicAgent::class,
            content: $prompt,
            attachments: $allAttachments,
            privacyAnonymized: $request->boolean('anonymize', true)
        );

        // Read the stored anonymized/highlighted copies so the frontend can
        // show the anonymization state (and toggle) immediately.
        $message = AgentConversationMessage::find($messageId);

        return response()->json([
            'conversationId' => $conversationId,
            'messageId' => $messageId,
            'anonymizedContent' => $message?->anonymized_content,
            'highlightedContent' => $message ? $this->highlightedHtmlFor($message) : null,
        ]);
    }

    public function ask(Request $request, string $conversationId)
    {
        $request->validate([
            'agentId' => ['required', Rule::exists('agents', 'id')->where('system_agent', 0)],
        ]);

        $agentId = $request->input('agentId');

        ProcessAgentChat::dispatch(
            $conversationId,
            (int) $agentId,
            (int) auth()->id(),
            locale: app()->getLocale()
        );

        return response()->json(['status' => 'processing']);
    }

    public function stop(string $conversationId)
    {
        Cache::put("stop-chat-{$conversationId}", true, now()->addMinutes(5));

        return response()->json(['status' => 'stop-requested']);
    }

    public function destroy(string $id)
    {
        AgentConversation::where('id', $id)->where('user_id', auth()->id())->delete();

        return redirect()->back();
    }

    public function share(Request $request, string $conversationId)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        $conversation = AgentConversation::where('id', $conversationId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $userId = auth()->id();
        $userIds = collect($request->input('user_ids'))->reject(fn ($id) => (int) $id === (int) $userId);

        foreach ($userIds as $sharedWithUserId) {
            AgentConversationShare::firstOrCreate(
                ['conversation_id' => $conversation->id, 'shared_with_user_id' => $sharedWithUserId],
                ['shared_by_user_id' => $userId]
            );
        }

        $conversation->update(['is_private' => false]);

        return response()->json([
            'shares' => $this->getConversationShares($conversationId),
        ]);
    }

    public function unshare(Request $request, string $conversationId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = AgentConversation::where('id', $conversationId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        AgentConversationShare::where('conversation_id', $conversation->id)
            ->where('shared_with_user_id', $request->input('user_id'))
            ->delete();

        if ($conversation->shares()->count() === 0) {
            $conversation->update(['is_private' => true]);
        }

        return response()->json([
            'shares' => $this->getConversationShares($conversationId),
        ]);
    }

    private function getAvailableAgents()
    {
        return Agent::where('is_active', true)
            ->where('system_agent', false)
            ->orderBy('name')
            ->get(['id', 'name', 'instructions', 'is_active', 'anonymization_enabled']);
    }

    private function getRecentConversations(string $search = '')
    {
        $userId = auth()->id();

        $ownConversations = AgentConversation::with('agent:id,name')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('session_id', 'not like', 'pipeline_run_%')
                    ->where('session_id', 'not like', 'schedule_log_%')
                    ->where('session_id', 'not like', 'ai_task_%')
                    ->where('session_id', 'not like', 'endpoint_log_%');
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('messages', fn ($m) => $m->where('content', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($conv) => [
                'id' => $conv->id,
                'title' => $conv->title,
                'agent_id' => $conv->agent_id,
                'agent_name' => $conv->agent?->name,
                'updated_at' => $conv->updated_at,
                'updated_at_diff' => $conv->updated_at->diffForHumans(),
                'is_shared_with_me' => false,
                'shared_by' => null,
                'is_private' => (bool) $conv->is_private,
            ]);

        $sharedConversations = AgentConversation::with('agent:id,name')
            ->whereHas('shares', fn ($q) => $q->where('shared_with_user_id', $userId))
            ->with(['shares' => fn ($q) => $q->where('shared_with_user_id', $userId)->with('sharedBy:id,name,email')])
            ->where(function ($q) {
                $q->where('session_id', 'not like', 'pipeline_run_%')
                    ->where('session_id', 'not like', 'schedule_log_%')
                    ->where('session_id', 'not like', 'ai_task_%')
                    ->where('session_id', 'not like', 'endpoint_log_%');
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('messages', fn ($m) => $m->where('content', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($conv) {
                $share = $conv->shares->first();

                return [
                    'id' => $conv->id,
                    'title' => $conv->title,
                    'agent_id' => $conv->agent_id,
                    'agent_name' => $conv->agent?->name,
                    'updated_at' => $conv->updated_at,
                    'updated_at_diff' => $conv->updated_at->diffForHumans(),
                    'is_shared_with_me' => true,
                    'shared_by' => $share?->sharedBy ? [
                        'id' => $share->sharedBy->id,
                        'name' => $share->sharedBy->name,
                        'email' => $share->sharedBy->email,
                    ] : null,
                    'is_private' => false,
                ];
            });

        return $ownConversations->concat($sharedConversations)
            ->sortByDesc('updated_at')
            ->take(10)
            ->values();
    }

    private function getMessages(string $conversationId)
    {
        $privacyEnabled = (bool) app(PrivacyIntegration::class)->isEnabled();

        return AgentConversationMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => $this->presentMessage($msg, $privacyEnabled))
            ->values();
    }

    /**
     * Shape a stored message for the dashboard chat.
     *
     * `content` stays plain text (the frontend renders markdown) while
     * `highlighted_content` is pre-rendered HTML in which every value that was
     * anonymized before reaching the model is wrapped in a highlight span that
     * carries the exact placeholder the model saw.
     *
     * @return array<string, mixed>
     */
    private function presentMessage(AgentConversationMessage $msg, bool $privacyEnabled): array
    {
        return [
            'id' => $msg->id,
            'role' => $msg->role,
            'content' => $msg->content,
            'highlighted_content' => $privacyEnabled ? $this->highlightedHtmlFor($msg) : null,
            'anonymized_content' => $privacyEnabled ? $msg->anonymized_content : null,
            'attachments' => $msg->attachments ?? [],
            'created_at' => $msg->created_at->format('H:i A'),
        ];
    }

    /**
     * Build the HTML rendering of a message with anonymized values highlighted.
     *
     * Uses the stored highlighted copy when present; legacy rows fall back to
     * restoring the stored anonymized copy while its mapping is still cached.
     */
    private function highlightedHtmlFor(AgentConversationMessage $msg): ?string
    {
        $highlighted = $msg->highlighted_content;

        if (empty($highlighted) && ! empty($msg->anonymized_content)) {
            try {
                $highlighted = app(PrivacyIntegration::class)
                    ->restoreHighlighted($msg->anonymized_content, $msg->conversation_id);
            } catch (\Throwable) {
                return null;
            }
        }

        if (empty($highlighted)) {
            return null;
        }

        return Str::markdown($highlighted, ['html_input' => 'allow']);
    }

    /**
     * Fetch a single message with highlighted content for the final broadcast fade-in.
     *
     * Called by the frontend when it receives an is_final signal from Reverb.
     * Returns the complete message with highlighted PII spans, bypassing Pusher
     * payload limits entirely.
     */
    public function fetchMessage(string $messageId)
    {
        $privacyEnabled = (bool) app(PrivacyIntegration::class)->isEnabled();

        $msg = AgentConversationMessage::find($messageId);

        if (! $msg) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        return response()->json($this->presentMessage($msg, $privacyEnabled));
    }

    /**
     * Resolve the AI disclosure text for the current locale.
     *
     * Reads the per-language JSON map from Configuration, picks the text
     * matching the current locale, and falls back to the translated default.
     */
    private function resolveAiDisclosureText(): string
    {
        $raw = Configuration::getValue('ai_disclosure_text', '');

        if (empty($raw)) {
            return __('agentoom-core::admin.configs.ai_disclosure_default_text');
        }

        // getValue auto-decodes JSON, so $raw may already be an array
        $map = is_array($raw) ? $raw : json_decode($raw, true);

        if (! is_array($map)) {
            // Legacy single string
            return $raw;
        }

        $locale = app()->getLocale();

        return $map[$locale]
            ?? $map['en']
            ?? reset($map)
            ?? __('agentoom-core::admin.configs.ai_disclosure_default_text');
    }

    private function resolveSelectedAgentId(?string $conversationId): ?int
    {
        if (! $conversationId) {
            return null;
        }

        $userId = auth()->id();

        return AgentConversation::query()
            ->where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('shares', fn ($s) => $s->where('shared_with_user_id', $userId));
            })
            ->value('agent_id');
    }

    private function getShareableUsers(): array
    {
        return User::where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->toArray();
    }

    private function getConversationShares(string $conversationId): array
    {
        $userId = auth()->id();

        $conversation = AgentConversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if (! $conversation) {
            return [];
        }

        return AgentConversationShare::where('conversation_id', $conversationId)
            ->with('sharedWith:id,name,email')
            ->get()
            ->map(fn ($share) => [
                'user_id' => $share->shared_with_user_id,
                'name' => $share->sharedWith?->name,
                'email' => $share->sharedWith?->email,
            ])
            ->values()
            ->toArray();
    }

    private function getTranslations()
    {
        return [
            'new_chat' => __('agentoom-core::admin.chat_view.new_chat'),
            'history' => __('agentoom-core::admin.chat_view.history'),
            'select_agent' => __('agentoom-core::admin.chat_view.select_agent'),
            'disclaimer' => __('agentoom-core::admin.chat_view.disclaimer'),
            'type_message' => __('agentoom-core::admin.chat_view.type_message'),
            'share' => __('agentoom-core::admin.chat_view.share'),
            'share_conversation' => __('agentoom-core::admin.chat_view.share_conversation'),
            'shared_with' => __('agentoom-core::admin.chat_view.shared_with'),
            'add_people' => __('agentoom-core::admin.chat_view.add_people'),
            'search_users_placeholder' => __('agentoom-core::admin.chat_view.search_users_placeholder'),
            'no_users_found' => __('agentoom-core::admin.chat_view.no_users_found'),
            'all_users_have_access' => __('agentoom-core::admin.chat_view.all_users_have_access'),
            'delete_conversation_confirm' => __('agentoom-core::admin.chat_view.delete_conversation_confirm'),
            'no_history' => __('agentoom-core::admin.chat_view.no_history'),
            'stop' => __('agentoom-core::admin.chat_view.stop'),
            'sending_message' => __('agentoom-core::admin.chat_view.sending_message'),
            'agent_thinking' => __('agentoom-core::admin.chat_view.agent_thinking'),
            'attach_file' => __('agentoom-core::admin.chat_view.attach_file'),
            'record_voice' => __('agentoom-core::admin.chat_view.record_voice'),
            'stop_recording' => __('agentoom-core::admin.chat_view.stop_recording'),
            'recording' => __('agentoom-core::admin.chat_view.recording'),
            'anonymize' => __('agentoom-core::admin.chat_view.anonymize'),
            'anonymize_off_warning' => __('agentoom-core::admin.chat_view.anonymize_off_warning'),
            'view_anonymized' => __('agentoom-core::admin.chat_view.view_anonymized'),
            'view_clear' => __('agentoom-core::admin.chat_view.view_clear'),
            'credits_exhausted' => __('agentoom-core::admin.chat_view.credits_exhausted'),
            'untitled_chat' => __('agentoom-core::admin.chat_view.untitled_chat'),
            'search_conversations' => __('agentoom-core::admin.chat_view.search_conversations'),
            'deleted_agent' => __('agentoom-core::admin.chat_view.deleted_agent'),
        ];
    }
}
