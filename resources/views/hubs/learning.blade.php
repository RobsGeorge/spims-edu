@extends('layouts.app')

@section('title', __('hubs.learning_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <x-page-header :title="__('hubs.learning_title')" :subtitle="__('hubs.learning_desc')" />
    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_learning')])
    @include('partials.people-entrance-banner')
    @include('partials.people-entrance-banner')
    <div class="row g-3">
        @foreach($links as $link)
            @include('partials.hub-link-tile', ['link' => $link])
        @endforeach
    </div>
</div>
@endsection
