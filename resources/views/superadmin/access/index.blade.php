@extends('layouts.app')

@section('title', __('access.page_title'))

@section('content')
<div class="sa-access hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <nav class="mb-3 small" aria-label="{{ __('access.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('access.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('access.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('access.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('access.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('access.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('access.danger_title') }}</strong>
            <p class="mb-0">{{ __('access.danger_body') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('access.readonly_title') }}</strong>
            <p class="mb-0">{{ __('access.readonly_body') }}</p>
        </div>
    </aside>

    <div class="row g-3 mb-4" data-access-totals>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="h4 page-title mb-0">{{ number_format($totals['keys']) }}</div>
                    <div class="small text-muted-theme text-uppercase">{{ __('access.totals_keys') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="h4 page-title mb-0">{{ number_format($totals['exclusive']) }}</div>
                    <div class="small text-muted-theme text-uppercase">{{ __('access.totals_exclusive') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100" data-stat="leaks">
                <div class="card-body text-center">
                    <div class="h4 page-title mb-0">{{ number_format($totals['leaks']) }}</div>
                    <div class="small text-muted-theme text-uppercase">{{ __('access.totals_leaks') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100" data-stat="elevated">
                <div class="card-body text-center">
                    <div class="h4 page-title mb-0">{{ number_format($totals['elevated']) }}</div>
                    <div class="small text-muted-theme text-uppercase">{{ __('access.totals_elevated') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100" data-stat="drifted">
                <div class="card-body text-center">
                    <div class="h4 page-title mb-0">{{ number_format($totals['drifted']) }}</div>
                    <div class="small text-muted-theme text-uppercase">{{ __('access.totals_drifted') }}</div>
                </div>
            </div>
        </div>
    </div>

    <p class="small mb-4">
        <a href="#access-census">{{ __('access.census_title') }}</a>
        · <a href="#access-leaks">{{ __('access.leaks_title') }}</a>
        · <a href="#access-elevated">{{ __('access.elevated_title') }}</a>
        · <a href="#access-exclusive">{{ __('access.exclusive_title') }}</a>
        · <a href="#access-lookup">{{ __('access.lookup_title') }}</a>
    </p>

    <section class="app-card card shadow-sm mb-4" id="access-census" aria-labelledby="access-census-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="access-census-heading">{{ __('access.census_title') }}</h2>
            <p class="text-muted-theme">{{ __('access.census_help') }}</p>

            <article class="app-card card shadow-sm mb-3" data-role="SUPER_ADMIN">
                <div class="card-body">
                    <h3 class="h6 page-title mb-1">{{ __('access.superadmin_title') }}</h3>
                    <p class="form-text mb-2">{{ __('access.superadmin_help') }}</p>
                    <p class="mb-1">
                        <span class="text-muted-theme">{{ __('access.superadmin_count') }}:</span>
                        <strong>{{ number_format($superadmin['count']) }}</strong>
                    </p>
                    @if($superadmin['emails'] !== [])
                        <ul class="small mb-0">
                            @foreach($superadmin['emails'] as $email)
                                <li><code>{{ $email }}</code></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mb-0">{{ __('access.superadmin_none') }}</p>
                    @endif
                </div>
            </article>

            <div class="row g-3">
                @foreach($roles as $row)
                    <div class="col-md-6">
                        <article class="app-card card shadow-sm h-100" data-role="{{ $row['role'] }}" data-matches="{{ $row['matches_defaults'] ? '1' : '0' }}">
                            <div class="card-body">
                                <h3 class="h6 page-title mb-1">{{ __('roles_hub.role_'.$row['role']) }}</h3>
                                <p class="mb-1">
                                    <span class="text-muted-theme">{{ __('access.users') }}:</span>
                                    <strong>{{ number_format($row['users']) }}</strong>
                                </p>
                                <p class="mb-1">
                                    <span class="text-muted-theme">{{ __('access.granted') }}:</span>
                                    <strong>{{ number_format($row['granted']) }}</strong>
                                    <span class="text-muted-theme">· {{ __('access.default') }}: {{ number_format($row['default']) }}</span>
                                </p>
                                <p class="mb-2">
                                    <span class="sa-flag-state {{ $row['matches_defaults'] ? 'is-on' : 'is-off' }}">
                                        {{ $row['matches_defaults'] ? __('access.matches') : __('access.drifted') }}
                                    </span>
                                </p>
                                @if($row['added'] !== [])
                                    <p class="form-text mb-1">{{ __('access.added') }}</p>
                                    <ul class="small mb-2">
                                        @foreach($row['added'] as $key)
                                            <li><code>{{ $key }}</code></li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($row['removed'] !== [])
                                    <p class="form-text mb-1">{{ __('access.removed') }}</p>
                                    <ul class="small mb-2">
                                        @foreach($row['removed'] as $key)
                                            <li><code>{{ $key }}</code></li>
                                        @endforeach
                                    </ul>
                                @endif
                                <p class="small mb-0">
                                    @if($row['hub_url'])
                                        <a href="{{ $row['hub_url'] }}">{{ __('access.open_hub') }}</a>
                                    @endif
                                    @if($row['directory_url'])
                                        · <a href="{{ $row['directory_url'] }}">{{ __('access.open_directory') }}</a>
                                    @endif
                                </p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="access-leaks" aria-labelledby="access-leaks-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="access-leaks-heading">{{ __('access.leaks_title') }}</h2>
            <p class="text-muted-theme">{{ __('access.leaks_help') }}</p>
            @if($leaks === [])
                <p class="mb-0" data-leaks-empty>{{ __('access.leaks_none') }}</p>
            @else
                <ul class="mb-0">
                    @foreach($leaks as $leak)
                        <li data-leak-key="{{ $leak['key'] }}" data-leak-role="{{ $leak['role'] }}">
                            <code>{{ $leak['key'] }}</code>
                            → {{ __('roles_hub.role_'.$leak['role']) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="access-elevated" aria-labelledby="access-elevated-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="access-elevated-heading">{{ __('access.elevated_title') }}</h2>
            <p class="text-muted-theme">{{ __('access.elevated_help') }}</p>
            @if($elevated === [])
                <p class="mb-0" data-elevated-empty>{{ __('access.elevated_none') }}</p>
            @else
                <ul class="mb-0">
                    @foreach($elevated as $row)
                        <li data-elevated-key="{{ $row['key'] }}" data-elevated-role="{{ $row['role'] }}">
                            <code>{{ $row['key'] }}</code>
                            → {{ __('roles_hub.role_'.$row['role']) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="access-exclusive" aria-labelledby="access-exclusive-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="access-exclusive-heading">{{ __('access.exclusive_title') }}</h2>
            <p class="text-muted-theme">{{ __('access.exclusive_help') }}</p>
            <ul class="list-unstyled mb-0">
                @foreach($exclusive as $item)
                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom border-opacity-25" data-exclusive-key="{{ $item['key'] }}">
                        <span>
                            <code>{{ $item['key'] }}</code>
                            <span class="form-text d-block">{{ __('roles_hub.group_'.$item['group']) }}</span>
                        </span>
                        @if($item['granted_to'] === [])
                            <span class="sa-flag-state is-on">{{ __('access.exclusive_only') }}</span>
                        @else
                            <span class="sa-flag-state is-off">
                                {{ __('access.exclusive_granted') }}:
                                {{ implode(', ', array_map(fn ($role) => __('roles_hub.role_'.$role), $item['granted_to'])) }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="access-lookup" aria-labelledby="access-lookup-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="access-lookup-heading">{{ __('access.lookup_title') }}</h2>
            <p class="text-muted-theme">{{ __('access.lookup_help') }}</p>
            <form method="GET" action="{{ route('superadmin.access') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-8">
                    <label class="form-label" for="access-key-q">{{ __('access.lookup_label') }}</label>
                    <input id="access-key-q" name="q" value="{{ $query }}" class="form-control"
                           placeholder="{{ __('access.lookup_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-primary">{{ __('access.lookup_submit') }}</button>
                </div>
            </form>
            @if($query === '')
                <p class="form-text mb-0">{{ __('access.lookup_need_query') }}</p>
            @elseif($lookup === [])
                <p class="mb-0">{{ __('access.lookup_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('access.col_key') }}</th>
                                <th>{{ __('access.holders_now') }}</th>
                                <th>{{ __('access.holders_default') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lookup as $row)
                                <tr data-lookup-key="{{ $row['key'] }}">
                                    <td><code>{{ $row['key'] }}</code></td>
                                    <td>
                                        @forelse($row['roles'] as $role)
                                            {{ __('roles_hub.role_'.$role) }}@if(! $loop->last), @endif
                                        @empty
                                            {{ __('access.holders_none') }}
                                        @endforelse
                                    </td>
                                    <td>
                                        @forelse($row['defaults'] as $role)
                                            {{ __('roles_hub.role_'.$role) }}@if(! $loop->last), @endif
                                        @empty
                                            {{ __('access.holders_none') }}
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <p class="mb-2">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.access.csv', array_filter(['q' => $query])) }}">
            <i class="bi bi-download" aria-hidden="true"></i> {{ __('access.export') }}
        </a>
    </p>
    <p class="form-text mb-4">{{ __('access.export_help') }}</p>

    <p class="small text-muted-theme mb-0">
        <a href="{{ route('roles.hub') }}">{{ __('superadmin.tile_roles') }}</a>
        · <a href="{{ route('admin.users.index') }}">{{ __('superadmin.tile_users') }}</a>
        · <a href="{{ route('superadmin.security') }}">{{ __('superadmin.tile_security') }}</a>
        · <a href="{{ route('superadmin.audit.index', ['action' => 'rbac.']) }}">{{ __('superadmin.tile_audit') }}</a>
    </p>
</div>
@endsection
