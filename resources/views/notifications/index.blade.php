@extends('layouts.app')
@section('title', __('ui.nav_notifications'))
@section('content')
<x-page-header :title="__('ui.nav_notifications')" />
<ul class="list-group">
@foreach($notifications as $n)
    <li class="list-group-item d-flex justify-content-between @if(!$n->read_at) fw-semibold @endif">
        <div>
            <div>{{ $n->title }}</div>
            <div class="small text-muted">{{ $n->body }}</div>
        </div>
        @if(!$n->read_at)
        <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Read</button></form>
        @endif
    </li>
@endforeach
</ul>
@endsection
