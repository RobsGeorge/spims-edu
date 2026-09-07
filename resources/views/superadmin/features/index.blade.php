@extends('layouts.app')

@section('title', __('features.page_title'))

@section('content')
<div class="sa-features animate-in">
    <nav class="mb-3 small" aria-label="{{ __('features.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('features.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('features.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('features.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('features.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('features.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-4" role="note">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('features.danger_title') }}</strong>
            <p class="mb-0">{{ __('features.danger_body') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.config') }}">{{ __('features.open_config') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.theme.index') }}">{{ __('theme_studio.nav_studio') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index', ['action' => 'features.']) }}">{{ __('features.open_audit') }}</a>
    </div>
    <p class="small text-muted-theme mb-4">{{ __('features.open_config_help') }}</p>

    @foreach($groupOrder as $group)
        @if(!empty($groups[$group]))
            <section class="mb-4" id="sa-flags-{{ $group }}">
                <h2 class="h5 page-title mb-1">{{ __('features.group_'.$group) }}</h2>
                <p class="small text-muted-theme mb-3">{{ __('features.group_'.$group.'_help') }}</p>
                <div class="row g-3">
                    @foreach($groups[$group] as $flag)
                        <div class="col-12">
                            <article class="app-card p-3 sa-flag-card {{ $flag['enabled'] ? 'is-on' : 'is-off' }}" data-flag="{{ $flag['key'] }}">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="h6 page-title mb-1">{{ __('features.'.$flag['key']) }}</h3>
                                        <p class="small text-muted-theme mb-2">{{ __('features.'.$flag['key'].'_help') }}</p>
                                        <p class="mb-1">
                                            <span class="sa-flag-state {{ $flag['enabled'] ? 'is-on' : 'is-off' }}">
                                                {{ $flag['enabled'] ? __('features.current_on') : __('features.current_off') }}
                                            </span>
                                        </p>
                                        <p class="form-text mb-0">
                                            {{ $flag['overridden'] ? __('features.uses_override') : __('features.uses_default') }}
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('superadmin.features.update', $flag['key']) }}" class="sa-flag-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="enabled" value="{{ $flag['enabled'] ? '0' : '1' }}">
                                        @if($flag['enabled'])
                                            <button class="btn btn-outline-danger btn-sm" onclick="return confirm(@json(__('features.confirm_off')))">
                                                {{ __('features.turn_off') }}
                                            </button>
                                        @else
                                            <button class="btn btn-outline-success btn-sm">{{ __('features.turn_on') }}</button>
                                        @endif
                                        <p class="form-text mb-0 mt-2">{{ __('features.'.$flag['key']) }} · <code>features.{{ $flag['key'] }}</code></p>
                                    </form>
                                </div>
                                <dl class="row small mb-0 mt-3">
                                    <dt class="col-md-3">{{ __('features.what_it_gates') }}</dt>
                                    <dd class="col-md-9">{{ __('features.'.$flag['key'].'_gates') }}</dd>
                                    <dt class="col-md-3">{{ __('features.what_stays') }}</dt>
                                    <dd class="col-md-9 mb-0">{{ __('features.'.$flag['key'].'_stays') }}</dd>
                                </dl>
                            </article>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
@endsection
