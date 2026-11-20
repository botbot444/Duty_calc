<?php

namespace App\Http\Controllers;

use App\Enums\Language;
use App\Http\Middleware\SetLocale;
use App\Models\TradeRoute;
use App\Services\Estimate\SupportedCurrencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The trader screens (search → suggestions → confirm → trade details). The page calls the
 * same JSON endpoints as any other client; routes and currencies are sent with the page so
 * a slow connection makes one request fewer.
 */
class TraderController extends Controller
{
    /** GET / */
    public function index(SupportedCurrencies $currencies): View
    {
        $routes = TradeRoute::supported()
            ->with('origin', 'destination')
            ->orderBy('destination_iso2')
            ->orderBy('border_post')
            ->get()
            ->map(fn (TradeRoute $r) => [
                'id' => $r->id,
                'origin' => ['iso2' => $r->origin_iso2, 'name' => $r->origin->name],
                'destination' => ['iso2' => $r->destination_iso2, 'name' => $r->destination->name],
                'border_post' => $r->border_post,
            ])
            ->values();

        return view('trader', [
            'routes' => $routes,
            'currencies' => array_column($currencies->all(), 'code'),
        ]);
    }

    /**
     * GET /language/{locale}?to=/path#screen – remember the trader's language and go back to the
     * same screen. The page script adds `to`, because the browser never sends the #screen part
     * in the Referer. Only a local path is accepted, so this is not an open redirect.
     */
    public function language(Request $request, string $locale): RedirectResponse
    {
        abort_if(Language::tryFrom($locale) === null, 404);
        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        $to = $request->query('to');
        if (is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')) {
            return redirect($to);
        }

        return redirect()->back(fallback: route('home'));
    }
}
