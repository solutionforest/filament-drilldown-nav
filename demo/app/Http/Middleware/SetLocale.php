<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Demo only: `?lang=ja` switches the interface language and remembers it in the session. */
class SetLocale
{
    public const LOCALES = ['en', 'zh_CN', 'zh_TW', 'zh_HK', 'ja', 'ko', 'es'];

    public function handle(Request $request, Closure $next): mixed
    {
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, self::LOCALES, true)) {
            $request->session()->put('locale', $lang);
        }

        $locale = $request->session()->get('locale');

        if (is_string($locale) && in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
