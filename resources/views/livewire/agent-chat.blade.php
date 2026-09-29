@php
    $settings = $widget?->settings ?? [];
    $headerUseGradient = $settings['header_use_gradient'] ?? true;
    $headerBgColor = $settings['header_bg_color'] ?? '#7e22ce';
    $headerTextColor = $settings['header_text_color'] ?? '#ffffff';
    $headerGradientStart = $settings['header_gradient_start'] ?? '#7e22ce';
    $headerGradientEnd = $settings['header_gradient_end'] ?? '#9333ea';
    $chatBg = $settings['chat_bg'] ?? '#ffffff';
    $userMsgBg = $settings['user_msg_bg'] ?? '#E3EFFF';
    $userMsgText = $settings['user_msg_text'] ?? '#18181b';
    $agentMsgBg = $settings['agent_msg_bg'] ?? '#f9fafb';
    $agentMsgText = $settings['agent_msg_text'] ?? '#18181b';
    $btnBg = $settings['btn_bg'] ?? '#c026d3';
    $btnColor = $settings['btn_color'] ?? '#ffffff';
    $inputBg = $settings['input_bg'] ?? '#ffffff';
    $inputText = $settings['input_text'] ?? '#18181b';

    // Dark settings
    $darkHeaderBgColor = $settings['dark_header_bg_color'] ?? '#18181b';
    $darkHeaderTextColor = $settings['dark_header_text_color'] ?? '#ffffff';
    $darkHeaderGradientStart = $settings['dark_header_gradient_start'] ?? '#18181b';
    $darkHeaderGradientEnd = $settings['dark_header_gradient_end'] ?? '#27272a';
    $darkChatBg = $settings['dark_chat_bg'] ?? '#09090b';
    $darkUserMsgBg = $settings['dark_user_msg_bg'] ?? '#27272a';
    $darkUserMsgText = $settings['dark_user_msg_text'] ?? '#ffffff';
    $darkAgentMsgBg = $settings['dark_agent_msg_bg'] ?? '#18181b';
    $darkAgentMsgText = $settings['dark_agent_msg_text'] ?? '#ffffff';
    $darkBtnBg = $settings['dark_btn_bg'] ?? $btnBg;
    $darkBtnColor = $settings['dark_btn_color'] ?? $btnColor;
    $darkInputBg = $settings['dark_input_bg'] ?? '#18181b';
    $darkInputText = $settings['dark_input_text'] ?? '#ffffff';
@endphp

<div class="flex flex-col h-screen max-w-3xl mx-auto p-4 widget-container">
    <style>
        .widget-container {
            --widget-header-bg: {{ $headerUseGradient ? 'linear-gradient(to right, ' . $headerGradientStart . ', ' . $headerGradientEnd . ')' : $headerBgColor }};
            --widget-header-text: {{ $headerTextColor }};
            --widget-chat-bg: {{ $chatBg }};
            --widget-user-msg-bg: {{ $userMsgBg }};
            --widget-user-msg-text: {{ $userMsgText }};
            --widget-agent-msg-bg: {{ $agentMsgBg }};
            --widget-agent-msg-text: {{ $agentMsgText }};
            --widget-btn-bg: {{ $btnBg }};
            --widget-btn-color: {{ $btnColor }};
            --widget-input-bg: {{ $inputBg }};
            --widget-input-text: {{ $inputText }};
        }
        .dark .widget-container {
            --widget-header-bg: {{ $headerUseGradient ? 'linear-gradient(to right, ' . $darkHeaderGradientStart . ', ' . $darkHeaderGradientEnd . ')' : $darkHeaderBgColor }};
            --widget-header-text: {{ $darkHeaderTextColor }};
            --widget-chat-bg: {{ $darkChatBg }};
            --widget-user-msg-bg: {{ $darkUserMsgBg }};
            --widget-user-msg-text: {{ $darkUserMsgText }};
            --widget-agent-msg-bg: {{ $darkAgentMsgBg }};
            --widget-agent-msg-text: {{ $darkAgentMsgText }};
            --widget-btn-bg: {{ $darkBtnBg }};
            --widget-btn-color: {{ $darkBtnColor }};
            --widget-input-bg: {{ $darkInputBg }};
            --widget-input-text: {{ $darkInputText }};
        }
    </style>
    <div class="mb-6 flex items-center justify-between p-6 rounded-[32px] transition-colors duration-300 shadow-sm border border-black/5"
         style="background: var(--widget-header-bg); color: var(--widget-header-text) !important">
        <div>
            <h2 class="text-lg font-bold" style="color: inherit !important">{{ $agent->chatbot_title ?: $agent->name }}</h2>
            @if ($agent->chatbot_description)
                <p class="text-sm opacity-80" style="color: inherit !important">{{ $agent->chatbot_description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="button" x-data x-on:click="$flux.dark = ! $flux.dark" class="opacity-70 hover:opacity-100 transition-opacity" title="{{ __('agentoom-core::front.toggle_theme') }}">
                <flux:icon.sun class="size-5 dark:hidden" />
                <flux:icon.moon class="size-5 hidden dark:block" />
            </button>
        </div>
    </div>
    <div
        id="chat-container"
        class="flex-1 overflow-y-auto space-y-8 mb-6 rounded-[32px] border border-black/5 p-8 shadow-sm transition-colors duration-300"
        style="background-color: var(--widget-chat-bg)"
    >
        @foreach ($messages as $msg)
            <div @class(['flex w-full', 'justify-end' => $msg['role'] === 'user', 'justify-start' => $msg['role'] !== 'user'])>
                <div @class(['flex flex-col max-w-[80%]', 'items-end' => $msg['role'] === 'user', 'items-start' => $msg['role'] !== 'user'])>
                    <div class="flex items-center gap-2 mb-1 px-1">
                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            @if($msg['role'] === 'user')
                                {{ auth()->user()->name ?? __('agentoom-core::front.you') }}
                            @else
                                {{ $msg['agent_name'] ?? $agent->name }}
                            @endif
                        </span>
                        <span class="text-[10px] text-zinc-400">• {{ $msg['created_at'] ?? now()->format('H:i A') }}</span>
                    </div>

                    @php
                        $bStyle = $settings['bubble_style'] ?? 'modern';
                        $isUser = $msg['role'] === 'user';
                        $radiusClass = match($bStyle) {
                            'modern' => $isUser ? 'rounded-[28px] rounded-tr-none' : 'rounded-[28px] rounded-tl-none',
                            'rounded' => 'rounded-[32px]',
                            'flat' => 'rounded-xl',
                            'sharp' => 'rounded-none',
                            default => 'rounded-[28px]',
                        };
                    @endphp

                    <div
                        class="p-4 text-sm transition-all duration-200 shadow-sm border border-black/5 {{ $radiusClass }}"
                        style="@if($msg['role'] === 'user') background-color: var(--widget-user-msg-bg); color: var(--widget-user-msg-text); @else background-color: var(--widget-agent-msg-bg); color: var(--widget-agent-msg-text); @endif"
                    >
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            @if (isset($msg['thinking']) && $msg['thinking'])
                                <flux:accordion transition class="mb-3 border-b border-black/5 dark:border-white/5 pb-3">
                                    <flux:accordion.item :expanded="false" wire:key="msg-thinking-{{ $loop->index }}">
                                        <flux:accordion.heading>
                                            <div class="flex items-center gap-2">
                                                <flux:icon.brain class="size-4 opacity-70" />
                                                <span class="text-[11px] font-medium uppercase tracking-wider opacity-70">{{ __('agentoom-core::front.thinking') }}</span>
                                            </div>
                                        </flux:accordion.heading>

                                        <flux:accordion.content>
                                            <div class="text-[11px] italic opacity-70 leading-relaxed">
                                                {!! $msg['thinking'] !!}
                                            </div>
                                        </flux:accordion.content>
                                    </flux:accordion.item>
                                </flux:accordion>
                            @endif

                            {!! $msg['content'] !!}
                        </div>
                    </div>

                    @if ($msg['role'] === 'user')
                        <div class="flex items-center gap-1 mt-1 px-1">
                            <flux:icon.check-badge class="size-3 text-blue-500" variant="solid" />
                            <span class="text-[10px] text-zinc-400">{{ $msg['created_at'] ?? now()->format('H:i A') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        @if ($streaming)
            <div class="flex w-full justify-start">
                <div class="flex flex-col max-w-[80%] items-start">
                    <div class="flex items-center gap-2 mb-1 px-1">
                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $agent->name }}</span>
                        <span class="text-[10px] text-zinc-400">• {{ __('agentoom-core::front.just_now') }}</span>
                    </div>

                    @php
                        $bStyle = $settings['bubble_style'] ?? 'modern';
                        $radiusClass = match($bStyle) {
                            'modern' => 'rounded-[28px] rounded-tl-none',
                            'rounded' => 'rounded-[32px]',
                            'flat' => 'rounded-xl',
                            'sharp' => 'rounded-none',
                            default => 'rounded-[28px]',
                        };
                    @endphp

                    <div class="p-4 text-sm {{ $radiusClass }} shadow-sm border border-black/5 transition-all duration-200"
                         style="background-color: var(--widget-agent-msg-bg); color: var(--widget-agent-msg-text);">
                        <div wire:stream="streamedContent" class="prose prose-sm dark:prose-invert max-w-none">
                            {!! $streamedContent !!}
                        </div>
                        <flux:icon.loading class="mt-2" />
                    </div>
                </div>
            </div>
        @endif
    </div>

    <form
        wire:submit="send"
        class="mt-2 {{ ! $hasCredits ? 'opacity-60 pointer-events-none' : '' }}"
        x-data
        @keydown.enter="if (!$event.shiftKey && ['TEXTAREA','INPUT'].includes($event.target.tagName)) { $event.preventDefault(); $wire.send(); }"
    >
        <flux:composer
            wire:model="prompt"
            rows="3"
            :label="__('agentoom-core::front.prompt')"
            label:sr-only
            placeholder="{{ $hasCredits ? __('agentoom-core::front.ask_me_anything') : __('agentoom-core::front.credits_exhausted') }}"
            :disabled="! $hasCredits"
            class="!bg-[var(--widget-input-bg)] !text-[var(--widget-input-text)] border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden"
        >
            <x-slot name="actionsTrailing">
                <flux:button type="submit" size="sm" wire:loading.attr="disabled" :disabled="! $hasCredits"
                             style="background-color: var(--widget-btn-bg); color: var(--widget-btn-color)">
                    <flux:icon icon="send" variant="mini" />
                </flux:button>
            </x-slot>
        </flux:composer>
    </form>

    <script>
        const setupScroll = () => {
            const container = document.getElementById('chat-container');
            if (!container) return;

            const scrollToBottom = () => {
                container.scrollTop = container.scrollHeight;
            };

            const observer = new MutationObserver(() => {
                scrollToBottom();
            });

            observer.observe(container, {
                childList: true,
                characterData: true,
                subtree: true
            });

            scrollToBottom();

            window.addEventListener('scroll-bottom', () => {
                scrollToBottom();
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupScroll);
        } else {
            setupScroll();
        }

        document.addEventListener('livewire:navigated', setupScroll);
    </script>
</div>
