@extends('layouts.app')

@section('title', __('hubs.academic_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <x-page-header :title="__('hubs.academic_title')" :subtitle="__('hubs.academic_desc')" />
    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_academic')])
    @include('partials.people-entrance-banner')
    @include('partials.people-entrance-banner')
    <div class="row g-3">
        @forelse($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @empty
            <p class="spims-text-dim">{{ __('ui.dashboard_subheading') }}</p>
        @endforelse
    </div>
</div>
@endsection
