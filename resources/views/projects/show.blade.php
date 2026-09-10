@extends('layouts.app')
@section('title', $project->name)
@section('content')
<x-page-header
    :title="$project->name"
    :subtitle="$offering->course->code.' — '.$assessment->title"
>
    <x-slot:actions>
        <a href="{{ route('student.projects.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('projects.back_to_list') }}</a>
        <a href="{{ route('learn.offering', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('learning.open_player') }}</a>
    </x-slot:actions>
</x-page-header>

@if($project->workspace_url)
    <p class="mb-3"><a href="{{ $project->workspace_url }}" target="_blank" rel="noopener">{{ __('projects.workspace') }}</a></p>
@endif
@if($grade !== null)
    <p class="mb-3">{{ __('projects.grade') }}: <strong>{{ $grade }}</strong></p>
@endif

{{-- Members --}}
<x-card variant="quiet" class="mb-4 p-3">
    <h2 class="h6 mb-3">{{ __('projects.members') }}</h2>
    <ul class="list-unstyled mb-0">
        @foreach($project->activeMemberships as $membership)
            <li class="d-flex flex-wrap align-items-center gap-2 py-1">
                <x-avatar :name="trim(($membership->student?->first_name ?? '').' '.($membership->student?->last_name ?? ''))" size="sm" />
                <span>{{ $membership->student?->first_name }} {{ $membership->student?->last_name }}</span>
                <span class="small spims-text-dim">{{ __('projects.role_'.$membership->role->value) }}</span>
            </li>
        @endforeach
    </ul>
</x-card>

{{-- Deliverables --}}
<h2 class="h6 mb-3">{{ __('projects.deliverables') }}</h2>
@foreach($phases as $phase)
    <x-card variant="panel" class="mb-4 p-4">
        <h3 class="h6 mb-3">{{ $phase->name }}</h3>
        @foreach($phase->deliverables as $deliverable)
            @php $submission = $submissions->get($deliverable->id); @endphp
            <x-card variant="quiet" class="p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <strong>{{ $deliverable->title }}</strong>
                    <span class="small spims-text-dim">{{ __('projects.kind_'.$deliverable->kind->value) }}</span>
                </div>
                @if($deliverable->due_at)
                    <p class="small spims-text-dim mb-2">{{ $deliverable->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                @endif
                @if($submission)
                    <p class="small mb-2">
                        <x-icon name="success" size="sm" />
                        {{ __('projects.submitted') }}
                        @if($submission->late)
                            <x-status-badge status="late" />
                        @endif
                    </p>
                    @if($submission->body)
                        <p class="mb-2">{{ $submission->body }}</p>
                    @endif
                    @if($submission->link)
                        <p class="mb-2"><a href="{{ $submission->link }}" target="_blank" rel="noopener">{{ $submission->link }}</a></p>
                    @endif
                    @foreach($submission->files as $file)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <a href="{{ $fileUrl($file->path) }}">
                                <x-icon name="download" size="sm" />
                                {{ $file->original_name }}
                            </a>
                            <form method="POST" action="{{ route('student.projects.files.destroy', [$project, $file]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <x-icon name="delete" size="sm" />
                                    {{ __('projects.delete_file') }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                @endif

                <form method="POST" action="{{ route('student.projects.submit', [$project, $deliverable]) }}" enctype="multipart/form-data" class="mt-2">
                    @csrf
                    @if($deliverable->kind->value === 'TEXT')
                        <x-field :label="__('projects.submit')" name="body">
                            <textarea name="body" class="form-control" rows="4">{{ old('body', $submission?->body) }}</textarea>
                        </x-field>
                    @elseif($deliverable->kind->value === 'LINK')
                        <x-field :label="__('projects.link_label')" name="link">
                            <input name="link" class="form-control" type="url" value="{{ old('link', $submission?->link) }}" placeholder="https://">
                        </x-field>
                    @else
                        @php $maxBytes = $deliverable->max_file_mb * 1024 * 1024; @endphp
                        <x-file-drop
                            name="files[]"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.txt,.odt,.ods,.odp"
                            :max-size="$maxBytes"
                        />
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary mt-2">{{ __('projects.submit') }}</button>
                </form>
            </x-card>
        @endforeach
    </x-card>
@endforeach

{{-- Peer evaluation --}}
<x-card variant="quiet" class="p-4 mb-4">
    <h2 class="h6 mb-2">{{ __('projects.peer_title') }}</h2>
    <p class="small spims-text-dim mb-3">{{ __('projects.peer_anonymous') }}</p>

    {{-- Submitted evals (read-only) --}}
    @if($submittedPeerEvals->isNotEmpty())
        <h3 class="h6 mb-2">{{ __('projects.peer_submitted_label') }}</h3>
        @foreach($submittedPeerEvals as $eval)
            <x-card variant="bare" class="border mb-2 p-3">
                <p class="mb-1 small">
                    <strong>{{ $eval->ratee?->first_name }} {{ $eval->ratee?->last_name }}</strong>
                    &mdash;
                    {{ __('projects.peer_score') }}: <strong>{{ $eval->score }}</strong>
                </p>
                @if($eval->comment)
                    <p class="small spims-text-dim mb-0">{{ $eval->comment }}</p>
                @endif
            </x-card>
        @endforeach
    @endif

    {{-- Pending eval forms --}}
    @if(! $peerWindowOpen)
        <p class="small spims-text-dim mb-0">{{ __('projects.peer_window_closed') }}</p>
    @elseif($pendingPeers === [])
        @if($submittedPeerEvals->isEmpty())
            <p class="small spims-text-dim mb-0">{{ __('projects.peer_none') }}</p>
        @endif
    @else
        @foreach($pendingPeers as $peer)
            <form method="POST" action="{{ route('student.projects.peer.store', $project) }}" class="mb-3">
                @csrf
                <input type="hidden" name="ratee_id" value="{{ $peer['id'] }}">
                <p class="mb-2 small">{{ __('projects.peer_rate') }}: <strong>{{ $peer['first_name'] }} {{ $peer['last_name'] }}</strong></p>
                <div class="row g-2">
                    <div class="col-12 col-md-3">
                        <x-field :label="__('projects.peer_score')" name="score-{{ $peer['id'] }}">
                            <input id="score-{{ $peer['id'] }}" type="number" name="score" class="form-control" min="0" max="100" step="0.1" required>
                        </x-field>
                    </div>
                    <div class="col-12 col-md-7">
                        <x-field :label="__('projects.peer_comment')" name="comment-{{ $peer['id'] }}">
                            <input id="comment-{{ $peer['id'] }}" name="comment" class="form-control">
                        </x-field>
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100 mb-3">{{ __('projects.peer_submit') }}</button>
                    </div>
                </div>
            </form>
        @endforeach
    @endif
</x-card>
@endsection
