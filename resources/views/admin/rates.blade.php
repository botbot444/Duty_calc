@extends('admin.layout')

@section('title', 'Exchange rates')

@php use App\Support\Display; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Exchange rates</h1>
            <p>Enter the {{ $displayCurrency ?: 'USD' }} rate each week. ZMW needs no rate.</p>
        </div>
    </div>

    @if ($displayCurrency)
        <div class="alert alert-info">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2F5D8C" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 7.5v.5"></path></svg>
            <span>The {{ $displayCurrency }} rate is used twice: to convert values traders enter in {{ $displayCurrency }}, and to show results in {{ $displayCurrency }}. With no {{ $displayCurrency }} rate in force, traders can still enter ZMW and see results in ZMW only.</span>
        </div>
    @endif

    <div class="cols">
        <section class="panel col-wide">
            <div class="panel-head"><h2>Rates</h2></div>
            <div class="table-wrap">
                <table class="data" style="min-width: 600px">
                    <thead><tr><th scope="col">Currency</th><th scope="col">1 unit =</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Source</th><th scope="col">Entered by</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($rates as $rate)
                            @php $inForce = $rate->isInForce(); $future = ! $inForce && $rate->effective_from->isFuture(); @endphp
                            <tr class="{{ $inForce || $future ? '' : 'past' }}">
                                <td class="strong">{{ $rate->currency }}</td>
                                <td class="nowrap">ZMW {{ Display::rate($rate->rate_to_zmw) }}</td>
                                <td class="nowrap">{{ Display::date($rate->effective_from->toDateString()) }}</td>
                                <td class="nowrap">{{ $rate->effective_to ? Display::date($rate->effective_to->toDateString()) : '–' }}</td>
                                <td>{{ $rate->source }}</td>
                                <td>{{ $rate->enteredBy?->name ?? '–' }}</td>
                                <td>
                                    @if ($inForce)<span class="badge badge-ok">In force</span>
                                    @elseif ($future)<span class="badge badge-future">Starts {{ Display::date($rate->effective_from->toDateString()) }}</span>
                                    @else<span class="badge badge-ended">Ended</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No exchange rates yet. Results are shown in ZMW only.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <form class="col-narrow panel panel-pad panel-accent" data-api="{{ route('api.admin.rates.store') }}" novalidate>
            <h2>Add this week's rate</h2>
            <ul class="form-errors" data-form-errors hidden></ul>
            <div class="form-grid">
                <div class="field" data-field="currency">
                    <label for="x-cur">Currency</label>
                    <input class="input" id="x-cur" name="currency" value="{{ $displayCurrency ?: 'USD' }}" maxlength="3" autocapitalize="characters" list="currency-list">
                    <datalist id="currency-list"><option value="USD"></option></datalist>
                    <p class="field-error" data-error-for="currency" hidden></p>
                </div>
                <div class="field" data-field="rate_to_zmw">
                    <label for="x-rate">1 unit in ZMW</label>
                    <input class="input" id="x-rate" name="rate_to_zmw" inputmode="decimal" placeholder="26.50">
                    <p class="field-error" data-error-for="rate_to_zmw" hidden></p>
                </div>
            </div>
            <div class="field" data-field="effective_from">
                <label for="x-from">Starts on</label>
                <input class="input" id="x-from" name="effective_from" type="date" value="{{ now(config('app.timezone'))->toDateString() }}">
                <p class="field-error" data-error-for="effective_from" hidden></p>
            </div>
            <div class="field" data-field="source">
                <label for="x-src">Source</label>
                <input class="input" id="x-src" name="source" placeholder="For example: Bank of Zambia, published rate">
                <p class="field-error" data-error-for="source" hidden></p>
            </div>
            <p class="hint">Saving ends the currency's current rate the day before. Estimates already saved keep the rate they used.</p>
            <button type="submit" class="btn btn-primary btn-sm">Save rate</button>
        </form>
    </div>
@endsection
