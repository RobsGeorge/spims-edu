@extends('layouts.app')
@section('title', $thread->title)
@section('content')
<h1 class="spims-title mb-3">{{ $thread->title }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@foreach($thread->posts as $post)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <div class="small text-muted">{{ $post->author->email }} · {{ $post->created_at }}</div>
            <div>{!! nl2br(e($post->body)) !!}</div>
        </div>
    </div>
@endforeach
<form method="POST" action="{{ route('discussions.posts.store', $thread) }}">@csrf
    <textarea name="body" class="form-control mb-2" rows="3" required></textarea>
    <button class="btn btn-primary">{{ __('live.posted') }}</button>
</form>
@endsection
