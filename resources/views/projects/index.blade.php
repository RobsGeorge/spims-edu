@extends('layouts.app')
@section('title', __('projects.title').' — '.$offering->course->code)
@section('content')
<x-page-header
    :title="__('projects.title')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
>
    <x-slot:actions>
        <a href="{{ route('courses.player', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('learning.open_player') }}</a>
        <a href="{{ route('learn.offering', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('learn.player_title') }}</a>
    </x-slot:actions>
</x-page-header>

@forelse($rows as $row)
    @php
        $assessment = $row['assessment'];
        $membership = $row['membership'];
    @endphp
    <div class="app-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-2">
            <div>
                <h2 class="h5 mb-1">{{ $assessment->title }}</h2>
                <p class="small text-muted-theme mb-0">
                    {{ __('projects.team_size', ['min' => $assessment->team_size_min, 'max' => $assessment->team_size_max]) }}
                    · {{ __('projects.join_window_label') }}:
                    {{ $assessment->join_opens_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    –
                    {{ $assessment->join_closes_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                </p>
            </div>
            @if($membership)
                <a class="btn btn-sm btn-primary" href="{{ route('student.projects.show', $membership->project_id) }}">{{ __('projects.open_team') }}</a>
            @endif
        </div>

        @if($membership)
            <p class="mb-2">{{ __('projects.your_team') }}: <strong>{{ $membership->project?->name ?? $membership->project_id }}</strong></p>
            @if($row['canLeave'])
                <form method="POST" action="{{ route('student.projects.leave', [$offering, $assessment]) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger">{{ __('projects.leave') }}</button>
                </form>
            @endif
        @elseif($assessment->isJoinWindowOpen())
            <form method="POST" action="{{ route('student.projects.join', [$offering, $assessment]) }}" class="mb-3">
                @csrf
                <button class="btn btn-sm btn-primary">{{ __('projects.join_any') }}</button>
            </form>
            @if($row['openTeams']->isNotEmpty())
                <h3 class="h6">{{ __('projects.open_teams') }}</h3>
                <ul class="list-unstyled mb-0">
                    @foreach($row['openTeams'] as $teamRow)
                        <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-1">
                            <span>
                                {{ $teamRow['project']->name }}
                                <span class="small text-muted-theme">{{ $teamRow['seats'] }}/{{ $teamRow['capacity'] }}</span>
                            </span>
                            @if($teamRow['seats'] < $teamRow['capacity'])
                                <form method="POST" action="{{ route('student.projects.join', [$offering, $assessment]) }}">
                                    @csrf
                                    <input type="hidden" name="project_id" value="{{ $teamRow['project']->id }}">
                                    <button class="btn btn-sm btn-outline-primary">{{ __('projects.join_team') }}</button>
                                </form>
                            @else
                                <span class="small text-muted-theme">{{ __('projects.at_capacity') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            <p class="text-muted-theme mb-0">{{ __('projects.join_window') }}</p>
        @endif
    </div>
@empty
    <x-empty-state :title="__('projects.no_assessments')" icon="bi-people" />
@endforelse
@endsection
