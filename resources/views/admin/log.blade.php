@extends('admin.layout')

@section('title', 'Change log')

@php
    $tables = [
        'tariff_rules' => 'Rate', 'exchange_rates' => 'Exchange rate', 'catalogue_items' => 'Product',
        'product_aliases' => 'Search word', 'trade_routes' => 'Route', 'tariff_sources' => 'Source', 'tariff_import' => 'CSV import',
    ];
    $actions = [
        'create' => ['Added', 'badge-ok'], 'update' => ['Changed', 'badge-future'], 'replace' => ['New rate', 'badge-warn'],
        'end' => ['Ended', 'badge-ended'], 'import' => ['Imported', 'badge-ok'], 'toggle' => ['Switched', 'badge-ended'],
        'delete' => ['Deleted', 'badge-bad'],
    ];
    $skip = ['id', 'created_at', 'updated_at'];
    $show = fn ($v) => is_array($v) ? implode(', ', array_map(fn ($x) => is_array($x) ? json_encode($x) : (string) $x, $v)) : ($v === null ? '–' : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v));

    /** @return list<array{0: string, 1: ?string, 2: ?string}> field, before, after (only what changed) */
    $changes = function ($entry) use ($skip, $show) {
        $before = is_array($entry->before) ? $entry->before : [];
        $after = is_array($entry->after) ? $entry->after : [];
        $rows = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $b = array_key_exists($key, $before) ? $show($before[$key]) : null;
            $a = array_key_exists($key, $after) ? $show($after[$key]) : null;
            if ($b !== $a) {
                $rows[] = [str_replace('_', ' ', $key), $b, $a];
            }
        }

        return $rows;
    };
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Change log</h1>
            <p>Every change made in the admin portal or by CSV import: who, when, and what changed. Entries cannot be edited or deleted.</p>
        </div>
    </div>

    <form class="toolbar" method="get" action="{{ route('admin.log') }}">
        <div class="field">
            <label for="type">Change</label>
            <select class="input" id="type" name="type">
                <option value="">All changes</option>
                @foreach (['rates' => 'Tariff rates', 'imports' => 'CSV imports', 'exchange' => 'Exchange rates', 'catalogue' => 'Catalogue', 'reference' => 'Routes and sources'] as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="from">From</label>
            <input class="input" id="from" name="from" type="date" value="{{ $from }}">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input class="input" id="to" name="to" type="date" value="{{ $to }}">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </form>

    <section class="panel table-wrap">
        <table class="data" style="min-width: 820px">
            <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Change</th><th scope="col">What</th><th scope="col">Before → after</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php [$label, $badge] = $actions[$entry->action] ?? [ucfirst($entry->action), 'badge-ended']; @endphp
                    <tr>
                        <td class="nowrap">{{ $entry->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</td>
                        <td>{{ $entry->user?->email ?? '–' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                        <td>{{ $tables[$entry->table_name] ?? $entry->table_name }}@if ($entry->record_id && $entry->action !== 'import') <span class="muted">#{{ $entry->record_id }}</span>@endif</td>
                        <td class="diff">
                            @if ($entry->action === 'import')
                                @php $files = $entry->after['files'] ?? []; @endphp
                                <strong>{{ array_sum($files) }} rows added</strong><br>
                                {{ collect($files)->map(fn ($n, $f) => "{$f}: {$n}")->implode(', ') }}
                            @else
                                @if (isset($entry->before['replaces_rule_id']))
                                    <div><strong>Replaces rate #{{ $entry->before['replaces_rule_id'] }}</strong></div>
                                @endif
                                @forelse (array_slice(array_values(array_filter($changes($entry), fn ($c) => $c[0] !== 'replaces rule id')), 0, 8) as [$field, $b, $a])
                                    <div>{{ $field }}: @if ($b !== null){{ $b }} → @endif<strong>{{ $a ?? '–' }}</strong></div>
                                @empty
                                    –
                                @endforelse
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No changes recorded @if ($type || $from || $to)for this filter. <a href="{{ route('admin.log') }}">Show all</a>.@else yet.@endif</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($entries->hasPages())
        <nav class="pager" aria-label="Pages">
            <span class="muted">{{ $entries->firstItem() }}–{{ $entries->lastItem() }} of {{ $entries->total() }}</span>
            <span class="actions">
                @if ($entries->previousPageUrl())<a href="{{ $entries->previousPageUrl() }}">Newer</a>@endif
                @if ($entries->nextPageUrl())<a href="{{ $entries->nextPageUrl() }}">Older</a>@endif
            </span>
        </nav>
    @endif
@endsection
