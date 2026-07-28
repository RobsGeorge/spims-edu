@extends('layouts.app')

@section('title', __('superadmin.title'))

@section('content')
<div class="container py-2 animate-in hub-page" style="max-width:920px;">
    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i> {{ __('superadmin.role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('superadmin.title') }}</h1>
    </div>
    <p class="text-muted-theme mb-4">{{ __('superadmin.hub_desc') }}</p>

    @foreach($sections as $section)
        <h2 class="h6 text-muted-theme text-uppercase mb-3 mt-4">{{ $section['title'] }}</h2>
        <div class="row g-3 mb-2">
            @foreach($section['links'] as $link)
                @include('partials.hub-link-tile', ['link' => $link])
            @endforeach
        </div>
    @endforeach
</div>
@endsection
