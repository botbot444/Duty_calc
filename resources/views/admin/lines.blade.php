@extends('admin.layout')

@section('title', 'Tariff lines')

@php use App\Support\Display; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Tariff lines</h1>
            <p>Charges estimated: {{ collect(config('dutycalc.charged_tax_types'))->map(fn ($t) => \App\Enums\TaxType::from($t)->label())->implode(', ') }}. Every rate shows its source and page.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.import') }}">Import CSV</a>
    </div>

    <form class="toolbar" method="get" action="{{ route('admin.lines') }}" role="search">
        <div class="field grow">
            <label for="q">Search</label>
            <input class="input" id="q" name="q" type="search" value="{{ $q }}" placeholder="HS code or description">
        </div>
        <div class="field">
            <label for="show">Show</label>
            <select class="input" id="show" name="show">
                <option value="all" @selected($show === 'all')>All lines</option>
                <option value="attention" @selected($show === 'attention')>No rate in force</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </form>

    <section class="panel table-wrap">
        <table class="data" style="min-width: 760px">
            <thead><tr><th scope="col">HS code</th><th scope="col">Official description</th><th scope="col">Unit</th><th scope="col">Products</th><th scope="col">In force today</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td class="strong num-left nowrap"><a href="{{ route('admin.line', $line) }}">{{ Display::hs($line->hs_code) }}</a></td>
                        <td>{{ $line->official_description }}</td>
                        <td>{{ $line->stat_unit->value }}</td>
                        <td>{{ $line->catalogueItems->pluck('id')->implode(', ') ?: '–' }}</td>
                        <td>
                            @forelse ($line->rules as $rule)
                                <div>{{ Display::ruleOf($rule) }}@if ($rule->method->value !== 'specific')<span class="muted"> · {{ Display::base($rule->base_components) }}</span>@endif</div>
                            @empty
                                <span class="strong" style="color: var(--error-text)">None</span>
                            @endforelse
                        </td>
                        <td>
                            @if (! $line->isInForce())
                                <span class="badge badge-ended">Line not valid today</span>
                            @elseif ($line->rules->isEmpty())
                                <span class="badge badge-bad">Estimates refused</span>
                            @else
                                <span class="badge badge-ok">In force</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No tariff lines match. @if ($q !== '' || $show !== 'all')<a href="{{ route('admin.lines') }}">Show all lines</a>.@endif</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($lines->hasPages())
        <nav class="pager" aria-label="Pages">
            <span class="muted">{{ $lines->firstItem() }}–{{ $lines->lastItem() }} of {{ $lines->total() }}</span>
            <span class="actions">
                @if ($lines->previousPageUrl())<a href="{{ $lines->previousPageUrl() }}">Previous</a>@endif
                @if ($lines->nextPageUrl())<a href="{{ $lines->nextPageUrl() }}">Next</a>@endif
            </span>
        </nav>
    @endif

    <p class="panel-note">A line with no rate in force makes every estimate for its products “unsupported”. Open the line and add a rate (0% if there is no duty) with its source and page.</p>
@endsection
