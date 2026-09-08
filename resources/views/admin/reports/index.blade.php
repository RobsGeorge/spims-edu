@extends('layouts.app')
@section('title', __('reports.hub_title'))
@section('content')
<div class="hub-page animate-in">
    <x-page-header :title="__('reports.hub_title')" :subtitle="__('reports.hub_desc')" />

    <div class="row g-3">
        @foreach([
            ['route' => 'admin.reports.headcount', 'label' => 'reports.headcount_title', 'desc' => 'reports.headcount_desc', 'icon' => 'bi-people'],
            ['route' => 'admin.reports.admissions', 'label' => 'reports.admissions_title', 'desc' => 'reports.admissions_desc', 'icon' => 'bi-funnel'],
            ['route' => 'admin.reports.attendance', 'label' => 'reports.attendance_title', 'desc' => 'reports.attendance_desc', 'icon' => 'bi-calendar-check'],
            ['route' => 'admin.reports.grades', 'label' => 'reports.grades_title', 'desc' => 'reports.grades_desc', 'icon' => 'bi-bar-chart'],
            ['route' => 'admin.reports.finance', 'label' => 'reports.finance_title', 'desc' => 'reports.finance_desc', 'icon' => 'bi-graph-up'],
            ['route' => 'admin.reports.standing', 'label' => 'reports.standing_title', 'desc' => 'reports.standing_desc', 'icon' => 'bi-exclamation-triangle'],
        ] as $link)
            @include('partials.hub-link-tile', ['link' => [
                'route' => $link['route'],
                'label' => __($link['label']),
                'description' => __($link['desc']),
                'icon' => $link['icon'],
            ]])
        @endforeach
        @if(!empty($canManageStanding))
            @include('partials.hub-link-tile', ['link' => [
                'route' => 'admin.reports.standing.thresholds',
                'label' => __('reports.thresholds_title'),
                'description' => __('reports.thresholds_desc'),
                'icon' => 'bi-sliders',
            ]])
        @endif
    </div>
</div>
@endsection
