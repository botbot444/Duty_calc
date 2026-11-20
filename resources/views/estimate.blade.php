@extends('layouts.app')

@php
    use App\Support\Display;

    $display = $e['display'];                       // USD block, or null: show ZMW only
    $v = $e['valuation'];
    $unit = $e['product']['stat_unit'] ?? null;
    $route = $e['route'];
    $estimated = $e['status'] === 'estimated';
    $missingItems = array_column($e['missing'], 'item');
    $productProblem = (bool) array_intersect($missingItems, ['catalogue_item', 'tariff_line', 'tariff_rule']);
    $routeProblem = in_array('trade_route', $missingItems, true);

    $first = fn (?string $usd, ?string $zmw) => $display ? Display::money($usd, $display['currency']) : Display::money($zmw, 'ZMW');

    $summary = collect([
        $e['product']['name'] ?? null,
        $v['quantity'] !== null ? rtrim(rtrim($v['quantity'], '0'), '.').' '.$v['quantity_unit'] : null,
        $route ? "{$route['origin_name']} → {$route['destination_name']} ".__('via')." {$route['border_post']}" : null,
    ])->filter()->implode(' · ');

    $sources = collect($e['line_items'])->map(fn ($l) => $l['source']['name'].', '.__('page :page', ['page' => $l['source']['page']]))->unique();
    $basis = $v['basis'] === null ? null : match ($v['basis']) {
        'FOB' => __('Value for duty (FOB)'),
        'CIF' => __('Customs value (CIF)'),
        default => __('Value for duty'),
    };
    $editUrl = route('home').'#details';
@endphp

@section('title', __('Your estimate').' – '.__('DutyCalc'))

@section('content')
    <a class="back-link no-print" href="{{ $editUrl }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
        {{ __('Back') }}
    </a>
    <h1>{{ __('Your estimate') }}</h1>

    @if ($estimated)
        <section class="hero" aria-label="{{ __('Estimated total to pay at the Zambian border') }}">
            <span class="k">{{ __('Estimated total to pay at the Zambian border') }}</span>
            <span class="total">{{ $first($display['total'] ?? null, $e['total_estimated_charges']) }}</span>
            @if ($display)
                <span class="alt">≈ {{ Display::money($e['total_estimated_charges'], 'ZMW') }}</span>
            @endif
            <span class="k">{{ $summary }}</span>
        </section>

        <section class="card card-flush">
            <h2>{{ __('Charges') }}</h2>
            @foreach ($e['line_items'] as $line)
                <div class="charge">
                    <div class="charge-row">
                        <span class="charge-name">{{ __($line['label']) }}</span>
                        <span class="charge-amount">{{ $first($line['amount_display'], $line['amount']) }}</span>
                    </div>
                    <div class="charge-row charge-note">
                        <span>{{ Display::rule($line, $unit) }} · {{ __('Charged on') }} {{ Display::money($line['taxable_base'], 'ZMW') }}</span>
                        @if ($display)
                            <span class="num">{{ Display::money($line['amount'], 'ZMW') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="charge-total">
                <span>{{ __('Total') }}</span>
                <span class="num">{{ $first($display['total'] ?? null, $e['total_estimated_charges']) }}</span>
            </div>
        </section>

        <section class="card">
            <h2>{{ __('How we worked it out') }}</h2>
            <dl class="sums">
                <dt>{{ __('Value of the goods') }}</dt><dd>{{ Display::money($v['declared_value_zmw'], 'ZMW') }}</dd>
                @if ($v['basis'] !== 'FOB')
                    <dt>{{ __('Freight') }}</dt><dd>{{ Display::money($v['freight_zmw'], 'ZMW') }}</dd>
                    <dt>{{ __('Insurance') }}</dt><dd>{{ Display::money($v['insurance_zmw'], 'ZMW') }}</dd>
                @endif
                @if ($basis)
                    <dt class="strong">{{ $basis }}</dt><dd class="strong">{{ Display::money($v['value_for_duty_zmw'], 'ZMW') }}</dd>
                @endif
                @php
                    // The rate used to convert the trader's input, and the one for the display currency
                    // (the same row when both are USD).
                    $rates = collect([
                        $v['input_currency'] !== 'ZMW' ? [$v['input_currency'], $v['exchange_rate'], $v['exchange_rate_date']] : null,
                        $display ? [$display['currency'], $display['exchange_rate'], $display['exchange_rate_date']] : null,
                    ])->filter()->unique(fn ($r) => implode('|', $r));
                @endphp
                @foreach ($rates as [$code, $rate, $date])
                    <dt>{{ __('Exchange rate used') }}</dt>
                    <dd>{{ $code }} 1 = ZMW {{ Display::rate($rate) }}<br><span class="hint">{{ __('Rate as of :date', ['date' => Display::date($date)]) }}</span></dd>
                @endforeach
                <dt>{{ __('Tariff source') }}</dt>
                <dd>{{ $sources->implode('; ') }}<br><span class="hint">{{ __('Tariff as of :date', ['date' => Display::date($e['tariff_effective_date'])]) }}</span></dd>
            </dl>
        </section>

        <section class="card">
            <h2>{{ __('What we assumed') }}</h2>
            <ul class="assumptions">
                @foreach ($e['assumptions'] as $assumption)
                    <li>{{ __($assumption) }}</li>
                @endforeach
            </ul>
        </section>
    @else
        @php $incomplete = $e['status'] === 'incomplete'; @endphp
        <section class="state-card {{ $incomplete ? 'state-incomplete' : 'state-unsupported' }}" role="status">
            <span class="state-title">
                @if ($incomplete)
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8A6400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l9 16H3z"></path><path d="M12 10v4M12 17v.5"></path></svg>
                    {{ __('We need more information') }}
                @else
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4A524E" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M5.6 5.6l12.8 12.8"></path></svg>
                    {{ __('We cannot estimate this') }}
                @endif
            </span>
            <ul>
                @foreach ($e['missing'] as $m)
                    <li>{{ __($m['message']) }}</li>
                @endforeach
            </ul>
            @if ($productProblem)
                <p class="muted">{{ __('For this product, ask a clearing agent or ZRA.') }}</p>
            @endif
        </section>

        @if ($e['product'])
            <section class="card">
                <span class="strong">{{ $e['product']['name'] }}</span>
                <span class="muted small">HS {{ Display::hs($e['product']['hs_code']) }}@if ($route) · {{ $route['origin_name'] }} → {{ $route['destination_name'] }} {{ __('via') }} {{ $route['border_post'] }}@endif</span>
            </section>
        @endif

        <section class="stack-lg no-print">
            <h2>{{ __('What you can do') }}</h2>
            @if ($incomplete)
                <a class="btn btn-primary" href="{{ $editUrl }}">{{ __('Add the missing details') }}</a>
            @elseif ($routeProblem)
                <a class="btn btn-primary" href="{{ $editUrl }}">{{ __('Choose another route') }}</a>
            @endif
            <a class="btn {{ $incomplete || $routeProblem ? 'btn-secondary' : 'btn-primary' }}" href="{{ route('home') }}?new=1">{{ __('Try another product') }}</a>
        </section>
    @endif

    <div class="alert alert-warn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8A6400" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16.5v.5"></path></svg>
        <span>{{ __($e['disclaimer']) }}</span>
    </div>

    <section class="stack small muted">
        <span>{{ __('Estimate number: :id', ['id' => substr($e['estimate_id'], 0, 8)]) }}</span>
        <span>{{ __('Keep this link to see this estimate again for :days days.', ['days' => config('dutycalc.estimate_retention_days')]) }}</span>
    </section>

    @if ($estimated)
        <div class="btn-grid no-print">
            <button type="button" class="btn btn-secondary btn-sm" id="copy-link" data-done="{{ __('Link copied') }}">{{ __('Copy link') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">{{ __('Print') }}</button>
            @if ($v['quantity'] !== null)
                <a class="btn btn-secondary btn-sm" href="{{ $editUrl }}">{{ __('Change quantity') }}</a>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $editUrl }}">{{ __('Back') }}</a>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ route('home') }}?new=1">{{ __('New estimate') }}</a>
        </div>
        <p class="sr-only" id="copy-status" role="status"></p>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var button = document.getElementById('copy-link');
            if (!button) return;
            button.addEventListener('click', function () {
                var done = function () {
                    button.textContent = button.dataset.done;
                    document.getElementById('copy-status').textContent = button.dataset.done;
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(window.location.href).then(done, function () { window.prompt('', window.location.href); });
                } else {
                    window.prompt('', window.location.href);
                }
            });
        })();
    </script>
@endpush
