@extends('layouts.app')
@section('title', $form->name)
@section('content')
<h1 class="spims-title mb-3">{{ $form->program->code }} — {{ $form->name }}</h1>
<form method="POST" action="{{ route('applications.store', $application) }}" enctype="multipart/form-data">
    @csrf
    @foreach($form->fields as $field)
        @php
            $existing = $application->values->firstWhere('field_id', $field->id);
            $current = old('answers.'.$field->id, $existing?->value ?? ($prefill[$field->id] ?? ''));
        @endphp
        <div class="mb-3">
            <label class="form-label">{{ $field->label }} @if($field->required)*@endif</label>
            @if($field->admin_note)<div class="small text-muted-theme">{{ $field->admin_note }}</div>@endif
            @if($field->type->value === 'FILE')
                @if($existing?->file_url || $existing?->value)
                    <div class="small text-muted-theme mb-1">{{ __('admissions.current_document') }}: {{ $existing->file_url ?? $existing->value }}</div>
                @endif
                <input type="file" name="files[{{ $field->id }}]" class="form-control" @required($field->required && ! $existing)>
            @elseif($field->type->value === 'TEXTAREA')
                <textarea name="answers[{{ $field->id }}]" class="form-control" rows="4" @required($field->required)>{{ $current }}</textarea>
            @elseif($field->type->value === 'CHECKBOX')
                <div class="form-check">
                    <input type="checkbox" name="answers[{{ $field->id }}]" value="1" class="form-check-input" @checked((string) $current === '1') @required($field->required)>
                </div>
            @else
                <input
                    name="answers[{{ $field->id }}]"
                    class="form-control"
                    type="{{ $field->type->value === 'NUMBER' ? 'number' : ($field->type->value === 'DATE' ? 'date' : 'text') }}"
                    value="{{ $current }}"
                    @required($field->required)
                >
            @endif
            @error('answers.'.$field->id)<div class="text-danger small">{{ $message }}</div>@enderror
            @error('files.'.$field->id)<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    @endforeach
    <button name="submit" value="0" class="btn btn-outline-primary">{{ __('ui.save') }}</button>
    <button name="submit" value="1" class="btn btn-primary">{{ __('admissions.submit') }}</button>
</form>
@endsection
