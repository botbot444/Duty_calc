@extends('admin.layout')

@section('title', 'Catalogue')

@php
    use App\Support\Display;

    $languages = ['en' => 'English', 'bem' => 'Ichibemba', 'sw' => 'Kiswahili'];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Catalogue</h1>
            <p>The products traders can choose, and the words they use to find them.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="#add-product">Add product</a>
    </div>

    <section class="panel table-wrap">
        <table class="data" style="min-width: 760px">
            <thead><tr><th scope="col">ID</th><th scope="col">Product</th><th scope="col">HS code</th><th scope="col">Search words</th><th scope="col">Shown to traders</th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    <tr @if ($selected && $selected->id === $item->id) style="background: #F6FBF8" @endif class="{{ $item->active ? '' : 'past' }}">
                        <td class="nowrap">{{ $item->id }}</td>
                        <td><a class="strong" href="{{ route('admin.catalogue', ['item' => $item->id]) }}#words">{{ $item->display_name }}</a></td>
                        <td class="nowrap"><a href="{{ route('admin.line', $item->tariff_line_id) }}">{{ Display::hs($item->tariffLine->hs_code) }}</a></td>
                        <td class="muted">{{ $item->aliases->groupBy('language')->map(fn ($a) => $a->pluck('alias')->implode(', '))->implode(' · ') ?: '–' }}</td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" role="switch" @checked($item->active) data-toggle-api="{{ route('api.admin.items.update', $item) }}" data-method="PATCH" data-field="active" aria-label="Show {{ $item->display_name }} to traders">
                                <span class="track" aria-hidden="true"></span>
                                <span>{{ $item->active ? 'Yes' : 'No' }}</span>
                            </label>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No products yet. Add one below or import catalogue_items.csv.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="cols">
        @if ($selected)
            <section class="col-wide panel panel-pad panel-accent" id="words">
                <div class="stack">
                    <h2>{{ $selected->display_name }}</h2>
                    <span class="hint">{{ $selected->id }} · HS {{ Display::hs($selected->tariffLine->hs_code) }}</span>
                </div>

                <form class="toolbar" data-api="{{ route('api.admin.items.update', $selected) }}" data-method="PATCH" novalidate>
                    <ul class="form-errors" data-form-errors hidden></ul>
                    <div class="field grow" data-field="display_name">
                        <label for="rename">Name traders see</label>
                        <input class="input" id="rename" name="display_name" value="{{ $selected->display_name }}" maxlength="255">
                        <p class="field-error" data-error-for="display_name" hidden></p>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Rename</button>
                </form>

                <div class="stack-lg section-sep">
                    <span class="strong">Search words</span>
                    <span class="hint">Add every name, short form and common misspelling traders use.</span>
                    @foreach ($languages as $code => $name)
                        <div class="alias-row">
                            <span class="alias-lang">{{ $name }}</span>
                            @forelse ($selected->aliases->where('language', $code) as $alias)
                                <span class="alias">{{ $alias->alias }}
                                    <button type="button" aria-label="Remove {{ $alias->alias }}" data-action data-method="DELETE"
                                            data-api="{{ route('api.admin.aliases.destroy', [$selected, $alias]) }}"
                                            data-confirm="Remove the search word “{{ $alias->alias }}”?">×</button>
                                </span>
                            @empty
                                <span class="hint">None yet</span>
                            @endforelse
                        </div>
                    @endforeach
                </div>

                <form class="toolbar section-sep" data-api="{{ route('api.admin.aliases.store', $selected) }}" novalidate>
                    <ul class="form-errors" data-form-errors hidden style="flex-basis: 100%"></ul>
                    <div class="field grow" data-field="alias">
                        <label for="al-word">New search word</label>
                        <input class="input" id="al-word" name="alias" maxlength="150">
                        <p class="field-error" data-error-for="alias" hidden></p>
                    </div>
                    <div class="field" data-field="language">
                        <label for="al-lang">Language</label>
                        <select class="input" id="al-lang" name="language">
                            @foreach ($languages as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add word</button>
                </form>
            </section>
        @endif

        <form class="col-narrow panel panel-pad" id="add-product" data-api="{{ route('api.admin.items.store') }}" novalidate>
            <h2>Add product</h2>
            <ul class="form-errors" data-form-errors hidden></ul>
            <div class="field" data-field="id">
                <label for="p-id">ID</label>
                <input class="input" id="p-id" name="id" placeholder="CAT-0009" maxlength="20">
                <p class="field-error" data-error-for="id" hidden></p>
            </div>
            <div class="field" data-field="display_name">
                <label for="p-name">Name traders see</label>
                <input class="input" id="p-name" name="display_name" maxlength="255">
                <p class="field-error" data-error-for="display_name" hidden></p>
            </div>
            <div class="field" data-field="hs_code">
                <label for="p-hs">HS code</label>
                <input class="input" id="p-hs" name="hs_code" inputmode="numeric" placeholder="8 digits">
                <p class="hint">Must match a tariff line. Add the line first (CSV import) if it is new.</p>
                <p class="field-error" data-error-for="hs_code" hidden></p>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Add product</button>
        </form>
    </div>
@endsection
