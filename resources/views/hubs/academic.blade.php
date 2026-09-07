@extends('layouts.app')

@section('title', __('hubs.academic_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <h1 class="page-title">{{ __('hubs.academic_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('hubs.academic_desc') }}</p>
    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_academic')])
    @include('partials.features-entrance-banner')
    @include('partials.config-entrance-banner')
    @include('partials.theme-entrance-banner', ['caption' => __('theme_studio.entrance_from_academic')])
    @include('partials.people-entrance-banner')
    <div class="row g-3">
        @forelse($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @empty
            <p class="text-muted-theme">{{ __('ui.dashboard_subheading') }}</p>
        @endforelse
    </div>
</div>
@endsection
