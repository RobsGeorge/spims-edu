@extends('layouts.app')
@section('title', $form->name)
@section('content')
<x-page-header :title="$form->program->code.' — '.$form->name" :subtitle="$form->name">
    <x-slot:actions>
        <a href="{{ route('applications.index') }}" class="btn btn-outline-primary btn-sm">{{ __('ui.nav_my_applications') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<x-card variant="panel">
    <p class="mb-3">
        <x-status-badge :status="$application->status->badgeTone()" :label="$application->status->value" />
    </p>
    <form method="POST" action="{{ route('applications.store', $application) }}" enctype="multipart/form-data">
        @csrf
        @foreach($form->fields->where('active', true) as $field)
            @php
                $existing = $application->values->firstWhere('field_id', $field->id);
                $current = old('answers.'.$field->id, $existing?->value ?? ($prefill[$field->id] ?? ''));
                $typeVal = $field->type->value;
                $fieldInputType = $typeVal === 'NUMBER' ? 'number' : ($typeVal === 'DATE' ? 'date' : 'text');
                $existingDisplay = $existing ? ($existing->file_url ?? $existing->value) : null;
            @endphp
            <div class="mb-3">
                <label class="form-label">{{ $field->label }} @if($field->required)*@endif</label>
                @if($field->admin_note)<div class="small spims-text-dim">{{ $field->admin_note }}</div>@endif
                @if($typeVal === 'FILE')
                    @if($existingDisplay)
                        <div class="small spims-text-dim mb-1">{{ __('admissions.current_document') }}: {{ $existingDisplay }}</div>
                    @endif
                    <input type="file" name="files[{{ $field->id }}]" class="form-control" @required($field->required && ! $existing)>
                @elseif($typeVal === 'TEXTAREA')
                    <textarea name="answers[{{ $field->id }}]" class="form-control" rows="4" @required($field->required)>{{ $current }}</textarea>
                @elseif($typeVal === 'CHECKBOX')
                    <div class="form-check">
                        <input type="checkbox" name="answers[{{ $field->id }}]" value="1" class="form-check-input" @checked((string) $current === '1') @required($field->required)>
                    </div>
                @else
                    <input
                        name="answers[{{ $field->id }}]"
                        class="form-control"
                        type="{{ $fieldInputType }}"
                        value="{{ $current }}"
                        @required($field->required)
                    >
                @endif
                @error('answers.'.$field->id)<div class="text-danger small">{{ $message }}</div>@enderror
                @error('files.'.$field->id)<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="d-flex flex-wrap gap-2">
            <button name="submit" value="0" class="btn btn-outline-primary">{{ __('ui.save') }}</button>
            <button name="submit" value="1" class="btn btn-primary">{{ __('admissions.submit') }}</button>
            @if($application->status->isWithdrawable())
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdraw-application">
                    {{ __('admissions.withdraw') }}
                </button>
            @endif
        </div>
    </form>
</x-card>

@if($application->status->isWithdrawable())
    <form method="POST" action="{{ route('applications.withdraw', $application) }}" id="withdraw-application-form">
        @csrf
    </form>
    <x-confirm-dialog
        id="withdraw-application"
        :title="__('admissions.withdraw_confirm_title')"
        :message="__('admissions.withdraw_confirm_body')"
        tone="danger"
    >
        <x-slot:confirm>
            <button type="submit" form="withdraw-application-form" class="btn btn-danger">{{ __('admissions.withdraw') }}</button>
        </x-slot:confirm>
    </x-confirm-dialog>
@endif
@endsection
