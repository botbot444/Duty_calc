<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B6E4F">
    <title>@yield('title', __('DutyCalc'))</title>
    <link rel="stylesheet" href="{{ asset('css/dutycalc.css') }}?v={{ filemtime(public_path('css/dutycalc.css')) }}">
</head>
<body>
    <header class="app-header">
        <div class="app-header-inner">
            <div class="brand-row">
                <a class="brand" href="{{ route('home') }}" data-new-estimate>
                    <span class="brand-mark" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 7h8M8 11h8M8 15h5"></path></svg>
                    </span>
                    <span>
                        <span class="brand-name">{{ __('DutyCalc') }}</span>
                        <span class="brand-tag">{{ __('Know your border charges before you travel') }}</span>
                    </span>
                </a>
            </div>
            <nav class="lang-switch" aria-label="{{ __('Language') }}">
                @foreach (['en' => 'English', 'bem' => 'Ichibemba', 'sw' => 'Kiswahili'] as $code => $name)
                    <a href="{{ route('language', $code) }}" lang="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $name }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="page" id="main">
        @yield('content')
    </main>

    <script>
        // Keep the trader's place (path and #screen) when switching language.
        document.querySelectorAll('.lang-switch a').forEach(function (a) {
            a.addEventListener('click', function () {
                a.href = a.href.split('?')[0] + '?to=' + encodeURIComponent(location.pathname + location.hash);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
