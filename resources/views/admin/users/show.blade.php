@extends('layouts.app')
@section('title', $user->email)
@section('content')
<x-page-header
    :title="$user->first_name.' '.$user->last_name"
    :subtitle="$user->email"
>
    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('enrollment.back_to_users') }}</a>
        <a href="{{ route('admin.enrollments.index') }}" class="btn btn-outline-primary btn-sm">{{ __('enrollment.admin_title') }}</a>
    </x-slot:actions>
</x-page-header>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('ui.edit_user') }}</h2>
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="row g-2">
            @csrf
            @method('PUT')
            <div class="col-md-6">
                <label class="form-label" for="user-first">{{ __('ui.first_name') }}</label>
                <input id="user-first" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user-last">{{ __('ui.last_name') }}</label>
                <input id="user-last" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user-phone">{{ __('ui.phone') }}</label>
                <input id="user-phone" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user-locale">{{ __('learning.preferred_locale') }}</label>
                <select id="user-locale" name="preferred_locale" class="form-select">
                    @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('preferred_locale', $user->preferred_locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user-country">{{ __('ui.country_code') }}</label>
                <input id="user-country" name="country_code" class="form-control" maxlength="10" value="{{ old('country_code', $user->country_code) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user-dob">{{ __('attendance.date_of_birth') }}</label>
                <input id="user-dob" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}">
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input type="checkbox" name="notify_email" value="1" class="form-check-input" @checked(old('notify_email', $user->notify_email))>
                    <span class="form-check-label">{{ __('learning.notify_email') }}</span>
                </label>
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input type="checkbox" name="is_reviewer" value="1" class="form-check-input" @checked(old('is_reviewer', $user->is_reviewer))>
                    <span class="form-check-label">{{ __('ui.is_reviewer') }}</span>
                </label>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('ui.roles') }}</h2>
        <ul class="mb-3">
            @forelse($user->roleTypes() as $role)
                <li class="d-flex align-items-center gap-2 mb-1">
                    <span>{{ $role->value }}</span>
                    @if(in_array($role, $assignableRoles, true))
                        <form method="POST" action="{{ route('admin.users.roles.remove', [$user, $role->value]) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('ui.remove_role') }}</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-muted-theme">{{ __('ui.empty') }}</li>
            @endforelse
        </ul>
        @if(count($assignableRoles) > 0)
            <form method="POST" action="{{ route('admin.users.roles.assign', $user) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label" for="user-assign-role">{{ __('ui.assign_role') }}</label>
                    <select id="user-assign-role" name="role" class="form-select" required>
                        @foreach($assignableRoles as $role)
                            <option value="{{ $role->value }}">{{ $role->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-primary">{{ __('ui.assign_role') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">{{ __('enrollment.financial_hold_label') }}</h2>
                <p class="small text-muted-theme mb-3">
                    {{ $held ? __('enrollment.hold_on') : __('enrollment.hold_off') }}
                </p>
                <form method="POST" action="{{ route('admin.enrollments.financial-hold', $user) }}">
                    @csrf
                    @if($held)
                        <input type="hidden" name="held" value="0">
                        <button class="btn btn-outline-secondary">{{ __('enrollment.release_hold') }}</button>
                    @else
                        <input type="hidden" name="held" value="1">
                        <button class="btn btn-outline-danger">{{ __('enrollment.place_hold') }}</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">{{ __('enrollment.override_register') }}</h2>
                <form method="POST" action="{{ route('admin.enrollments.override') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $user->id }}">
                    <div class="col-12">
                        <label class="form-label" for="user-override-offering">{{ __('enrollment.offering') }}</label>
                        <select id="user-override-offering" name="offering_id" class="form-select" required>
                            @foreach($offerings as $offering)
                                <option value="{{ $offering->id }}">{{ $offering->course->code }} — {{ $offering->course->title }}@if($offering->semester) ({{ $offering->semester->name }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="user-override-program">{{ __('enrollment.program') }}</label>
                        <select id="user-override-program" name="student_program_id" class="form-select">
                            <option value="">{{ __('enrollment.standalone_or_none') }}</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->program->code }} — {{ $program->program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="is_audit" value="1" class="form-check-input">
                            <span class="form-check-label">{{ __('enrollment.audit_registration') }}</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary">{{ __('enrollment.override_register') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
