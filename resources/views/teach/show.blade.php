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
    @elseif($tab === 'attendance')
        <x-page-header :title="__('teach.tab_attendance')" :subtitle="__('teach.tab_attendance_help')" />
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('teach.attendance.index', $offering) }}">{{ __('teach.open_attendance') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('teach.attendance.index', ['offering' => $offering, 'tab' => 'report']) }}">{{ __('attendance.report') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('teach.attendance.roster.csv', $offering) }}">{{ __('attendance.roster_export') }}</a>
        </div>
    @elseif($tab === 'discussions')
        <x-page-header :title="__('teach.tab_discussions')" :subtitle="__('teach.tab_discussions_help')" />
        <a class="btn btn-primary" href="{{ route('discussions.board', $offering) }}">{{ __('teach.open_discussions') }}</a>
    @elseif($tab === 'announcements')
        <x-page-header :title="__('teach.tab_announcements')" :subtitle="__('teach.tab_announcements_help')" />
        <form method="POST" action="{{ route('teach.announcements.store', $offering) }}" class="row g-2 mb-4">
            @csrf
            <div class="col-md-4"><input name="title" class="form-control" placeholder="{{ __('teach.announcement_title') }}" required></div>
            <div class="col-md-6"><input name="body" class="form-control" placeholder="{{ __('teach.announcement_body') }}" required></div>
            <div class="col-md-2 d-flex flex-column gap-1">
                <button class="btn btn-outline-primary w-100" name="publish" value="0">{{ __('communications.save_draft') }}</button>
                <button class="btn btn-primary w-100" name="publish" value="1">{{ __('communications.publish') }}</button>
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input type="checkbox" name="is_banner" value="1" class="form-check-input">
                    <span class="form-check-label">{{ __('communications.is_banner') }}</span>
                </label>
            </div>
        </form>
        @forelse($announcements as $announcement)
            <article class="border rounded-3 p-3 mb-2">
                <div class="d-flex justify-content-between gap-2">
                    <h3 class="h6 mb-1">{{ $announcement->title }}</h3>
                    <x-status-badge :status="$announcement->status->value" :label="__('communications.status_'.strtolower($announcement->status->value))" />
                </div>
                <p class="mb-2 text-muted-theme">{{ $announcement->body }}</p>
                <form method="POST" action="{{ route('teach.announcements.update', $announcement) }}" class="row g-2 mb-2">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4"><input name="title" class="form-control form-control-sm" value="{{ $announcement->title }}" required></div>
                    <div class="col-md-6"><input name="body" class="form-control form-control-sm" value="{{ $announcement->body }}" required></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('communications.edit') }}</button></div>
                </form>
                <div class="d-flex gap-2">
                    @if($announcement->status->value === 'DRAFT')
                        <form method="POST" action="{{ route('teach.announcements.publish', $announcement) }}">
                            @csrf
                            <button class="btn btn-sm btn-primary">{{ __('communications.publish') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('teach.announcements.resend', $announcement) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">{{ __('communications.resend_email') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state :title="__('teach.no_announcements')" icon="bi-megaphone" />
        @endforelse
    @elseif($tab === 'roster')
        <x-page-header :title="__('teach.tab_roster')" :subtitle="__('teach.roster_count', ['count' => $rosterCount])" />
        <h3 class="h6">{{ __('teach.staff') }}</h3>
        <ul class="list-unstyled mb-4">
            @foreach($offering->staff as $staff)
                <li class="mb-2">{{ $staff->user->first_name }} {{ $staff->user->last_name }}
                    <x-status-badge :status="'info'" :label="$staff->role->value" />
                </li>
            @endforeach
        </ul>
        <h3 class="h6">{{ __('teach.students') }}</h3>
        @forelse($roster as $enrollment)
            <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
                <div>
                    <strong>{{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}</strong>
                    <div class="small text-muted-theme">{{ $enrollment->student->email }}</div>
                </div>
                <x-status-badge :status="$enrollment->status->value" :label="$enrollment->status->value" />
            </div>
        @empty
            <x-empty-state :title="__('teach.empty_roster')" icon="bi-people" />
        @endforelse
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-outline-primary btn-sm" href="{{ route('teach.attendance.roster.csv', $offering) }}">{{ __('attendance.roster_export') }}</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('teach.attendance.index', ['offering' => $offering, 'tab' => 'roster']) }}">{{ __('attendance.birthdays') }}</a>
        </div>
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
