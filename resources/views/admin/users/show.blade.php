@extends('layouts.app')

@section('title', $person->displayName())

@section('content')
@php
    use App\Enums\RoleType;
    use App\Enums\UserStatus;
    $statusKey = $person->status->value;
@endphp
<div class="people-dossier animate-in">
    <nav class="mb-3 small" aria-label="{{ __('people.dossier_title') }}">
        @if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
            <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
                @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('people.dossier_crumb_console') }}
            </a>
            <span class="text-muted-theme mx-1">·</span>
        @endif
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none">{{ __('people.dossier_crumb_directory') }}</a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ $person->displayName() }}</span>
    </nav>

    <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ $person->displayName() }}</h1>
            <p class="text-muted-theme mb-2">{{ $person->email }}</p>
            <p class="small text-muted-theme mb-0">{{ __('people.dossier_lead') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="people-status people-status-{{ strtolower($statusKey) }}">{{ __('people.status_'.$statusKey) }}</span>
            @if($person->isSeededSuperAdmin())
                <span class="badge bg-danger">{{ __('people.protected_badge') }}</span>
            @endif
            @if($isSelf)
                <span class="badge bg-secondary">{{ __('people.self_badge') }}</span>
            @endif
        </div>
    </header>

    @if(session('dev_otp'))
        <div class="alert alert-warning">{{ __('people.password_reset_dev', ['code' => session('dev_otp')]) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.identity_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.identity_help') }}</p>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">{{ __('ui.email') }}</dt>
                    <dd class="col-sm-8">{{ $person->email }}</dd>
                    <dt class="col-sm-4">{{ __('ui.phone') }}</dt>
                    <dd class="col-sm-8">{{ $person->phone ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('people.locale') }}</dt>
                    <dd class="col-sm-8">{{ $person->preferred_locale }}</dd>
                    <dt class="col-sm-4">{{ __('people.reviewer_label') }}</dt>
                    <dd class="col-sm-8">{{ $person->is_reviewer ? __('ui.confirm') : '—' }}</dd>
                    <dt class="col-sm-4">{{ __('people.last_login') }}</dt>
                    <dd class="col-sm-8">
                        {{ $lastLogin?->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('people.last_login_never') }}
                        <p class="form-text mb-0">{{ __('people.last_login_help') }}</p>
                    </dd>
                </dl>
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.status_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.status_help') }}</p>
                <p class="small mb-3">{{ __('people.status_'.strtolower($statusKey).'_help') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    @if($person->status === UserStatus::Active && $canMutateTarget && ! $isSelf)
                        <form method="POST" action="{{ route('admin.users.suspend', $person) }}">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm">{{ __('ui.suspend') }}</button>
                        </form>
                        <p class="form-text mb-0">{{ __('people.suspend_help') }}</p>
                    @endif
                    @if($person->status === UserStatus::Suspended && !empty($capabilities['unsuspend']))
                        <form method="POST" action="{{ route('admin.users.unsuspend', $person) }}">
                            @csrf
                            <button class="btn btn-outline-success btn-sm">{{ __('people.unsuspend') }}</button>
                        </form>
                        <p class="form-text mb-0">{{ __('people.unsuspend_help') }}</p>
                    @endif
                    @if($person->status === UserStatus::Pending && $canMutateTarget)
                        <form method="POST" action="{{ route('admin.users.activate', $person) }}">
                            @csrf
                            <button class="btn btn-outline-primary btn-sm">{{ __('people.activate') }}</button>
                        </form>
                        <p class="form-text mb-0">{{ __('people.activate_help') }}</p>
                    @endif
                </div>
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('ui.edit_user') }}</h2>
                <p class="small text-muted-theme">{{ __('people.profile_help') }}</p>
                <form method="POST" action="{{ route('admin.users.update', $person) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-6">
                        <label class="form-label" for="edit-first">{{ __('ui.first_name') }}</label>
                        <input id="edit-first" name="first_name" class="form-control" value="{{ old('first_name', $person->first_name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit-last">{{ __('ui.last_name') }}</label>
                        <input id="edit-last" name="last_name" class="form-control" value="{{ old('last_name', $person->last_name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit-phone">{{ __('ui.phone') }}</label>
                        <input id="edit-phone" name="phone" class="form-control" value="{{ old('phone', $person->phone) }}">
                        <p class="form-text mb-0">{{ __('people.phone_help') }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit-locale">{{ __('people.locale') }}</label>
                        <select id="edit-locale" name="preferred_locale" class="form-select">
                            @foreach($localeOptions as $code => $label)
                                <option value="{{ $code }}" @selected(old('preferred_locale', $person->preferred_locale) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="form-text mb-0">{{ __('people.locale_help') }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit-country">{{ __('ui.country_code') }}</label>
                        <input id="edit-country" name="country_code" class="form-control" maxlength="10" value="{{ old('country_code', $person->country_code) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit-dob">{{ __('attendance.date_of_birth') }}</label>
                        <input id="edit-dob" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $person->date_of_birth?->toDateString()) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="notify_email" value="1" class="form-check-input" @checked(old('notify_email', $person->notify_email))>
                            <span class="form-check-label">{{ __('learning.notify_email') }}</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label>
                            <input type="hidden" name="is_reviewer" value="0">
                            <input type="checkbox" name="is_reviewer" value="1" @checked(old('is_reviewer', $person->is_reviewer))>
                            {{ __('ui.is_reviewer') }}
                        </label>
                        <p class="form-text">{{ __('people.reviewer_help') }}</p>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-sm">{{ __('ui.save_changes') }}</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.roles_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.roles_help') }}</p>
                <ul class="list-unstyled mb-3">
                    @forelse($person->roleTypes() as $role)
                        <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-1 border-bottom border-opacity-25">
                            <span>{{ __('people.role_'.$role->value) }} <code class="small">{{ $role->value }}</code></span>
                            @if(!empty($capabilities['assign_roles']) && $role !== RoleType::SuperAdmin)
                                <form method="POST" action="{{ route('admin.users.roles.destroy', [$person, $role->value]) }}"
                                      onsubmit="return confirm(@json(__('people.revoke_confirm', ['role' => __('people.role_'.$role->value)])));">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('people.revoke_role') }}</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted-theme">{{ __('people.no_roles') }}</li>
                    @endforelse
                </ul>
                @if(!empty($capabilities['assign_roles']) && $assignableRoles !== [])
                    <form method="POST" action="{{ route('admin.users.roles.assign', $person) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label" for="assign-role">{{ __('people.assign_role') }}</label>
                            <select id="assign-role" name="role" class="form-select" required>
                                @foreach($assignableRoles as $role)
                                    <option value="{{ $role->value }}">{{ __('people.role_'.$role->value) }}</option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0">{{ __('people.assign_role_help') }}</p>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary w-100">{{ __('people.assign_role') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.actions_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.actions_help') }}</p>

                @if(!empty($capabilities['reset_password']) && $canMutateTarget)
                    <form method="POST" action="{{ route('admin.users.password-reset', $person) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm">{{ __('people.password_reset') }}</button>
                        <p class="form-text mb-0">{{ __('people.password_reset_help') }}</p>
                    </form>
                @endif

                @if($canImpersonateTarget)
                    <form method="POST" action="{{ route('superadmin.people.impersonate', $person) }}" class="sa-callout sa-callout-danger">
                        @csrf
                        <div>
                            <p class="fw-semibold mb-1">{{ __('people.impersonate') }}</p>
                            <p class="small mb-2">{{ __('people.impersonate_help') }}</p>
                            <label class="d-block mb-2">
                                <input type="checkbox" name="confirm" value="1" required>
                                {{ __('people.impersonate_confirm_label') }}
                            </label>
                            <p class="form-text">{{ __('people.impersonate_confirm_help') }}</p>
                            <button class="btn btn-danger btn-sm">{{ __('people.impersonate_start') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.sessions_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.sessions_help') }}</p>
                @if($identitySessions->isEmpty())
                    <p class="small text-muted-theme mb-2">{{ __('people.sessions_empty') }}</p>
                @else
                    <ul class="list-unstyled small mb-3">
                        @foreach($identitySessions as $session)
                            <li class="py-1 border-bottom border-opacity-25">
                                {{ __('people.session_ip') }}: {{ $session->ip ?: '—' }}
                                · {{ __('people.session_expires') }}: {{ $session->expires_at?->format('Y-m-d H:i') }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.users.sessions.revoke', $person) }}">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm">{{ __('people.sessions_revoke') }}</button>
                    <p class="form-text mb-0">{{ __('people.sessions_revoke_help') }}</p>
                </form>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <section class="app-card p-3 h-100">
                <h2 class="h6 page-title">{{ __('enrollment.financial_hold_label') }}</h2>
                <p class="small text-muted-theme">{{ __('people.hold_help') }}</p>
                <p class="mb-3">{{ $held ? __('enrollment.hold_on') : __('enrollment.hold_off') }}</p>
                @if($hasFinancialHold && ! $held)
                    <p class="small text-muted-theme">{{ __('people.hold_on') }}</p>
                @endif
                @if(!empty($capabilities['enrollment_override']))
                    <form method="POST" action="{{ route('admin.enrollments.financial-hold', $person) }}" class="d-flex flex-wrap gap-2">
                        @csrf
                        @if($held)
                            <input type="hidden" name="held" value="0">
                            <button class="btn btn-outline-secondary btn-sm">{{ __('enrollment.release_hold') }}</button>
                        @else
                            <input type="hidden" name="held" value="1">
                            <button class="btn btn-outline-danger btn-sm">{{ __('enrollment.place_hold') }}</button>
                        @endif
                    </form>
                @endif
            </section>
        </div>
        <div class="col-lg-7">
            <section class="app-card p-3 h-100">
                <h2 class="h6 page-title">{{ __('enrollment.override_register') }}</h2>
                <p class="small text-muted-theme">{{ __('people.enrollments_help') }}</p>
                @if(!empty($capabilities['enrollment_override']))
                    <form method="POST" action="{{ route('admin.enrollments.override') }}" class="row g-2">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $person->id }}">
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
                            <button class="btn btn-primary btn-sm">{{ __('enrollment.override_register') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.enrollments_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.enrollments_help') }}</p>
                @if($enrollments->isEmpty())
                    <p class="text-muted-theme mb-0">{{ __('people.enrollments_empty') }}</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($enrollments as $enrollment)
                            <li class="py-2 border-bottom border-opacity-25">
                                <div class="fw-semibold">
                                    {{ $enrollment->offering?->course?->code }} · {{ $enrollment->offering?->course?->title }}
                                </div>
                                <div class="small text-muted-theme">{{ $enrollment->status?->value }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.programs_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.programs_help') }}</p>
                @if($studentPrograms->isEmpty())
                    <p class="text-muted-theme mb-0">{{ __('people.programs_empty') }}</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($studentPrograms as $programRow)
                            <li class="py-2 border-bottom border-opacity-25">
                                <div class="fw-semibold">{{ $programRow->program?->name ?? $programRow->program?->code ?? $programRow->program_id }}</div>
                                <div class="small text-muted-theme">{{ $programRow->status?->value }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.invoices_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.invoices_help') }}</p>
                @if($invoiceSummary === [])
                    <p class="text-muted-theme mb-0">{{ __('people.invoices_empty') }}</p>
                @else
                    <ul class="list-unstyled mb-3">
                        @foreach($invoiceSummary as $row)
                            <li class="small py-1">
                                <strong>{{ $row['total'] }}</strong>
                                · {{ __('people.invoice_open') }}: {{ $row['open'] }}
                                · {{ __('people.invoice_paid') }}: {{ $row['paid'] }}
                            </li>
                        @endforeach
                    </ul>
                    <ul class="list-unstyled small mb-0">
                        @foreach($invoices as $invoice)
                            <li class="py-1 border-bottom border-opacity-25">
                                {{ $invoice->status->value }} ·
                                {{ \App\Support\Money::fromMinor((int) $invoice->total_minor, $invoice->currency)->format() }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('people.audit_title') }}</h2>
                <p class="small text-muted-theme">{{ __('people.audit_help') }}</p>
                @include('partials.audit-entrance-banner', [
                    'caption' => __('audit.entrance_from_people'),
                    'url' => route('superadmin.audit.index', ['actor' => $person->email]),
                ])
                @if($recentAudit->isEmpty())
                    <p class="text-muted-theme mb-0">{{ __('people.audit_empty') }}</p>
                @else
                    <ul class="list-unstyled small mb-2">
                        @foreach($recentAudit as $log)
                            <li class="py-1 border-bottom border-opacity-25">
                                @if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
                                    <a href="{{ route('superadmin.audit.show', $log) }}"><code>{{ $log->action }}</code></a>
                                @else
                                    <code>{{ $log->action }}</code>
                                @endif
                                · {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </li>
                        @endforeach
                    </ul>
                    @if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
                        <a class="small" href="{{ route('superadmin.audit.index', ['actor' => $person->email]) }}">
                            {{ __('people.audit_open_explorer') }}
                        </a>
                    @endif
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
