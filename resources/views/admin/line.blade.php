@extends('admin.layout')

@section('title', \App\Support\Display::hs($line->hs_code))

@php
    use App\Support\Display;

    $today = now(config('app.timezone'))->toDateString();
    $status = function ($rule) use ($today) {
        if ($rule->effective_to !== null && $rule->effective_to->toDateString() < $today) {
            return ['Ended', 'badge-ended'];
        }
        if ($rule->effective_from->toDateString() > $today) {
            return ['Starts '.Display::date($rule->effective_from->toDateString()), 'badge-future'];
        }

        return ['In force', 'badge-ok'];
    };
    // Rules an admin can still change: charged, standard rate, with no end date yet (the latest
    // one). A rule that already has an end date is followed with "Add", not "Replace".
    $changeable = $line->rules->filter(fn ($r) => in_array($r->tax_type->value, $charged, true) && $r->origin_country === null
        && $r->effective_to === null);
@endphp

@section('content')
    <a class="back-link back-row" href="{{ route('admin.lines') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
        Tariff lines
    </a>

    <div class="stack">
        <h1><span class="num">{{ Display::hs($line->hs_code) }}</span> · {{ $line->official_description }}</h1>
        <div class="actions small muted">
            <span>Unit: <strong class="strong" style="color: var(--ink)">{{ $line->stat_unit->value }}</strong></span>
            <span>Valid {{ Display::date($line->valid_from->toDateString()) }} – {{ $line->valid_to ? Display::date($line->valid_to->toDateString()) : 'no end date' }}</span>
            <span>Source: <strong style="color: var(--ink)">{{ $line->source->name }}, page {{ $line->source_page }}</strong></span>
            <span>Products:
                @forelse ($line->catalogueItems as $item)
                    <a class="strong" href="{{ route('admin.catalogue', ['item' => $item->id]) }}">{{ $item->id }} {{ $item->display_name }}</a>@if (! $loop->last), @endif
                @empty
                    none
                @endforelse
            </span>
        </div>
    </div>

    @foreach ($missing as $type)
        <div class="alert alert-error" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B42318" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v6M12 16.5v.5"></path></svg>
            <span>No {{ strtolower($type->label()) }} is in force today, so estimates for this line are refused. <a href="#add-{{ $type->value }}">Add a rate</a>.</span>
        </div>
    @endforeach

    <section class="panel">
        <div class="panel-head">
            <h2>Rates on this line</h2>
            <span>Rates are never edited. A change ends the old rate and adds a new one.</span>
        </div>
        <div class="table-wrap">
            <table class="data" style="min-width: 860px">
                <thead><tr><th scope="col">Charge</th><th scope="col">Rate</th><th scope="col">Charged on</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Source</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($line->rules as $rule)
                        @php [$label, $badge] = $status($rule); $isCharged = in_array($rule->tax_type->value, $charged, true); @endphp
                        <tr class="{{ $label === 'Ended' || ! $isCharged ? 'past' : '' }}">
                            <td class="strong">{{ $rule->tax_type->label() }}@if ($rule->origin_country) <span class="muted">({{ $rule->origin_country }} only)</span>@endif</td>
                            <td>{{ Display::ruleOf($rule) }}</td>
                            <td>{{ $rule->method->value === 'specific' ? '–' : Display::base($rule->base_components) }}</td>
                            <td class="nowrap">{{ Display::date($rule->effective_from->toDateString()) }}</td>
                            <td class="nowrap">{{ $rule->effective_to ? Display::date($rule->effective_to->toDateString()) : '–' }}</td>
                            <td>{{ $rule->source->name }}, page {{ $rule->source_page }}</td>
                            <td>
                                @if ($isCharged)
                                    <span class="badge {{ $badge }}">{{ $label }}</span>
                                @else
                                    <span class="badge badge-ended" title="Only {{ implode(', ', $charged) }} is charged">Not charged</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                @if ($changeable->contains($rule))
                                    <a class="strong" href="#replace-{{ $rule->id }}">Replace</a>
                                    &nbsp; <a class="strong" style="color: var(--error)" href="#end-{{ $rule->id }}">End</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No rates on this line yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($missing as $type)
        @include('admin.partials.rate-form', ['rule' => null, 'taxType' => $type])
    @endforeach

    {{-- A rate is in force: "Add" is still needed after a scheduled end date, where Replace cannot reach. --}}
    @foreach (collect($charged)->map(fn ($t) => \App\Enums\TaxType::from($t))->reject(fn ($t) => $missing->contains($t)) as $type)
        <details class="panel panel-pad">
            <summary class="strong" style="cursor: pointer; min-height: 32px">Add {{ strtolower($type->label()) }} for a later period (after a rate's end date)</summary>
            @include('admin.partials.rate-form', ['rule' => null, 'taxType' => $type])
        </details>
    @endforeach

    @foreach ($changeable as $rule)
        <div class="cols">
            <div class="col-wide">
                @include('admin.partials.rate-form', ['rule' => $rule, 'taxType' => $rule->tax_type])
            </div>
            <form class="col-narrow panel panel-pad" id="end-{{ $rule->id }}" data-api="{{ route('api.admin.rules.end', $rule) }}"
                  data-confirm="End this rate? With no rate in force, estimates for this line are refused." novalidate>
                <h2>End this rate</h2>
                <div class="alert alert-warn small">
                    <span>With no {{ strtolower($rule->tax_type->label()) }} in force, estimates for this line are refused. To record “no duty”, replace it with a 0% rate instead.</span>
                </div>
                <ul class="form-errors" data-form-errors hidden></ul>
                <div class="field" data-field="effective_to">
                    <label for="end-{{ $rule->id }}-to">Last day of this rate</label>
                    <input class="input" id="end-{{ $rule->id }}-to" name="effective_to" type="date">
                    <p class="field-error" data-error-for="effective_to" hidden></p>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">End rate</button>
            </form>
        </div>
    @endforeach
@endsection
