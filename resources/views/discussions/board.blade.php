@extends('layouts.app')
@section('title', __('live.discussions'))
@section('content')
<h1 class="spims-title mb-3">{{ __('live.discussions') }} — {{ $offering->course->code }}</h1>
@if($canGrade ?? false)
    <div class="mb-3">
        <a href="{{ route('teach.discussions.index', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.discussions_workspace') }}</a>
    </div>
@endif
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($board)
<form method="POST" action="{{ route('discussions.threads.store', $offering) }}" class="card border-0 shadow-sm mb-4">@csrf
    <div class="card-body row g-2">
        <div class="col-md-6"><input name="title" class="form-control" placeholder="{{ __('discussions.thread_title') }}" required></div>
        <div class="col-md-6"><input name="body" class="form-control" placeholder="{{ __('discussions.opening_post') }}"></div>
        <div class="col-12"><button class="btn btn-primary">{{ __('discussions.new_thread') }}</button></div>
    </div>
</form>
@if($threads->isEmpty())
    <p class="text-muted-theme">{{ __('discussions.no_threads') }}</p>
@else
    <ul>
    @foreach($threads as $thread)
        <li>
            @if($thread->pinned)📌@endif
            <a href="{{ route('discussions.thread', $thread) }}">{{ $thread->title }}</a>
            — {{ $thread->author->email }}
            @if($thread->locked)({{ __('discussions.locked') }})@endif
        </li>
    @endforeach
    </ul>
    {{ $threads->links() }}
@endif
@else
<div class="alert alert-info">{{ __('live.board_not_yet_configured') }}</div>
@endif
@endsection
