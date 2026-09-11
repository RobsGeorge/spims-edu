@extends('layouts.app')
@section('title', __('admissions.review'))
@section('content')
<x-page-header :title="$application->program->code.' — '.$application->applicant->email">
    <x-slot:actions>
        <x-status-badge :status="$application->status->badgeTone()" :label="$statusLabel" />
        <a href="{{ route('admin.applications.index') }}" class="btn btn-outline-primary btn-sm">{{ __('ui.nav_applications') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<x-card variant="panel" class="mb-3">
    <h2 class="h6 spims-title mb-3">{{ __('admissions.answers_heading') }}</h2>
    @if(count($answers) === 0)
        <p class="spims-text-dim mb-0">{{ __('admissions.answers_empty') }}</p>
    @else
        <ul class="mb-0">
            @foreach($answers as $answer)
                <li>
                    <strong>{{ $answer['label'] }}:</strong>
                    @if($answer['display'] === null || $answer['display'] === '')
                        <span class="spims-text-dim">{{ __('admissions.answer_blank') }}</span>
                    @elseif($answer['is_file'])
                        <span class="spims-text-dim">{{ __('admissions.document') }}:</span> {{ $answer['display'] }}
                    @else
                        {{ $answer['display'] }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.applications.decide', $application) }}" class="mt-3">
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <select name="decision" class="form-select" required>
                @foreach($decisions as $decision)
                    <option value="{{ $decision['value'] }}">{{ $decision['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6"><input name="decision_note" class="form-control" placeholder="{{ __('admissions.decision_note') }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
    </div>
</x-card>
@endsection
