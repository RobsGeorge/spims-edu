<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $localeDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $activeTheme?->site_name ?? 'SPIMS')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    @if(($localeDir ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
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
                @auth
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            {{ auth()->user()->first_name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach(\App\Support\Navigation::linksFor(auth()->user()) as $link)
                                <li><a class="dropdown-item" href="{{ route($link['route']) }}">{{ $link['label'] }}</a></li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">{{ __('ui.logout') }}</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('auth.login') }}" class="btn btn-sm btn-outline-primary">{{ __('ui.login') }}</a>
                    <a href="{{ route('auth.register') }}" class="btn btn-sm btn-primary">{{ __('ui.register') }}</a>
                @endauth
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
