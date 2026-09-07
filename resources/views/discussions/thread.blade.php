@extends('layouts.app')
@section('title', $thread->title)
@section('content')
<h1 class="spims-title mb-3">{{ $thread->title }}</h1>
@if($offering ?? null)
    <div class="mb-3">
        <a href="{{ route('discussions.board', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('live.discussions') }}</a>
        @if($canGrade)
            <a href="{{ route('teach.discussions.index', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.discussions_workspace') }}</a>
        @endif
    </div>
@endif
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@foreach($posts as $post)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <div class="small text-muted">{{ $post->author->email }} · {{ $post->created_at }}</div>
            <div>{!! nl2br(e($post->body)) !!}</div>
        </div>
    </div>
@endforeach
{{ $posts->links() }}
<form method="POST" action="{{ route('discussions.posts.store', $thread) }}">@csrf
    <textarea name="body" class="form-control mb-2" rows="3" required></textarea>
    <button class="btn btn-primary">{{ __('live.posted') }}</button>
</form>
@if(($canGrade ?? false) && ($offering ?? null))
    @include('discussions.partials.grade-form', [
        'thread' => $thread,
        'offering' => $offering,
        'students' => $students,
        'canGrade' => $canGrade,
    ])
@endif
@endsection
