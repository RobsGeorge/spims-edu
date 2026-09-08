@extends('layouts.app')
@section('title', __('admissions.review'))
@section('content')
<x-page-header :title="$application->program->code.' — '.$application->applicant->email">
    <x-slot:actions>
        <x-status-badge :status="$application->status->badgeTone()" :label="$application->status->value" />
        <a href="{{ route('admin.applications.index') }}" class="btn btn-outline-primary btn-sm">{{ __('ui.nav_applications') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<x-card variant="panel" class="mb-3">
        <ul class="mb-0">
        @foreach($application->values as $value)
            <li>
                <strong>{{ $value->field->label }}:</strong>
                @if($value->file_url)
                    <span class="spims-text-dim">{{ __('admissions.document') }}:</span> {{ $value->file_url }}
                @else
                    @php $fieldAnswer = $value->value; @endphp
                    {{ $fieldAnswer }}
                @endif
            </li>
        @endforeach
        </ul>
</x-card>
<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.applications.decide', $application) }}" class="mt-3">
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <select name="decision" class="form-select" required>
                <option value="ACCEPTED">ACCEPTED</option>
                <option value="REJECTED">REJECTED</option>
                <option value="WAITLISTED">WAITLISTED</option>
            </select>
        </div>
        <div class="col-md-6"><input name="decision_note" class="form-control" placeholder="{{ __('admissions.decision_note') }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
    </div>
</x-card>
@endsection
