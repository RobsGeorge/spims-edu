@extends('layouts.app')
@section('title', __('admissions.review'))
@section('content')
<h1 class="spims-title">{{ $application->program->code }} — {{ $application->applicant->email }}</h1>
<p class="text-muted-theme">{{ $application->status->value }}</p>
<ul>
@foreach($application->values as $value)
    <li>
        <strong>{{ $value->field->label }}:</strong>
        @if($value->file_url)
            <span class="text-muted-theme">{{ __('admissions.document') }}:</span> {{ $value->file_url }}
        @else
            {{ $value->value }}
        @endif
    </li>
@endforeach
</ul>
<form method="POST" action="{{ route('admin.applications.decide', $application) }}" class="card border-0 shadow-sm mt-3">
    @csrf
    <div class="card-body row g-2">
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
</form>
@endsection
