@extends('layouts.app')
@section('title', __('live.discussions'))
@section('content')
<h1 class="spims-title mb-3">{{ __('live.discussions') }} — {{ $offering->course->code }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('discussions.threads.store', $offering) }}" class="card border-0 shadow-sm mb-4">@csrf
    <div class="card-body row g-2">
        <div class="col-md-6"><input name="title" class="form-control" placeholder="Thread title" required></div>
        <div class="col-md-6"><input name="body" class="form-control" placeholder="Opening post"></div>
        <div class="col-12"><button class="btn btn-primary">New thread</button></div>
    </div>
</form>
<ul>
@foreach($threads as $thread)
    <li>
        @if($thread->pinned)📌@endif
        <a href="{{ route('discussions.thread', $thread) }}">{{ $thread->title }}</a>
        — {{ $thread->author->email }}
        @if($thread->locked)(locked)@endif
    </li>
@endforeach
</ul>
@endsection
