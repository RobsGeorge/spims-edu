@extends('layouts.app')
@section('title', __('attendance.admin_report'))
@section('content')
<x-page-header :title="__('attendance.cross_offering_report')" />

@forelse($rows as $row)
    <div class="app-card p-3 mb-3">
        <h2 class="h6">{{ $row['offering']->course->code }} — {{ $row['offering']->course->title }}</h2>
        <p class="small spims-text-dim mb-2">
            {{ __('attendance.session_count') }}: {{ $row['report']['aggregate']['session_count'] }}
            · {{ __('attendance.present') }}: {{ $row['report']['aggregate']['present'] }}
            · {{ __('attendance.absent') }}: {{ $row['report']['aggregate']['absent'] }}
        </p>
        <a href="{{ route('teach.attendance.index', ['offering' => $row['offering'], 'tab' => 'report']) }}">{{ __('attendance.report') }}</a>
    </div>
@empty
    <x-empty-state :title="__('attendance.report_empty')" icon="bi-clipboard-data" />
@endforelse
@endsection
