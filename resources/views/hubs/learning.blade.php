@extends('layouts.app')

@section('title', __('hubs.learning_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <h1 class="page-title">{{ __('hubs.learning_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('hubs.learning_desc') }}</p>
    <div class="row g-3">
        @foreach($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @endforeach
    </div>
</div>
@endsection
