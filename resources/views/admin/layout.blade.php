<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title') – DutyCalc admin</title>
    <link rel="stylesheet" href="{{ asset('css/dutycalc.css') }}?v={{ filemtime(public_path('css/dutycalc.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
@php
    $nav = [
        'admin.lines' => 'Tariff lines',
        'admin.import' => 'Import CSV',
        'admin.rates' => 'Exchange rates',
        'admin.catalogue' => 'Catalogue',
        'admin.reference' => 'Routes and sources',
        'admin.log' => 'Change log',
    ];
    $current = request()->route()?->getName();
    if ($current === 'admin.line') {
        $current = 'admin.lines';
    }
@endphp
<div class="admin">
    <nav class="admin-nav" aria-label="Admin">
        <a class="admin-brand" href="{{ route('admin.lines') }}">
            <span class="brand-mark" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 7h8M8 11h8M8 15h5"></path></svg>
            </span>
            DutyCalc admin
        </a>
        @foreach ($nav as $name => $label)
            <a href="{{ route($name) }}" @if ($current === $name) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <div class="admin-user">
            <span class="muted">Signed in as</span>
            <span class="strong">{{ auth()->user()->email }}</span>
            <button type="button" class="link-btn" data-action data-api="{{ route('api.admin.logout') }}" data-success="{{ route('admin.login') }}">Sign out</button>
            <a href="{{ route('home') }}" class="small">Trader site</a>
        </div>
    </nav>

    <main class="admin-main" id="main">
        <div class="alert alert-dark" id="admin-notice" role="alert" hidden>
            <span class="grow" id="admin-notice-text"></span>
            <button type="button" id="admin-notice-close">Close</button>
        </div>

        @if ($uncheckedSources->isNotEmpty())
            <div class="alert alert-warn" role="note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8A6400" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16.5v.5"></path></svg>
                <span>Rates in use come from sources nobody has checked yet: {{ $uncheckedSources->implode('; ') }}.
                    Do not rely on them for real estimates. <a href="{{ route('admin.reference') }}">Record who checked a source</a>.</span>
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
</body>
</html>
