@extends('layouts.app')
@section('title', __('teach.discussions_workspace'))
@section('content')
<x-page-header
    :title="__('teach.discussions_workspace')"
    :subtitle="__('teach.discussions_workspace_sub')"
    :eyebrow="$offering->course->code.' — '.$offering->course->title"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'discussions']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
        <a href="{{ route('discussions.board', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.open_discussions') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'discussions', 'prefix' => 'teach'])

@if(session('status'))
    <div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mt-3">
    @forelse($threads as $thread)
        <article class="border rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-1">
                        <a href="{{ route('discussions.thread', $thread) }}">{{ $thread->title }}</a>
                    </h2>
                    <div class="small text-muted-theme">{{ $thread->author?->email }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($thread->is_graded)
                        <x-status-badge status="info" :label="__('discussions.graded_thread')" />
                    @endif
                    @if($thread->locked)
                        <x-status-badge status="warning" :label="__('discussions.locked')" />
                    @endif
                    @if($thread->pinned)
                        <x-status-badge status="info" :label="__('discussions.pinned')" />
                    @endif
                    @if($thread->has_attachments)
                        <x-status-badge status="info" :label="__('discussions.attachments')" />
                    @endif
                </div>
            </div>
            @include('discussions.partials.grade-form', [
                'thread' => $thread,
                'offering' => $offering,
                'students' => $students,
                'canGrade' => $canGrade,
            ])
        </article>
    @empty
        <x-empty-state :title="__('discussions.no_threads')" :message="__('discussions.no_threads_help')" icon="bi-chat-square-text" />
    @endforelse
</div>
@endsection
