@extends('layouts.app')
@section('title', __('ui.error_500_title'))
@section('content')
<div class="spims-error-page text-center py-5">
    <p class="spims-error-page__code text-muted mb-2">500</p>
    <h1 class="spims-title h2 mb-3">{{ __('ui.error_500_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('ui.error_500_body') }}</p>
    <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="btn btn-primary">{{ __('ui.go_home') }}</a>
</div>
@endsection