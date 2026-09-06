@extends('layouts.app')
@section('title', $form->name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="spims-title mb-0">{{ $form->name }}</h1>
        <p class="text-muted-theme mb-0">{{ $form->program->code }} · {{ $form->active ? __('academics.active') : __('academics.inactive') }}</p>
    </div>
    <a href="{{ route('admin.application-forms.index') }}" class="btn btn-outline-secondary">{{ __('ui.nav_app_forms') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4" id="edit">
    <div class="card-body">
        <h2 class="h6">{{ __('admissions.edit_form') }}</h2>
        <form method="POST" action="{{ route('admin.application-forms.update', $form) }}" class="row g-2">
            @csrf
            @method('PUT')
            <div class="col-md-6">
                <label class="form-label">{{ __('admissions.form_name') }}</label>
                <input name="name" class="form-control" value="{{ old('name', $form->name) }}" required>
            </div>
            <div class="col-md-3 form-check mt-4">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" class="form-check-input" id="form_active" @checked(old('active', $form->active))>
                <label for="form_active" class="form-check-label">{{ __('academics.active') }}</label>
            </div>
            <div class="col-md-3 mt-4"><button class="btn btn-primary w-100">{{ __('ui.save_changes') }}</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('admissions.add_field') }}</h2>
        <form method="POST" action="{{ route('admin.application-forms.fields.store', $form) }}" class="row g-2">
            @csrf
            <div class="col-md-5">
                <label class="form-label">{{ __('admissions.field_label') }}</label>
                <input name="label" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('academics.type') }}</label>
                <select name="type" class="form-select" required>
                    @foreach($fieldTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-check mt-4">
                <input type="checkbox" name="required" value="1" class="form-check-input" id="field_required">
                <label for="field_required" class="form-check-label">{{ __('admissions.required') }}</label>
            </div>
            <div class="col-12"><button class="btn btn-outline-primary">{{ __('admissions.add_field') }}</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h6">{{ __('admissions.fields') }}</h2>
        <ul class="mb-0 list-unstyled">
            @forelse($form->fields as $field)
                <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span>
                        {{ $field->label }} ({{ $field->type->value }})
                        @if($field->required) · {{ __('admissions.required') }}@endif
                        · {{ $field->active ? __('academics.active') : __('academics.inactive') }}
                    </span>
                    @if($field->active)
                        <form method="POST" action="{{ route('admin.application-forms.fields.deactivate', [$form, $field]) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">{{ __('admissions.deactivate_field') }}</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-muted-theme">{{ __('admissions.no_fields') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
