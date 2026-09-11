<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $localeDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $activeTheme?->site_name ?? 'SPIMS')</title>
    @stack('head')
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
    <link href="{{ asset('css/spims-shell.css') }}" rel="stylesheet">
    @if(request()->routeIs('home') || request()->routeIs('auth.*') || request()->routeIs('catalog.*'))
        <link href="{{ asset('css/spims-public.css') }}" rel="stylesheet">
    @endif
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
    $themeClass = in_array($cookieTheme ?? 'light', ['light', 'dark', 'system'], true)
        ? $cookieTheme
        : 'light';
    $isPublicHome = request()->routeIs('home');
    $isPublicAuth = request()->routeIs('auth.*');
    $isPublicCatalog = request()->routeIs('catalog.*');
    $logoUrl = null;
    $logoLightUrl = null;
    $logoDarkUrl = null;
    if ($activeTheme) {
        $logoLightUrl = $activeTheme->logo_light_url ?: $activeTheme->logo_dark_url;
        $logoDarkUrl = $activeTheme->logo_dark_url ?: $activeTheme->logo_light_url;
        $logoUrl = $themeClass === 'dark' ? $logoDarkUrl : $logoLightUrl;
    }
    $shellLess = request()->routeIs('home') || request()->routeIs('auth.*') || !auth()->check();
    $guestCanReadSystemDocs = app(\App\Services\SystemDocs\SystemDocsCatalog::class)->canBrowse($navUser);
    $userInitials = '';
    if ($navUser) {
        $firstInitial = mb_substr(trim((string) $navUser->first_name), 0, 1);
        $lastInitial = mb_substr(trim((string) $navUser->last_name), 0, 1);
        $userInitials = mb_strtoupper($firstInitial.$lastInitial);
        if ($userInitials === '') {
            $userInitials = mb_strtoupper(mb_substr((string) $navUser->email, 0, 1));
        }
    }
@endphp
<body class="theme-{{ $themeClass }} {{ $shellLess ? 'shell-guest' : 'shell-app' }}{{ $isPublicHome ? ' spims-public-home' : '' }}{{ $isPublicAuth ? ' spims-public-auth' : '' }}{{ $isPublicCatalog ? ' spims-public-catalog' : '' }}">
    <a class="spims-skip-link" href="#main-content">{{ __('ui.skip_to_content') }}</a>

    @if($shellLess)
        <nav class="navbar navbar-expand-lg app-nav spims-nav spims-public-nav sticky-top" aria-label="{{ __('ui.nav_dashboard') }}">
            <div class="container-xl d-flex flex-wrap align-items-center gap-2">
                <a class="navbar-brand spims-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="spims-brand-logo" decoding="async">
                    @endif
                    <span>{{ $activeTheme?->site_name ?? 'SPIMS' }}</span>
                </a>
                @if($isPublicHome)
                    <div class="spims-public-nav-links">
                        <a href="#programs">{{ __('home.nav_programs') }}</a>
                        <a href="#admissions">{{ __('home.nav_admissions') }}</a>
                        <a href="#academics">{{ __('home.nav_academics') }}</a>
                        <a href="#spiritual">{{ __('home.nav_spiritual') }}</a>
                        @if($guestCanReadSystemDocs)
                            <a href="{{ route('system-docs.index') }}">{{ __('system_docs.nav') }}</a>
                        @endif
                    </div>
                @endif
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-outline-primary d-none d-lg-inline-flex">{{ __('ui.home_cta_catalog') }}</a>
                    @if($guestCanReadSystemDocs)
                        <a href="{{ route('system-docs.index') }}" class="btn btn-sm btn-outline-primary d-none d-lg-inline-flex">{{ __('system_docs.nav') }}</a>
                    @endif
                    @guest
                        <a href="{{ route('auth.login') }}" class="btn btn-sm btn-outline-primary">{{ __('ui.login') }}</a>
                        <a href="{{ route('auth.register') }}" class="btn btn-sm btn-primary">{{ __('ui.register') }}</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary">{{ __('ui.home_cta_dashboard') }}</a>
                    @endguest
                    <form method="POST" action="{{ route('locale.update') }}" class="d-inline">
                        @csrf
                        <label class="visually-hidden" for="locale-select">{{ __('ui.locale') }}</label>
                        <select id="locale-select" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                    @include('partials.theme-toggle')
                </div>
            </div>
        </nav>
        <main id="main-content" class="{{ ($isPublicHome || $isPublicAuth) ? 'spims-public-main' : 'container py-4' }}" tabindex="-1">
            @if($isPublicAuth)
                <div class="spims-auth-split">
                    @include('auth.partials.brand-panel')
                    <div class="spims-auth-form-col">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                </div>
            @else
                @include('partials.flash')
                @yield('content')
            @endif
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
                                <a href="{{ route($item['route']) }}"
                                   class="app-side-link {{ $item['active'] ? 'active' : '' }} {{ ($item['tone'] ?? '') === 'superadmin' ? 'app-side-link-superadmin' : '' }}">
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
                                <a href="{{ route($item['route']) }}"
                                   class="app-side-link {{ $item['active'] ? 'active' : '' }} {{ ($item['tone'] ?? '') === 'superadmin' ? 'app-side-link-superadmin' : '' }}"
                                   data-bs-dismiss="offcanvas">
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="app-main-column">
                @include('partials.demo-banner')
                @include('partials.impersonation-banner')
                <header class="app-topbar sticky-top" aria-label="{{ __('ui.nav_dashboard') }}">
                    <button class="btn btn-outline-secondary app-menu-btn d-lg-none" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#appDrawer" aria-controls="appDrawer"
                            aria-label="{{ __('ui.open_menu') }}">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <form method="GET" action="{{ route('catalog.index') }}" class="app-topbar-search" role="search">
                        <label class="visually-hidden" for="app-shell-search">{{ __('ui.search') }}</label>
                        <input id="app-shell-search" type="search" name="q" value="{{ request('q') }}"
                               class="form-control app-topbar-search-input"
                               placeholder="{{ __('catalog.search_placeholder') }}"
                               autocomplete="off">
                    </form>
                    <div class="app-topbar-spacer"></div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if($hasSuperadminNav)
                            <a href="{{ route('superadmin.index') }}"
                               class="btn btn-sm btn-outline-danger app-icon-btn sa-topbar-entry {{ request()->routeIs('superadmin.*') || request()->routeIs('roles.hub') ? 'active' : '' }}"
                               title="{{ __('superadmin.entrance_title') }}">
                                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                                <span class="d-none d-md-inline">{{ __('hubs.nav_superadmin') }}</span>
                            </a>
                        @endif
                        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary position-relative app-icon-btn" title="{{ __('ui.nav_notifications') }}" aria-label="{{ __('ui.nav_notifications') }}">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            @if($unreadCount > 0)
                                <span class="app-unread-dot" aria-label="{{ __('ui.unread_count', ['count' => $unreadCount]) }}"></span>
                            @endif
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle app-user-menu-btn" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('ui.user_menu', ['name' => $navUser->first_name]) }}">
                                <span class="app-avatar" aria-hidden="true">{{ $userInitials }}</span>
                                <span class="app-user-firstname">{{ $navUser->first_name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('dashboard') }}">{{ __('ui.nav_dashboard') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('catalog.index') }}">{{ __('ui.nav_catalog') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('applications.index') }}">{{ __('ui.nav_my_applications') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('enrollments.index') }}">{{ __('ui.nav_enrollments') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('grades.index') }}">{{ __('ui.nav_grades') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('settings.edit') }}">{{ __('ui.nav_settings') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('transcript.show') }}">{{ __('ui.nav_transcript') }}</a></li>
                                @if(app(\App\Support\AuthorizeService::class)->allows(auth()->user(), 'users.manage'))
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.users.index') }}">
                                            <i class="bi bi-people" aria-hidden="true"></i>
                                            {{ __('people.nav_people') }}
                                        </a>
                                    </li>
                                @endif
                                @if($hasSuperadminNav)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('superadmin.audit.index') }}">
                                            <i class="bi bi-journal-text" aria-hidden="true"></i>
                                            {{ __('audit.nav_audit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('superadmin.index') }}">
                                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                                            {{ __('superadmin.entrance_title') }}
                                        </a>
                                    </li>
                                @endif
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
                            <select id="locale-select" name="locale" class="form-select form-select-sm app-topbar-select" onchange="this.form.submit()">
                                @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                    <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        @include('partials.theme-toggle')
                    </div>
                </header>

                <main id="main-content" class="app-content container-fluid py-4" tabindex="-1">
                    @include('partials.flash')
                    @include('partials.announcement-banner')
                    @yield('content')
                </main>
            </div>

            <nav class="app-bottom-nav d-lg-none" aria-label="{{ __('hubs.nav_primary') }}">
                @foreach($bottomNav as $item)
                    <a href="{{ route($item['route']) }}" class="app-bottom-link {{ $item['active'] ? 'active' : '' }} {{ ($item['tone'] ?? '') === 'superadmin' ? 'app-bottom-link-superadmin' : '' }}">
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
