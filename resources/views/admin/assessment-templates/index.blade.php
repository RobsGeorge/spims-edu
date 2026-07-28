@extends('layouts.app')
@section('title', __('ui.nav_templates'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_templates') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('academics.create_template') }}</h2>
        <form method="POST" action="{{ route('admin.assessment-templates.store') }}">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-md-6"><input name="name" class="form-control" placeholder="{{ __('academics.name') }}" required></div>
                <div class="col-md-3 form-check mt-2"><input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default"><label for="is_default" class="form-check-label">{{ __('academics.is_default') }}</label></div>
            </div>
            <p class="small text-muted-theme">{{ __('academics.weights_hint') }}</p>
            @foreach([['Exam', 'EXAM', 60], ['Attendance', 'ATTENDANCE', 20], ['Assignments', 'ASSIGNMENT', 20]] as $i => $defaults)
                <div class="row g-2 mb-2">
                    <div class="col-md-4"><input name="components[{{ $i }}][name]" class="form-control" value="{{ $defaults[0] }}" required></div>
                    <div class="col-md-4">
                        <select name="components[{{ $i }}][kind]" class="form-select" required>
                            @foreach(\App\Enums\ComponentKind::cases() as $kind)
                                <option value="{{ $kind->value }}" @selected($kind->value === $defaults[1])>{{ $kind->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><input type="number" step="0.01" name="components[{{ $i }}][weight_percent]" class="form-control" value="{{ $defaults[2] }}" required></div>
                </div>
            @endforeach
            <button class="btn btn-primary">{{ __('ui.save') }}</button>
        </form>
    </div>
</div>

@foreach($templates as $template)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">{{ $template->name }} @if($template->is_default)<span class="badge text-bg-secondary">{{ __('academics.is_default') }}</span>@endif</h2>
            <ul class="mb-0">
                @foreach($template->components as $c)
                    <li>{{ $c->name }} ({{ $c->kind->value }}) — {{ $c->weight_percent }}%</li>
                @endforeach
            </ul>
        </div>
    </div>
@endforeach
@endsection
