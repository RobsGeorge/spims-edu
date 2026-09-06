@extends('layouts.app')
@section('title', __('completion.own_title'))
@section('content')
<x-page-header :title="__('completion.own_title')" :subtitle="$offering->course->code.' · '.$offering->course->title">
    <x-slot:actions>
        <a href="{{ route('courses.player', $offering) }}" class="btn btn-outline-secondary">{{ __('learning.open_player') }}</a>
        <a href="{{ route('learn.offering', $offering) }}" class="btn btn-outline-secondary">{{ __('learn.player_title') }}</a>
        <a href="{{ route('transcript.show') }}" class="btn btn-outline-secondary">{{ __('credentials.transcript') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="app-card p-4 mb-4">
    <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('completion.outcome') }}</dt>
        <dd class="col-sm-9">
            <span class="badge {{ $outcome === \App\Enums\CompletionOutcome::Completed ? 'text-bg-success' : ($outcome === \App\Enums\CompletionOutcome::NotCompleted ? 'text-bg-danger' : 'text-bg-warning') }}">
                {{ __('completion.outcome_'.$outcome->value) }}
            </span>
        </dd>
        <dt class="col-sm-3">{{ __('completion.evaluated_at') }}</dt>
        <dd class="col-sm-9">
            @if($evaluatedAt)
                {{ $evaluatedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
            @else
                <span class="text-muted-theme">{{ __('completion.not_yet_evaluated') }}</span>
            @endif
        </dd>
    </dl>
</div>

<h2 class="h5 mb-3">{{ __('completion.met_criteria') }}</h2>
<div class="table-responsive spims-table-wrap">
<table class="table">
    <thead>
        <tr>
            <th scope="col">{{ __('completion.kind') }}</th>
            <th scope="col">{{ __('completion.is_required') }}</th>
            <th scope="col">{{ __('completion.status') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse($metCriteria as $row)
        <tr>
            <td>{{ __('completion.kind_'.$row['kind']) }}</td>
            <td>{{ ($row['is_required'] ?? false) ? __('completion.required_yes') : __('completion.required_no') }}</td>
            <td>{{ ($row['passed'] ?? false) ? __('completion.passed') : __('completion.failed') }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="text-muted-theme">{{ __('completion.own_pending') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
