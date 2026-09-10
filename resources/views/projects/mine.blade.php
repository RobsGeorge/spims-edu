@extends('layouts.app')
@section('title', __('projects.mine_title'))
@section('content')
<x-page-header :title="__('projects.mine_title')" :subtitle="__('projects.mine_desc')">
    <x-slot:actions>
        <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.nav_enrollments') }}</a>
    </x-slot:actions>
</x-page-header>

@forelse($memberships as $membership)
    @php
        $project = $membership->project;
        $assessment = $project?->assessment;
        $offering = $assessment?->offering;
    @endphp
    <x-card variant="quiet" class="p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
            <div>
                <strong>{{ $project?->name }}</strong>
                <div class="small spims-text-dim">
                    {{ $offering?->course?->code }} · {{ $assessment?->title }}
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($offering)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('student.projects.index', $offering) }}">{{ __('projects.nav') }}</a>
                @endif
                @if($project)
                    <a class="btn btn-sm btn-primary" href="{{ route('student.projects.show', $project) }}">{{ __('projects.open_team') }}</a>
                @endif
            </div>
        </div>
    </x-card>
@empty
    <x-empty-state :title="__('projects.mine_empty')" :message="__('projects.mine_desc')" icon="bi-people">
        <x-slot:actions>
            <a href="{{ route('enrollments.index') }}" class="btn btn-outline-primary">{{ __('learning.open_player') }}</a>
        </x-slot:actions>
    </x-empty-state>
@endforelse
@endsection
