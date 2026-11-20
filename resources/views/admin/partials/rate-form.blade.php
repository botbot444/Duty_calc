{{--
    A rate form: "Replace this rate" ($rule set) or "Add a rate" ($taxType set).
    Values are typed as a percentage and sent as a fraction (data-percent).
--}}
@php
    use App\Support\Display;

    $old = $rule ?? null;
    $unit = $line->stat_unit->value;
    $method = $old?->method->value;
    $base = $old?->base_components ?? ['declared_value'];
    if (in_array('customs_value', $base, true)) {
        $base = ['declared_value', 'freight', 'insurance'];
    }
    $id = $old ? 'r'.$old->id : 'add-'.$taxType->value;
    $defaultFrom = $old ? $old->effective_from->copy()->max(now(config('app.timezone'))->startOfDay())->addDay()->toDateString()
        : now(config('app.timezone'))->toDateString();
@endphp
<form class="panel panel-pad panel-accent" id="{{ $old ? 'replace-'.$old->id : 'add-'.$taxType->value }}" data-rate-form data-unit="{{ $unit }}"
      data-api="{{ $old ? route('api.admin.rules.replace', $old) : route('api.admin.rules.store', $line) }}" novalidate>
    <div class="stack">
        <h2>{{ $old ? 'Replace this rate' : 'Add '.strtolower($taxType->label()) }}</h2>
        <p class="lead small">
            @if ($old)
                The current rate ends the day before the new one starts. Saved estimates keep the rate they used.
            @else
                For a period with no {{ strtolower($taxType->label()) }}. If there is no duty, add 0% with its source and page.
            @endif
        </p>
    </div>
    <ul class="form-errors" data-form-errors hidden></ul>
    @unless ($old)
        <input type="hidden" name="tax_type" value="{{ $taxType->value }}">
    @endunless

    <div class="form-grid">
        <div class="field" data-field="effective_from">
            <label for="{{ $id }}-from">{{ $old ? 'New rate starts on' : 'Starts on' }}</label>
            <input class="input" id="{{ $id }}-from" name="effective_from" type="date" value="{{ $defaultFrom }}">
            <p class="field-error" data-error-for="effective_from" hidden></p>
        </div>
        <fieldset data-field="method">
            <legend>Method</legend>
            <div class="choice-row">
                @foreach (['ad_valorem' => 'Percentage', 'specific' => 'Per '.$unit, 'compound' => 'Both'] as $value => $label)
                    <label class="choice"><input type="radio" name="method" value="{{ $value }}" @checked(($method ?? 'ad_valorem') === $value)> {{ $label }}</label>
                @endforeach
            </div>
            <p class="field-error" data-error-for="method" hidden></p>
        </fieldset>
        <div class="field" data-field="rate" data-show-for="ad_valorem compound">
            <label for="{{ $id }}-rate">Percentage</label>
            <div class="affix">
                <input id="{{ $id }}-rate" name="rate" inputmode="decimal" data-percent value="{{ $old?->rate !== null ? rtrim(Display::percent($old->rate), '%') : '' }}">
                <span>%</span>
            </div>
            <p class="field-error" data-error-for="rate" hidden></p>
        </div>
        <div class="field" data-field="specific_amount" data-show-for="specific compound">
            <label for="{{ $id }}-amount">Amount per {{ $unit }}</label>
            <div class="affix">
                <span>K</span>
                <input id="{{ $id }}-amount" name="specific_amount" inputmode="decimal" value="{{ $old?->specific_amount !== null ? Display::rate($old->specific_amount) : '' }}">
            </div>
            <input type="hidden" name="specific_currency" value="ZMW">
            <p class="field-error" data-error-for="specific_amount" hidden></p>
        </div>
        <div class="field" data-field="combine" data-show-for="compound">
            <label for="{{ $id }}-combine">When both apply</label>
            <select class="input" id="{{ $id }}-combine" name="combine">
                <option value="max" @selected(($old?->combine?->value ?? 'max') === 'max')>Whichever is higher</option>
                <option value="sum" @selected($old?->combine?->value === 'sum')>Add them together</option>
            </select>
        </div>
        <fieldset data-field="base_components">
            <legend>Charged on</legend>
            <label class="check"><input type="checkbox" name="base_components[]" value="declared_value" data-label="value of the goods" @checked(in_array('declared_value', $base, true))> Value of the goods</label>
            <label class="check"><input type="checkbox" name="base_components[]" value="freight" data-label="freight" @checked(in_array('freight', $base, true))> Freight</label>
            <label class="check"><input type="checkbox" name="base_components[]" value="insurance" data-label="insurance" @checked(in_array('insurance', $base, true))> Insurance</label>
            <p class="field-error" data-error-for="base_components" hidden></p>
        </fieldset>
        <div class="field" data-field="tariff_source_id">
            <label for="{{ $id }}-source">Source</label>
            <select class="input" id="{{ $id }}-source" name="tariff_source_id">
                <option value="">Choose the source…</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->id }}">{{ $source->name }}@if ($source->edition) ({{ $source->edition }})@endif</option>
                @endforeach
            </select>
            <p class="field-error" data-error-for="tariff_source_id" hidden></p>
        </div>
        <div class="field" data-field="source_page">
            <label for="{{ $id }}-page">Page</label>
            <input class="input" id="{{ $id }}-page" name="source_page" inputmode="numeric">
            <p class="field-error" data-error-for="source_page" hidden></p>
        </div>
    </div>

    <p class="preview-line"><span class="muted">Preview:</span> <strong data-preview></strong></p>

    <div class="actions">
        <button type="submit" class="btn btn-primary btn-sm">{{ $old ? 'Save new rate' : 'Add rate' }}</button>
    </div>
</form>
