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
    $hasAcademicNav = $navUser && count(NavigationHub::academicLinks($navUser)) > 0;
    $hasAdminNav = $navUser && count(NavigationHub::adminLinks($navUser)) > 0;
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
@endphp
<body class="theme-{{ $themeClass }}">
    <a class="spims-skip-link" href="#main-content">{{ __('ui.skip_to_content') }}</a>
    <nav class="navbar navbar-expand-lg app-nav spims-nav sticky-top" aria-label="{{ __('ui.nav_dashboard') }}">
        <div class="container">
            <a class="navbar-brand spims-brand d-flex align-items-center gap-2" href="{{ auth()->check() ? route('dashboard') : route('home') }}">
                @if($hasSuperadminNav && request()->routeIs('superadmin.*'))
                    <i class="bi bi-shield-lock-fill text-danger" aria-hidden="true"></i>
                @endif
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="spims-brand-logo" decoding="async">
                @endif
                <span>{{ $activeTheme?->site_name ?? 'SPIMS' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#spimsNav" aria-controls="spimsNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="spimsNav">
                @auth
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
                        <li class="nav-item">
                            <a class="nav-link app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-house"></i> {{ __('hubs.nav_home') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link app-nav-link {{ request()->routeIs('hubs.learning') ? 'active' : '' }}" href="{{ route('hubs.learning') }}">
                                <i class="bi bi-book-half"></i> {{ __('hubs.nav_learning') }}
                            </a>
                        </li>
                        @if($hasAcademicNav)
                            <li class="nav-item">
                                <a class="nav-link app-nav-link {{ request()->routeIs('hubs.academic') ? 'active' : '' }}" href="{{ route('hubs.academic') }}">
                                    <i class="bi bi-mortarboard"></i> {{ __('hubs.nav_academic') }}
                                </a>
                            </li>
                        @endif
                        @if($hasAdminNav)
                            <li class="nav-item">
                                <a class="nav-link app-nav-link {{ request()->routeIs('hubs.admin') ? 'active' : '' }}" href="{{ route('hubs.admin') }}">
                                    <i class="bi bi-gear"></i> {{ __('hubs.nav_admin') }}
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link app-nav-link {{ request()->routeIs('hubs.finance') ? 'active' : '' }}" href="{{ route('hubs.finance') }}">
                                <i class="bi bi-wallet2"></i> {{ __('hubs.nav_finance') }}
                            </a>
                        </li>
                        @if($hasSuperadminNav)
                            <li class="nav-item dropdown">
                                <a class="nav-link app-nav-link dropdown-toggle {{ request()->routeIs('superadmin.*') ? 'active' : '' }}"
                                   href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                                    {{ __('hubs.nav_superadmin') }}
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('superadmin.index') }}">
                                            @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                                            {{ __('superadmin.title') }}
                                        </a>
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('roles.hub') }}">{{ __('roles_hub.title') }}</a></li>
                                    <li><a class="dropdown-item" href="{{ route('superadmin.security') }}">{{ __('superadmin.tile_security') }}</a></li>
                                    <li><a class="dropdown-item" href="{{ route('superadmin.audit.index') }}">{{ __('superadmin.tile_audit') }}</a></li>
                                    <li><a class="dropdown-item" href="{{ route('superadmin.observability.index') }}">{{ __('superadmin.tile_observability') }}</a></li>
                                </ul>
                            </li>
                        @endif
                    </ul>
                @endauth

                <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                    @auth
                        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('ui.nav_notifications') }}">
                            <i class="bi bi-bell"></i>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ auth()->user()->first_name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('dashboard') }}">{{ __('ui.nav_dashboard') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('catalog.index') }}">{{ __('ui.nav_catalog') }}</a></li>
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
                    @else
                        <a href="{{ route('auth.login') }}" class="btn btn-sm btn-outline-primary">{{ __('ui.login') }}</a>
                        <a href="{{ route('auth.register') }}" class="btn btn-sm btn-primary">{{ __('ui.register') }}</a>
                    @endauth
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
            </div>
        </div>
    </nav>

    <main id="main-content" class="container py-4" tabindex="-1">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
