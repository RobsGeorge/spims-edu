@extends('layouts.app')
@section('title', $form->name)
@section('content')
<h1 class="spims-title mb-3">{{ $form->program->code }} — {{ $form->name }}</h1>
<form method="POST" action="{{ route('applications.store', $application) }}">
    @csrf
    @foreach($form->fields as $field)
        <div class="mb-3">
            <label class="form-label">{{ $field->label }} @if($field->required)*@endif</label>
            @if($field->admin_note)<div class="small text-muted-theme">{{ $field->admin_note }}</div>@endif
            <input name="answers[{{ $field->id }}]" class="form-control" value="{{ old('answers.'.$field->id, $application->values->firstWhere('field_id', $field->id)?->value ?? ($prefill[$field->id] ?? '')) }}" @required($field->required)>
        </div>
    @endforeach
    <button name="submit" value="0" class="btn btn-outline-primary">{{ __('ui.save') }}</button>
    <button name="submit" value="1" class="btn btn-primary">{{ __('admissions.submit') }}</button>
</form>
@endsection
