@extends('layouts.app')
@section('title', __('ui.nav_catalog'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_catalog') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="row g-3">
@foreach($courses as $course)
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 spims-title">{{ $course->code }}</h2>
                <p class="mb-2">{{ $course->title }}</p>
                <p class="text-muted-theme small">{{ __('academics.credits') }}: {{ $course->credit_hours }} · {{ __('academics.interest') }}: {{ $course->interest_flags_count }}</p>
                @auth
                    <form method="POST" action="{{ route('catalog.interest', $course) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">{{ __('academics.flag_interest') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </div>
@endforeach
</div>
{{ $courses->links() }}
@endsection
