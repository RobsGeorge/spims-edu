@extends('layouts.app')
@section('title', $project->name)
@section('content')
<x-page-header
    :title="$project->name"
    :subtitle="$offering->course->code.' — '.$assessment->title"
>
    <x-slot:actions>
        <a href="{{ route('student.projects.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('projects.back_to_list') }}</a>
        <a href="{{ route('courses.player', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('learning.open_player') }}</a>
    </x-slot:actions>
</x-page-header>

@if($project->workspace_url)
    <p class="mb-3"><a href="{{ $project->workspace_url }}" target="_blank" rel="noopener">{{ __('projects.workspace') }}</a></p>
@endif
@if($grade !== null)
    <p class="mb-3">{{ __('projects.grade') }}: <strong>{{ $grade }}</strong></p>
@endif

<h2 class="h6">{{ __('projects.members') }}</h2>
<ul class="mb-4">
    @foreach($project->activeMemberships as $membership)
        <li>
            {{ $membership->student?->first_name }} {{ $membership->student?->last_name }}
            <span class="small text-muted-theme">{{ __('projects.role_'.$membership->role->value) }}</span>
        </li>
    @endforeach
</ul>

<h2 class="h6">{{ __('projects.deliverables') }}</h2>
@foreach($phases as $phase)
    <div class="app-card p-3 mb-3">
        <h3 class="h6 mb-2">{{ $phase->name }}</h3>
        @foreach($phase->deliverables as $deliverable)
            @php $submission = $submissions->get($deliverable->id); @endphp
            <div class="border rounded-3 p-3 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <strong>{{ $deliverable->title }}</strong>
                    <span class="small text-muted-theme">{{ __('projects.kind_'.$deliverable->kind->value) }}</span>
                </div>
                @if($deliverable->due_at)
                    <p class="small text-muted-theme mb-2">{{ $deliverable->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                @endif
                @if($submission)
                    <p class="small mb-2">
                        {{ __('projects.submitted') }}
                        @if($submission->late)<span class="badge text-bg-warning">{{ __('projects.late') }}</span>@endif
                    </p>
                    @if($submission->body)
                        <p>{{ $submission->body }}</p>
                    @endif
                    @if($submission->link)
                        <p><a href="{{ $submission->link }}" target="_blank" rel="noopener">{{ $submission->link }}</a></p>
                    @endif
                    @foreach($submission->files as $file)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <a href="{{ $fileUrl($file->path) }}">{{ $file->original_name }}</a>
                            <form method="POST" action="{{ route('student.projects.files.destroy', [$project, $file]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('projects.delete_file') }}</button>
                            </form>
                        </div>
                    @endforeach
                @endif

                <form method="POST" action="{{ route('student.projects.submit', [$project, $deliverable]) }}" enctype="multipart/form-data" class="mt-2">
                    @csrf
                    @if($deliverable->kind->value === 'TEXT')
                        <textarea name="body" class="form-control mb-2" rows="4" required>{{ old('body', $submission?->body) }}</textarea>
                    @elseif($deliverable->kind->value === 'LINK')
                        <input name="link" class="form-control mb-2" type="url" required value="{{ old('link', $submission?->link) }}" placeholder="https://">
                    @else
                        <input name="files[]" class="form-control mb-2" type="file" multiple>
                    @endif
                    <button class="btn btn-sm btn-primary">{{ __('projects.submit') }}</button>
                </form>
            </div>
        @endforeach
    </div>
@endforeach

<h2 class="h6">{{ __('projects.peer_title') }}</h2>
<p class="text-muted-theme">{{ __('projects.peer_anonymous') }}</p>
@if(! $peerWindowOpen)
    <p class="text-muted-theme">{{ __('projects.peer_window_closed') }}</p>
@elseif($pendingPeers === [])
    <p class="text-muted-theme">{{ __('projects.peer_none') }}</p>
@else
    @foreach($pendingPeers as $peer)
        <form method="POST" action="{{ route('student.projects.peer.store', $project) }}" class="app-card p-3 mb-2">
            @csrf
            <input type="hidden" name="ratee_id" value="{{ $peer['id'] }}">
            <p class="mb-2">{{ __('projects.peer_rate') }}: <strong>{{ $peer['first_name'] }} {{ $peer['last_name'] }}</strong></p>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label" for="score-{{ $peer['id'] }}">{{ __('projects.peer_score') }}</label>
                    <input id="score-{{ $peer['id'] }}" type="number" name="score" class="form-control" min="0" max="100" step="0.1" required>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="comment-{{ $peer['id'] }}">{{ __('projects.peer_comment') }}</label>
                    <input id="comment-{{ $peer['id'] }}" name="comment" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100">{{ __('projects.peer_submit') }}</button>
                </div>
            </div>
        </form>
    @endforeach
@endif
@endsection
