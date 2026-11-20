<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Sign in – DutyCalc admin</title>
    <link rel="stylesheet" href="{{ asset('css/dutycalc.css') }}?v={{ filemtime(public_path('css/dutycalc.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="login-wrap">
    <main class="login" id="main">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true" style="width: 44px; height: 44px; border-radius: 12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 7h8M8 11h8M8 15h5"></path></svg>
            </span>
            <span>
                <span class="brand-name" style="font-size: 20px">DutyCalc admin</span>
                <span class="brand-tag">Tariff data, rates and the change log</span>
            </span>
        </div>

        <form class="card" data-api="{{ route('api.admin.login') }}" data-success="{{ route('admin.lines') }}" novalidate>
            <h1>Sign in</h1>
            <ul class="form-errors" data-form-errors hidden></ul>
            <div class="field" data-field="email">
                <label for="email">Email</label>
                <input class="input" id="email" name="email" type="email" autocomplete="username" required>
                <p class="field-error" data-error-for="email" hidden></p>
            </div>
            <div class="field" data-field="password">
                <label for="password">Password</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
                <p class="field-error" data-error-for="password" hidden></p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            <p class="hint">There is no sign-up. Admin accounts are created by the developer. After 5 wrong tries, wait a minute before trying again.</p>
        </form>

        <a href="{{ route('home') }}" class="strong small" style="align-self: center">Back to the trader site</a>
    </main>
</div>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
</body>
</html>
