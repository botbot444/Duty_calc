<?php

namespace App\Http\Middleware;

use App\Enums\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the trader picked (session `locale`: en, bem or sw) for __() strings,
 * on the screens and in the JSON endpoints' validation messages.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($locale) && Language::tryFrom($locale) !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
