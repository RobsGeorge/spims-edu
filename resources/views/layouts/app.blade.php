<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $localeDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $activeTheme?->site_name ?? 'SPIMS')</title>
    @if(!empty($activeTheme?->favicon_url))
        <link rel="icon" href="{{ $activeTheme->favicon_url }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if(($localeDir ?? 'ltr') === 'rtl')
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @if(($localeDir ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <link href="{{ asset('css/spims-theme.css') }}" rel="stylesheet">
    @if(!empty($themeCssBlock))
        <style id="spims-theme-tokens">{!! $themeCssBlock !!}</style>
    @endif
    @stack('styles')
</head>
@php
    use App\Support\NavigationHub;
    $navUser = auth()->user();
    $primaryNav = NavigationHub::primaryNav($navUser);
    $bottomNav = NavigationHub::bottomNav($navUser);
    $unreadCount = NavigationHub::unreadNotificationCount($navUser);
    $hasSuperadminNav = NavigationHub::hasSuperadmin($navUser);
    $themeClass = in_array($cookieTheme ?? 'system', ['light', 'dark', 'system'], true)
        ? $cookieTheme
        : 'system';
    $logoUrl = null;
    if ($activeTheme) {
        $logoUrl = $themeClass === 'dark'
            ? ($activeTheme->logo_dark_url ?: $activeTheme->logo_light_url)
            : ($activeTheme->logo_light_url ?: $activeTheme->logo_dark_url);
    }
    $shellLess = request()->routeIs('home') || request()->routeIs('auth.*') || !auth()->check();
@endphp
<body class="theme-{{ $themeClass }} {{ $shellLess ? 'shell-guest' : 'shell-app' }}">
    <a class="spims-skip-link" href="#main-content">{{ __('ui.skip_to_content') }}</a>

    @if($shellLess)
        <nav class="navbar navbar-expand-lg app-nav spims-nav sticky-top" aria-label="{{ __('ui.nav_dashboard') }}">
            <div class="container">
                <a class="navbar-brand spims-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="spims-brand-logo" decoding="async">
                    @endif
                    <span>{{ $activeTheme?->site_name ?? 'SPIMS' }}</span>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="{{ route('auth.login') }}" class="btn btn-sm btn-outline-primary">{{ __('ui.login') }}</a>
                    <a href="{{ route('auth.register') }}" class="btn btn-sm btn-primary">{{ __('ui.register') }}</a>
                    <form method="POST" action="{{ route('locale.update') }}" class="d-inline">
                        @csrf
                        <label class="visually-hidden" for="locale-select">{{ __('ui.locale') }}</label>
                        <select id="locale-select" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </nav>
        <main id="main-content" class="container py-4" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    @else
        <div class="app-shell" id="app-shell">
            <aside class="app-sidebar d-none d-lg-flex" aria-label="{{ __('hubs.nav_primary') }}">
                <a class="spims-brand app-sidebar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                    @if($hasSuperadminNav && request()->routeIs('superadmin.*'))
                        <i class="bi bi-shield-lock-fill text-danger" aria-hidden="true"></i>
                    @endif
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="spims-brand-logo" decoding="async">
                    @endif
                    <span>{{ $activeTheme?->site_name ?? 'SPIMS' }}</span>
                </a>
                <nav class="app-sidebar-nav flex-grow-1">
                    <ul class="list-unstyled mb-0">
                        @foreach($primaryNav as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" class="app-side-link {{ $item['active'] ? 'active' : '' }}">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            <div class="offcanvas offcanvas-start app-drawer" tabindex="-1" id="appDrawer" aria-labelledby="appDrawerLabel">
                <div class="offcanvas-header">
                    <h2 class="offcanvas-title h5 spims-brand mb-0" id="appDrawerLabel">{{ $activeTheme?->site_name ?? 'SPIMS' }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($primaryNav as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" class="app-side-link {{ $item['active'] ? 'active' : '' }}" data-bs-dismiss="offcanvas">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="app-main-column">
                <header class="app-topbar sticky-top" aria-label="{{ __('ui.nav_dashboard') }}">
                    <button class="btn btn-outline-secondary app-menu-btn d-lg-none" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#appDrawer" aria-controls="appDrawer"
                            aria-label="{{ __('ui.open_menu') }}">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div class="app-topbar-spacer"></div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary position-relative app-icon-btn" title="{{ __('ui.nav_notifications') }}">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            @if($unreadCount > 0)
                                <span class="app-unread-dot" aria-label="{{ __('ui.unread_count', ['count' => $unreadCount]) }}"></span>
                            @endif
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ auth()->user()->first_name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('dashboard') }}">{{ __('ui.nav_dashboard') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('catalog.index') }}">{{ __('ui.nav_catalog') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('grades.index') }}">{{ __('ui.nav_grades') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('settings.edit') }}">{{ __('ui.nav_settings') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('transcript.show') }}">{{ __('ui.nav_transcript') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('auth.logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">{{ __('ui.logout') }}</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        <form method="POST" action="{{ route('locale.update') }}" class="d-inline">
                            @csrf
                            <label class="visually-hidden" for="locale-select">{{ __('ui.locale') }}</label>
                            <select id="locale-select" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                    <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('theme.update') }}" class="d-inline">
                            @csrf
                            <label class="visually-hidden" for="theme-select">{{ __('ui.theme') }}</label>
                            <select id="theme-select" name="theme" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="light" @selected(($cookieTheme ?? 'system') === 'light')>{{ __('ui.theme_light') }}</option>
                                <option value="dark" @selected(($cookieTheme ?? 'system') === 'dark')>{{ __('ui.theme_dark') }}</option>
                                <option value="system" @selected(($cookieTheme ?? 'system') === 'system')>{{ __('ui.theme_system') }}</option>
                            </select>
                        </form>
                    </div>
                </header>

                <main id="main-content" class="app-content container-fluid py-4" tabindex="-1">
                    @include('partials.flash')
                    @yield('content')
                </main>
            </div>

            <nav class="app-bottom-nav d-lg-none" aria-label="{{ __('hubs.nav_primary') }}">
                @foreach($bottomNav as $item)
                    <a href="{{ route($item['route']) }}" class="app-bottom-link {{ $item['active'] ? 'active' : '' }}">
                        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    @stack('scripts')
</body>
</html>
