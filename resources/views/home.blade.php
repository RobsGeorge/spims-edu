@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
    <div class="spims-hero card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="spims-title mb-3">{{ __('ui.home_heading') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('ui.home_subheading') }}</p>
        </div>
    </div>
@endsection
