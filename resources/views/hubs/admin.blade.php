@extends('layouts.app')

@section('title', __('hubs.admin_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <h1 class="page-title">{{ __('hubs.admin_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('hubs.admin_desc') }}</p>
    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_admin')])
    @include('partials.people-entrance-banner')
    @include('partials.features-entrance-banner')
    @include('partials.config-entrance-banner')
    @include('partials.theme-entrance-banner', ['caption' => __('theme_studio.entrance_from_admin')])
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_admin')])
    @include('partials.reports-entrance-banner', ['caption' => __('school_reports.entrance_from_admin')])
    @include('partials.ops-entrance-banner', ['caption' => __('ops.entrance_from_admin')])
    @include('partials.status-entrance-banner', ['caption' => __('status.entrance_from_admin')])
    @include('partials.access-entrance-banner', ['caption' => __('access.entrance_from_admin')])
    <div class="row g-3">
        @forelse($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @empty
            <p class="text-muted-theme">—</p>
        @endforelse
    </div>
</div>
@endsection
