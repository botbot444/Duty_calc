@extends('layouts.app')

@php
    $first = $routes->first();
    $routeChip = $first === null ? null
        : ($routes->count() === 1
            ? "{$first['origin']['name']} → {$first['destination']['name']} · {$first['border_post']}"
            : "{$first['origin']['name']} → {$first['destination']['name']}");

    // Every string the page script shows. Placeholders (:name) are filled in by the script.
    $strings = collect([
        'Type a word to search.', 'Searching…', 'Checking your photo…',
        "We couldn't identify this photo. Try typing the product name.",
        'This file is not a photo. Use a JPEG, PNG or WebP image.',
        'This photo is too large. The limit is :size MB.',
        "We couldn't find a match. Try another word or check the spelling.",
        "We couldn't find a reliable match. Try describing the product in a different way.",
        'Smart matching is not available right now, so these are plain search results.',
        'Good match', 'Possible match', 'Weak match',
        'Choose', 'HS code', 'Official description', 'Your answer',
        'Something went wrong. Please try again.',
        'No internet connection. Check your connection and try again.',
        'This page has expired. Reload the page and try again.',
        'Please choose and confirm a product first.',
        'Some details need fixing. Check the boxes marked in red.',
        'Working out your charges…', 'Get my estimate', 'Please wait…',
        'Amount in :unit_label', 'Going from: :country', 'Try again', 'Start again',
        'kilograms', 'litres', 'units (pieces)',
    ])->mapWithKeys(fn ($s) => [$s => __($s)]);

    $config = [
        'locale' => app()->getLocale(),
        'routes' => $routes,
        'currencies' => $currencies,
        'photoMaxMb' => \App\Http\Requests\IdentifyImageRequest::MAX_KB / 1024,
        'urls' => [
            'identifyText' => route('api.identify.text'),
            'identifyImage' => route('api.identify.image'),
            'clarify' => route('api.identify.clarify'),
            'selection' => route('api.selection.store'),
            'estimates' => route('api.estimates.store'),
        ],
        'strings' => $strings,
    ];
@endphp

@section('content')
    <div id="notice" class="alert alert-dark" role="alert" hidden>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v6M12 16.5v.5"></path></svg>
        <span class="grow" id="notice-text"></span>
        <button type="button" id="notice-action">{{ __('Try again') }}</button>
    </div>

    {{-- 1. Search --------------------------------------------------------- --}}
    <section class="screen" data-screen="search" aria-labelledby="search-title">
        <div class="stack-lg">
            <h1 class="big" id="search-title">{{ __('What are you taking out of Zambia?') }}</h1>
            @if ($routeChip)
                <span class="route-chip">{{ $routeChip }}</span>
            @endif
        </div>

        <form class="card" id="search-form" novalidate>
            <label for="q">{{ __('Type the name of the product') }}</label>
            <div class="field" id="search-field">
                <div class="input-search">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#55605A" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
                    <input id="q" name="q" type="search" maxlength="300" autocomplete="off" enterkeyhint="search"
                           placeholder="{{ __('For example: sugar, rice, phone charger') }}" aria-describedby="q-error">
                </div>
                <p class="field-error" id="q-error" hidden></p>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="search-btn">{{ __('Search') }}</button>
        </form>

        <div class="card" id="photo-status" role="status" hidden>
            <div class="status-line"><span class="spinner" aria-hidden="true"></span><span>{{ __('Checking your photo…') }}</span></div>
            <p class="hint">{{ __('Your photo is deleted as soon as it has been checked.') }}</p>
        </div>
        <div class="alert alert-error" id="photo-error" role="alert" hidden>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B42318" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v6M12 16.5v.5"></path></svg>
            <span id="photo-error-text"></span>
        </div>

        <div class="divider-or">{{ __('or') }}</div>

        <div class="photo-grid">
            <label class="photo-btn">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0B6E4F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"></path><circle cx="12" cy="13" r="3.5"></circle></svg>
                {{ __('Take a photo') }}
                <input type="file" accept="image/*" capture="environment" data-photo-input>
            </label>
            <label class="photo-btn">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0B6E4F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="M21 16l-5-5-9 9"></path></svg>
                {{ __('Choose a photo') }}
                <input type="file" accept="image/jpeg,image/png,image/webp,image/*" data-photo-input>
            </label>
        </div>

        <div class="stack small muted">
            <p>{{ __('Take the photo in good light, with the product filling the picture.') }}</p>
            <p>{{ __('Your photo is deleted as soon as it has been checked.') }}</p>
        </div>
    </section>

    {{-- 2. Suggestions ---------------------------------------------------- --}}
    <section class="screen" data-screen="suggestions" aria-labelledby="suggest-title" hidden>
        <button type="button" class="back-link" data-go="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
            {{ __('Back') }}
        </button>
        <div class="stack">
            <h1 id="suggest-title" tabindex="-1">{{ __('Is it one of these?') }}</h1>
            <p class="lead">{{ __('Choose the product that matches yours.') }}</p>
        </div>
        <span class="query-chip" id="query-chip" hidden>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#55605A" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
            <span id="query-text"></span>
        </span>

        <div class="alert alert-info" id="fallback-note" role="status" hidden>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2F5D8C" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 7.5v.5"></path></svg>
            <span>{{ __('Smart matching is not available right now, so these are plain search results.') }}</span>
        </div>

        <div class="stack-lg" id="suggestion-list"></div>

        <div class="card empty" id="no-match" hidden>
            <span class="empty-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#55605A" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5M8.5 8.5l5 5M13.5 8.5l-5 5"></path></svg>
            </span>
            <p class="strong" id="no-match-text"></p>
            <button type="button" class="btn btn-primary btn-block" data-go="search">{{ __('Try again') }}</button>
        </div>

        <button type="button" class="btn btn-link" id="none-of-these" data-go="search">{{ __('None of these') }}</button>
    </section>

    {{-- 2b. Clarifying question ------------------------------------------- --}}
    <section class="screen" data-screen="clarify" aria-labelledby="clarify-title" hidden>
        <button type="button" class="back-link" data-go="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
            {{ __('Back') }}
        </button>
        <div class="stack">
            <h1 id="clarify-title" tabindex="-1">{{ __('We need a little more detail') }}</h1>
            <p class="lead">{{ __('Answer the question below so we can find the right product.') }}</p>
        </div>
        <form class="card" id="clarify-form" novalidate>
            <div class="stack-lg" id="clarify-questions"></div>
            <button type="submit" class="btn btn-primary btn-block" id="clarify-btn">{{ __('Send answer') }}</button>
        </form>
    </section>

    {{-- 3. Confirm -------------------------------------------------------- --}}
    <section class="screen" data-screen="confirm" aria-labelledby="confirm-title" hidden>
        <button type="button" class="back-link" data-go="suggestions">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
            {{ __('Back') }}
        </button>
        <h1 id="confirm-title" tabindex="-1">{{ __('Confirm your product') }}</h1>

        <section class="card card-flush">
            <div class="stack" style="padding: 18px; border-bottom: 1px solid var(--line-soft)">
                <span class="muted">{{ __('You chose:') }}</span>
                <span class="card-title" id="confirm-name"></span>
            </div>
            <dl class="facts" style="padding: 16px 18px">
                <dt>{{ __('HS code') }}</dt><dd class="num strong" id="confirm-hs"></dd>
                <dt>{{ __('Official description') }}</dt><dd id="confirm-desc"></dd>
            </dl>
        </section>

        <section class="stack" id="confirm-answers" hidden>
            <h2>{{ __('Your answers') }}</h2>
            <div class="card" id="confirm-answer-list"></div>
        </section>

        <div class="alert alert-warn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8A6400" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16.5v.5"></path></svg>
            <span>{{ __('The estimate depends on choosing the right product. If you are not sure, ask a clearing agent.') }}</span>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-primary" id="confirm-yes">{{ __('Yes, this is my product') }}</button>
            <button type="button" class="btn btn-secondary" data-go="suggestions">{{ __('No, show the list again') }}</button>
        </div>
    </section>

    {{-- 4. Trade details -------------------------------------------------- --}}
    <section class="screen" data-screen="details" aria-labelledby="details-title" hidden>
        <button type="button" class="back-link" data-go="confirm">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
            {{ __('Back') }}
        </button>
        <div class="stack">
            <h1 id="details-title" tabindex="-1">{{ __('Trade details') }}</h1>
            <span class="muted small" id="details-product"></span>
        </div>

        <div class="alert alert-error" id="details-error" role="alert" hidden>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B42318" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v6M12 16.5v.5"></path></svg>
            <span id="details-error-text"></span>
        </div>

        <form id="details-form" class="stack-lg" novalidate>
            <section class="card">
                <h2>{{ __('Where are the goods going?') }}</h2>
                <span class="muted" id="from-line"></span>
                <div class="field">
                    <label for="f-destination">{{ __('Country the goods go to') }}</label>
                    <select class="input" id="f-destination"></select>
                </div>
                <div class="field" data-field="trade_route_id">
                    <label for="f-route">{{ __('Border post') }}</label>
                    <select class="input" id="f-route" name="trade_route_id" aria-describedby="e-trade_route_id"></select>
                    <p class="field-error" id="e-trade_route_id" hidden></p>
                </div>
            </section>

            <section class="card">
                <div class="row-value">
                    <div class="field" data-field="declared_value">
                        <label for="f-value">{{ __('Value of the goods') }}</label>
                        <input class="input num" id="f-value" name="declared_value" inputmode="decimal" autocomplete="off" aria-describedby="h-value e-declared_value">
                    </div>
                    <div class="field" data-field="currency">
                        <label for="f-currency">{{ __('Currency') }}</label>
                        <select class="input" id="f-currency" name="currency" aria-describedby="e-currency">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="hint" id="h-value">{{ __('The amount you paid for the goods') }}</p>
                <p class="field-error" id="e-declared_value" hidden></p>
                <p class="field-error" id="e-currency" hidden></p>

                <div id="cost-fields" class="stack-lg" hidden>
                    <div class="grid2">
                        <div class="field" data-field="freight" id="freight-field" hidden>
                            <label for="f-freight">{{ __('Freight (transport cost)') }}</label>
                            <input class="input num" id="f-freight" name="freight" inputmode="decimal" autocomplete="off" aria-describedby="e-freight">
                            <p class="field-error" id="e-freight" hidden></p>
                        </div>
                        <div class="field" data-field="insurance" id="insurance-field" hidden>
                            <label for="f-insurance">{{ __('Insurance') }}</label>
                            <input class="input num" id="f-insurance" name="insurance" inputmode="decimal" autocomplete="off" aria-describedby="e-insurance">
                            <p class="field-error" id="e-insurance" hidden></p>
                        </div>
                    </div>
                    <p class="hint">{{ __('Enter 0 if there is none.') }} {{ __('Use the same currency for the value, freight and insurance.') }}</p>
                </div>

                <div class="field section-sep" data-field="quantity" id="quantity-field" hidden>
                    <label for="f-quantity">{{ __('Quantity') }}</label>
                    <div class="input-unit">
                        <input id="f-quantity" name="quantity" inputmode="decimal" autocomplete="off" aria-describedby="h-quantity e-quantity">
                        <span class="unit" id="quantity-unit"></span>
                    </div>
                    <p class="hint" id="h-quantity"></p>
                    <p class="field-error" id="e-quantity" hidden></p>
                </div>
            </section>

            <button type="submit" class="btn btn-primary btn-block" id="estimate-btn">{{ __('Get my estimate') }}</button>
        </form>
    </section>

    <noscript>
        <p class="alert alert-warn">{{ __('Something went wrong. Please try again.') }}</p>
    </noscript>
@endsection

@push('scripts')
    <script>
        window.DutyCalc = {{ Illuminate\Support\Js::from($config) }};
    </script>
    <script src="{{ asset('js/dutycalc.js') }}?v={{ filemtime(public_path('js/dutycalc.js')) }}" defer></script>
@endpush
