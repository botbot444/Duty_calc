@extends('admin.layout')

@section('title', 'Routes and sources')

@php $s = $editing; @endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Routes and sources</h1>
            <p>Which border posts traders can choose, and the documents every rate comes from.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>Trade routes</h2>
            <span>Only supported routes appear on the trader screens. An estimate on any other route is refused. New routes come in through the CSV import.</span>
        </div>
        <div class="table-wrap">
            <table class="data" style="min-width: 560px">
                <thead><tr><th scope="col">From</th><th scope="col">To</th><th scope="col">Border post</th><th scope="col">Supported</th></tr></thead>
                <tbody>
                    @forelse ($routes as $route)
                        <tr>
                            <td>{{ $route->origin->name }}</td>
                            <td>{{ $route->destination->name }}</td>
                            <td class="strong">{{ $route->border_post }}</td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" role="switch" @checked($route->supported) data-toggle-api="{{ route('api.admin.routes.toggle', $route) }}" data-field="supported"
                                           aria-label="{{ $route->origin->name }} to {{ $route->destination->name }} via {{ $route->border_post }} supported">
                                    <span class="track" aria-hidden="true"></span>
                                    <span>{{ $route->supported ? 'Yes' : 'No' }}</span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No routes yet. Import trade_routes.csv.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="cols">
        <section class="panel col-wide">
            <div class="panel-head">
                <h2>Tariff sources</h2>
                <span>A source in use cannot be deleted.</span>
            </div>
            <div class="table-wrap">
                <table class="data" style="min-width: 600px">
                    <thead><tr><th scope="col">Source</th><th scope="col">Edition</th><th scope="col">Checked by</th><th scope="col" class="num">Used by</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @forelse ($sources as $source)
                            @php $used = $source->tariff_lines_count + $source->tariff_rules_count; @endphp
                            <tr>
                                <td class="strong">@if ($source->document_url)<a href="{{ $source->document_url }}" rel="noopener noreferrer" target="_blank">{{ $source->name }}</a>@else{{ $source->name }}@endif</td>
                                <td>{{ $source->edition ?: '–' }}</td>
                                <td>@if ($source->verified_by){{ $source->verified_by }}@else<span class="strong" style="color: var(--error)">Not checked</span>@endif</td>
                                <td class="num nowrap">{{ $source->tariff_lines_count }} lines, {{ $source->tariff_rules_count }} rates</td>
                                <td class="nowrap">
                                    <a class="strong" href="{{ route('admin.reference', ['source' => $source->id]) }}#source-form">Edit</a>
                                    &nbsp;
                                    @if ($used > 0)
                                        <button type="button" class="link-btn" disabled title="In use: cannot be deleted">Delete</button>
                                    @else
                                        <button type="button" class="link-btn danger" data-action data-method="DELETE" data-api="{{ route('api.admin.sources.destroy', $source) }}"
                                                data-confirm="Delete the source “{{ $source->name }}”?">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No sources yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <form class="col-narrow panel panel-pad panel-accent" id="source-form" novalidate
              data-api="{{ $s ? route('api.admin.sources.update', $s) : route('api.admin.sources.store') }}" @if ($s) data-method="PATCH" data-success="{{ route('admin.reference') }}" @endif>
            <h2>{{ $s ? 'Edit source' : 'Add source' }}</h2>
            <ul class="form-errors" data-form-errors hidden></ul>
            <div class="field" data-field="name">
                <label for="s-name">Name</label>
                <input class="input" id="s-name" name="name" value="{{ $s?->name }}" placeholder="For example: ZRA Customs and Excise Tariff">
                <p class="field-error" data-error-for="name" hidden></p>
            </div>
            <div class="form-grid">
                <div class="field" data-field="edition">
                    <label for="s-ed">Edition</label>
                    <input class="input" id="s-ed" name="edition" value="{{ $s?->edition }}" data-null>
                    <p class="field-error" data-error-for="edition" hidden></p>
                </div>
                <div class="field" data-field="published_on">
                    <label for="s-pub">Published on</label>
                    <input class="input" id="s-pub" name="published_on" type="date" value="{{ $s?->published_on?->toDateString() }}" data-null>
                    <p class="field-error" data-error-for="published_on" hidden></p>
                </div>
                <div class="field" data-field="retrieved_on">
                    <label for="s-ret">Retrieved on</label>
                    <input class="input" id="s-ret" name="retrieved_on" type="date" value="{{ $s?->retrieved_on?->toDateString() }}" data-null>
                    <p class="field-error" data-error-for="retrieved_on" hidden></p>
                </div>
            </div>
            <div class="field" data-field="document_url">
                <label for="s-url">Link to the document</label>
                <input class="input" id="s-url" name="document_url" type="url" value="{{ $s?->document_url }}" placeholder="https://" data-null>
                <p class="field-error" data-error-for="document_url" hidden></p>
            </div>
            <div class="field" data-field="verified_by">
                <label for="s-ver">Checked by</label>
                <input class="input" id="s-ver" name="verified_by" value="{{ $s?->verified_by }}" data-null>
                <p class="hint">The second person who checked the rows against the document.</p>
                <p class="field-error" data-error-for="verified_by" hidden></p>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ $s ? 'Save changes' : 'Save source' }}</button>
                @if ($s)<a class="btn btn-secondary btn-sm" href="{{ route('admin.reference') }}">Cancel</a>@endif
            </div>
        </form>
    </div>
@endsection
