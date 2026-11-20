@extends('admin.layout')

@section('title', 'Import CSV')

@section('content')
    <div class="page-head">
        <div>
            <h1>Import CSV</h1>
            <p>Add tariff lines, rates, products and search words from the agreed CSV files. An import only adds rows; nothing is changed or deleted.</p>
        </div>
    </div>

    <ol class="steps">
        <li data-step="1" aria-current="step"><span class="dot">1</span>Choose files</li>
        <li data-step="2"><span class="dot">2</span>Check</li>
        <li data-step="3"><span class="dot">3</span>Import</li>
    </ol>

    <form id="import-form" class="dropzone" data-api="{{ route('api.admin.imports.preview') }}" data-multipart data-handler="importPreview" novalidate>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0B6E4F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M4 15v5h16v-5"></path></svg>
        <div class="field grow" data-field="files">
            <label for="files">CSV files</label>
            <input id="files" name="files[]" type="file" accept=".csv,text/csv" multiple>
            <p class="hint">Use the file names from the format: tariff_sources.csv, countries.csv, trade_routes.csv, tariff_lines.csv, catalogue_items.csv, product_aliases.csv, tariff_rules.csv, exchange_rates.csv.</p>
            <p class="field-error" data-error-for="files" hidden></p>
        </div>
        <ul class="form-errors" data-form-errors hidden></ul>
        <button type="submit" class="btn btn-primary btn-sm">Check files</button>
    </form>

    <section class="panel panel-accent" id="import-result" hidden>
        <div class="panel-head">
            <h2>Check passed: nothing saved yet</h2>
            <span>Every row has a source and page, and no dates overlap.</span>
        </div>
        <table class="data">
            <thead><tr><th scope="col">File</th><th scope="col" class="num">Rows to add</th></tr></thead>
            <tbody></tbody>
        </table>
        <div class="panel-pad" style="flex-direction: row; flex-wrap: wrap; align-items: center">
            <button type="button" class="btn btn-primary btn-sm" id="import-confirm">Import</button>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.import') }}">Cancel</a>
            <span class="panel-note">The import is written to the change log.</span>
        </div>
    </section>

    <section class="panel" id="import-errors" hidden style="border-color: #F4B5AE">
        <div class="panel-head" style="background: var(--error-bg); color: var(--error-text); border-radius: var(--radius) var(--radius) 0 0">
            <h2>Import refused: <span data-error-count></span></h2>
            <span style="color: var(--error-text)">Nothing was saved. Fix the rows in the file and choose it again.</span>
        </div>
        <div class="table-wrap">
            <table class="data" style="min-width: 420px">
                <thead><tr><th scope="col">File</th><th scope="col" class="num">Row</th><th scope="col">Problem</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="panel-note">File format: <code>database/data/sample/README.md</code>. Rates are fractions (0.05 = 5%). Base components: declared_value, freight, insurance or customs_value.</p>
@endsection
