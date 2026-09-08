@extends('layouts.app')
@section('title', __('live.discussions'))
@section('content')
<x-page-header
    :title="__('live.discussions')"
    :subtitle="$offering->course->title"
    :eyebrow="$offering->course->code"
>
    <x-slot:actions>
        @if($canGrade ?? false)
            <a href="{{ route('teach.discussions.index', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.discussions_workspace') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('file_url')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('attachments')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('attachment')<div class="alert alert-danger">{{ $message }}</div>@enderror

@if($board)
<form method="POST" action="{{ route('discussions.threads.store', $offering) }}" enctype="multipart/form-data" class="card border-0 shadow-sm mb-4">@csrf
    <div class="card-body row g-2">
        <div class="col-12 col-md-6">
            <label class="form-label" for="thread-title">{{ __('discussions.thread_title') }}</label>
            <input id="thread-title" name="title" class="form-control" placeholder="{{ __('discussions.thread_title') }}" required>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="thread-body">{{ __('discussions.opening_post') }}</label>
            <input id="thread-body" name="body" class="form-control" placeholder="{{ __('discussions.opening_post') }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="thread-attachments">{{ __('discussions.attach_file') }}</label>
            <input id="thread-attachments" type="file" name="attachments[]" class="form-control" multiple>
        </div>
        <div class="col-12"><button class="btn btn-primary">{{ __('discussions.new_thread') }}</button></div>
    </div>
</form>
@if($threads->isEmpty())
    <x-empty-state :title="__('discussions.no_threads')" :message="__('discussions.no_threads_help')" icon="bi-chat-square-text" />
@else
    <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush">
        @foreach($threads as $thread)
            <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                <div>
                    @if($thread->pinned)<x-status-badge status="info" :label="__('discussions.pinned')" />@endif
                    <a href="{{ route('discussions.thread', $thread) }}">{{ $thread->title }}</a>
                    <div class="small text-muted-theme">{{ $thread->author->email }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($thread->locked)<x-status-badge status="warning" :label="__('discussions.locked')" />@endif
                </div>
            </li>
        @endforeach
        </ul>
    </div>
    {{ $threads->links() }}
@endif
@else
<div class="alert alert-info">{{ __('live.board_not_yet_configured') }}</div>
@endif
@endsection
