<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaxType;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\CatalogueItem;
use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\TradeRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The admin portal pages (design: "Admin portal" page of the mockup canvas). They only read;
 * every change goes through the JSON endpoints under /api/admin (the same validation, the same
 * RateChangeService and the same change log), called by public/js/admin.js.
 */
class AdminPageController extends Controller
{
    /** GET /admin/login */
    public function login(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('admin.lines') : view('admin.login');
    }

    /** GET /admin/lines?q=&show=all|attention */
    public function lines(Request $request): View
    {
        $charged = config('dutycalc.charged_tax_types', []);
        $q = trim((string) $request->query('q', ''));
        $show = $request->query('show') === 'attention' ? 'attention' : 'all';

        $lines = TariffLine::with(['rules' => fn ($r) => $r->with('source', 'tariffLine')->whereIn('tax_type', $charged)->forOrigin(null)->inForce(), 'catalogueItems'])
            ->when($q !== '', function (Builder $query) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $query->where(fn (Builder $w) => $w
                    ->where('official_description', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->when($digits !== '', fn (Builder $x) => $x->orWhere('hs_code', 'like', $digits.'%')));
            })
            ->when($show === 'attention', fn (Builder $query) => $query->whereDoesntHave('rules',
                fn (Builder $r) => $r->whereIn('tax_type', $charged)->whereNull('origin_country')->inForce()))
            ->orderBy('hs_code')
            ->orderByDesc('valid_from')
            ->paginate(50)
            ->withQueryString();

        return view('admin.lines', ['lines' => $lines, 'q' => $q, 'show' => $show] + $this->shared());
    }

    /** GET /admin/lines/{line} */
    public function line(TariffLine $line): View
    {
        $line->load(['source', 'catalogueItems', 'rules' => fn ($r) => $r->with('source', 'tariffLine')
            ->orderBy('tax_type')->orderByDesc('effective_from')]);

        $charged = config('dutycalc.charged_tax_types', []);
        $missing = collect($charged)->reject(fn (string $type) => $line->rules
            ->contains(fn (TariffRule $r) => $r->tax_type->value === $type && $r->origin_country === null && $r->isInForce()))
            ->map(fn (string $type) => TaxType::from($type))
            ->values();

        return view('admin.line', [
            'line' => $line,
            'charged' => $charged,
            'missing' => $missing,
            'sources' => TariffSource::orderBy('name')->get(),
        ] + $this->shared());
    }

    /** GET /admin/import */
    public function import(): View
    {
        return view('admin.import', $this->shared());
    }

    /** GET /admin/rates */
    public function rates(): View
    {
        return view('admin.rates', [
            'rates' => ExchangeRate::with('enteredBy:id,name')->orderBy('currency')->orderByDesc('effective_from')->paginate(50),
            'displayCurrency' => config('dutycalc.display_currency'),
        ] + $this->shared());
    }

    /** GET /admin/catalogue?item=CAT-0004 */
    public function catalogue(Request $request): View
    {
        $items = CatalogueItem::with(['tariffLine:id,hs_code,official_description', 'aliases'])->orderBy('id')->get();
        $selected = $items->firstWhere('id', $request->query('item')) ?? $items->first();

        return view('admin.catalogue', ['items' => $items, 'selected' => $selected] + $this->shared());
    }

    /** GET /admin/reference */
    public function reference(Request $request): View
    {
        $sources = TariffSource::withCount(['tariffLines', 'tariffRules'])->orderBy('name')->get();

        return view('admin.reference', [
            'routes' => TradeRoute::with('origin', 'destination')->orderBy('origin_iso2')->orderBy('destination_iso2')->orderBy('border_post')->get(),
            'sources' => $sources,
            'editing' => $sources->firstWhere('id', (int) $request->query('source')),
        ] + $this->shared());
    }

    /** GET /admin/log?type=&from=&to= */
    public function log(Request $request): View
    {
        $types = [
            'rates' => ['tables' => ['tariff_rules']],
            'imports' => ['actions' => ['import']],
            'exchange' => ['tables' => ['exchange_rates']],
            'catalogue' => ['tables' => ['catalogue_items', 'product_aliases']],
            'reference' => ['tables' => ['trade_routes', 'tariff_sources']],
        ];
        $type = array_key_exists((string) $request->query('type'), $types) ? (string) $request->query('type') : null;
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        $entries = AdminAuditLog::with('user:id,name,email')
            ->when($type && isset($types[$type]['tables']), fn ($q) => $q->whereIn('table_name', $types[$type]['tables']))
            ->when($type && isset($types[$type]['actions']), fn ($q) => $q->whereIn('action', $types[$type]['actions']))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.log', ['entries' => $entries, 'type' => $type, 'from' => $from, 'to' => $to] + $this->shared());
    }

    // ---------------------------------------------------------------------

    /** Data every admin page shows: unchecked sources still in use (the "not verified" banner). */
    private function shared(): array
    {
        return [
            'uncheckedSources' => TariffSource::where(fn ($q) => $q->whereNull('verified_by')->orWhere('verified_by', ''))
                ->where(fn ($q) => $q->has('tariffLines')->orHas('tariffRules'))
                ->orderBy('name')->pluck('name'),
        ];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
