@extends('layouts.app')
@section('title', $offering->course->code.' — '.__('teach.workspace'))
@section('content')
<x-page-header
    :title="$offering->course->code.' — '.$offering->course->title"
    :subtitle="__('teach.workspace_sub')"
    :eyebrow="__('teach.workspace')"
>
    <x-slot:actions>
        <a href="{{ route('teach.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
        <a href="{{ route('admin.offerings.show', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.open_admin') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => $tab, 'prefix' => 'teach'])

<div class="teach-workspace-panel mt-3">
    @if($tab === 'assessments')
        <x-page-header :title="__('teach.tab_assessments')" :subtitle="__('teach.tab_assessments_help')" />
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('admin.assessments.create', $offering) }}">{{ __('teach.create_assessment') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.banks.index', $offering->course) }}">{{ __('teach.open_banks') }}</a>
        </div>
    @elseif($tab === 'gradebook')
        <x-page-header :title="__('teach.tab_gradebook')" :subtitle="__('teach.tab_gradebook_help')" />
        <a class="btn btn-primary" href="{{ route('admin.gradebook.show', $offering) }}">{{ __('teach.open_gradebook') }}</a>
    @elseif($tab === 'live')
        <x-page-header :title="__('teach.tab_live')" :subtitle="__('teach.tab_live_help')" />
        <a class="btn btn-primary" href="{{ route('admin.live.index', $offering) }}">{{ __('teach.open_live') }}</a>
    @elseif($tab === 'discussions')
        <x-page-header :title="__('teach.tab_discussions')" :subtitle="__('teach.tab_discussions_help')" />
        <a class="btn btn-primary" href="{{ route('discussions.board', $offering) }}">{{ __('teach.open_discussions') }}</a>
    @elseif($tab === 'announcements')
        <x-page-header :title="__('teach.tab_announcements')" :subtitle="__('teach.tab_announcements_help')" />
        <form method="POST" action="{{ route('teach.announcements.store', $offering) }}" class="row g-2 mb-4">
            @csrf
            <div class="col-md-4"><input name="title" class="form-control" placeholder="{{ __('teach.announcement_title') }}" required></div>
            <div class="col-md-6"><input name="body" class="form-control" placeholder="{{ __('teach.announcement_body') }}" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
        @forelse($announcements as $announcement)
            <article class="border rounded-3 p-3 mb-2">
                <h3 class="h6 mb-1">{{ $announcement->title }}</h3>
                <p class="mb-0 text-muted-theme">{{ $announcement->body }}</p>
            </article>
        @empty
            <x-empty-state :title="__('teach.no_announcements')" icon="bi-megaphone" />
        @endforelse
    @elseif($tab === 'roster')
        <x-page-header :title="__('teach.tab_roster')" :subtitle="__('teach.roster_count', ['count' => $rosterCount])" />
        <ul class="list-unstyled mb-0">
            @foreach($offering->staff as $staff)
                <li class="mb-2">{{ $staff->user->first_name }} {{ $staff->user->last_name }}
                    <x-status-badge :status="'info'" :label="$staff->role->value" />
                </li>
            @endforeach
        </ul>
    @else
        <x-page-header :title="__('teach.tab_content')" :subtitle="__('teach.tab_content_help')" />
        <a class="btn btn-primary mb-3" href="{{ route('admin.offerings.show', $offering) }}">{{ __('teach.edit_content') }}</a>
        @forelse($offering->weeks as $week)
            <div class="border rounded-3 p-3 mb-2">
                <h3 class="h6 mb-1">{{ __('teach.week_n', ['n' => $week->number]) }} — {{ $week->title }}</h3>
                <p class="small text-muted-theme mb-0">{{ $week->contentItems->count() }} {{ __('teach.items') }}</p>
            </div>
        @empty
            <x-empty-state :title="__('teach.no_weeks')" :message="__('teach.no_weeks_help')" icon="bi-calendar-week" />
        @endforelse
    @endif
</div>
@endsection
