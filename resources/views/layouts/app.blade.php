<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $localeDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $activeTheme?->site_name ?? 'SPIMS')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    @if(($localeDir ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="{{ asset('css/spims-theme.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="theme-{{ $cookieTheme === 'system' ? 'dark' : $cookieTheme }}">
    <nav class="navbar navbar-expand-lg spims-nav">
        <div class="container">
            <a class="navbar-brand spims-brand" href="{{ route('home') }}">
                {{ $activeTheme?->site_name ?? 'SPIMS' }}
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <form method="POST" action="{{ route('locale.update') }}" class="d-inline">
                    @csrf
                    <select name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                <form method="POST" action="{{ route('theme.update') }}" class="d-inline">
                    @csrf
                    <select name="theme" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="light" @selected(($cookieTheme ?? 'dark') === 'light')>{{ __('ui.theme_light') }}</option>
                        <option value="dark" @selected(($cookieTheme ?? 'dark') === 'dark')>{{ __('ui.theme_dark') }}</option>
                        <option value="system" @selected(($cookieTheme ?? 'dark') === 'system')>{{ __('ui.theme_system') }}</option>
                    </select>
                </form>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
