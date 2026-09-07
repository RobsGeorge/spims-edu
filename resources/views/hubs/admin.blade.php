@extends('layouts.app')

@section('title', __('hubs.admin_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <x-page-header :title="__('hubs.admin_title')" :subtitle="__('hubs.admin_desc')" />
    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_admin')])
    @include('partials.people-entrance-banner')
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_admin')])
    <div class="row g-3">
        @forelse($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @empty
            <p class="spims-text-dim">—</p>
        @endforelse
    </div>
</div>
@endsection
