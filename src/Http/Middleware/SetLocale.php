<?php

namespace Agentoom\Core\Http\Middleware;

use Agentoom\Core\Models\Language;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $default = config('app.locale');
        $admin = session('admin_locale');
        $chat = session('chat_locale');

        $code = $admin ?: ($chat ?: $default);

        $localePrefix = $request->segment(1);
        if ($localePrefix && Language::where('is_active', true)->where('code', $localePrefix)->exists()) {
            $code = $localePrefix;
        }

        if ($code && Language::where('is_active', true)->where('code', $code)->exists()) {
            app()->setLocale($code);
        }

        return $next($request);
    }
}
