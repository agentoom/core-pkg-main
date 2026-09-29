<flux:header class="border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <a href="{{ route('home') }}" class="flex items-center gap-2 px-2">
        <flux:icon.brain variant="mini" class="size-6 text-indigo-600" />
        <span class="text-lg font-bold text-zinc-900 dark:text-white">{{ config('app.name') }}</span>
    </a>

    <flux:spacer />

    <flux:navbar class="-mb-px">
        <flux:dropdown>
            <flux:navbar.item icon-trailing="chevron-down">
                <flux:icon.arrow-down-tray variant="mini" class="mr-2" />
                {{ __('agentoom-core::admin.common.export') }}
            </flux:navbar.item>

            <flux:navmenu>
                <flux:navmenu.item href="{{ route('docs.export', ['locale' => app()->getLocale(), 'format' => 'md']) }}">
                    Markdown (.md)
                </flux:navmenu.item>
                <flux:navmenu.item href="{{ route('docs.export', ['locale' => app()->getLocale(), 'format' => 'pdf']) }}">
                    PDF (Print-Ready)
                </flux:navmenu.item>
            </flux:navmenu>
        </flux:dropdown>

        <flux:dropdown>
            <flux:navbar.item icon-trailing="chevron-down">
                {{ strtoupper(app()->getLocale()) }}
            </flux:navbar.item>

            <flux:navmenu>
                @foreach($languages as $language)
                    <flux:navmenu.item
                        href="{{ route(request()->route()->getName(), array_merge(request()->route()->parameters(), ['locale' => $language->code])) }}"
                    >
                        {{ $language->name }} ({{ strtoupper($language->code) }})
                    </flux:navmenu.item>
                @endforeach
            </flux:navmenu>
        </flux:dropdown>

        <flux:navbar.item href="{{ route('home') }}">{{ __('agentoom-core::admin.dashboard') }}</flux:navbar.item>
    </flux:navbar>
</flux:header>
