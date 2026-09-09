@extends('layouts.app')

@section('title', __('people.directory_title'))

@section('content')
@php
    use App\Enums\RoleType;
    use App\Enums\UserStatus;
@endphp
<div class="people-directory animate-in">
    <nav class="mb-3 small" aria-label="{{ __('people.dossier_crumb_directory') }}">
        @if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
            <a href="{{ route('superadmin.index') }}" class="text-decoration-none spims-text-dim">
                @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('people.dossier_crumb_console') }}
            </a>
            <span class="spims-text-dim mx-1">·</span>
        @endif
        <span class="spims-text-dim">{{ __('people.directory_title') }}</span>
    </nav>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <h1 class="page-title mb-0">{{ __('people.directory_title') }}</h1>
        <a class="small align-self-center" href="{{ route('help.show', 'users-roles') }}">{{ __('help.learn_more') }}</a>
    </div>
    <p class="spims-text-dim mb-2">{{ __('people.directory_lead') }}</p>
    <p class="small spims-text-dim mb-3">{{ __('people.directory_help') }}</p>

    @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_users')])
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_directory')])

    <x-card variant="panel" class="mb-4">
        <h2 class="h6 page-title">{{ __('people.create_title') }}</h2>
        <p class="small spims-text-dim">{{ __('people.create_help') }}</p>
        <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label" for="create-first">{{ __('ui.first_name') }}</label>
                <input id="create-first" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="create-last">{{ __('ui.last_name') }}</label>
                <input id="create-last" name="last_name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="create-email">{{ __('ui.email') }}</label>
                <input id="create-email" name="email" type="email" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="create-phone">{{ __('ui.phone') }}</label>
                <input id="create-phone" name="phone" class="form-control">
                <p class="form-text mb-0">{{ __('people.phone_help') }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="create-password">{{ __('ui.password') }}</label>
                <input id="create-password" name="password" type="password" class="form-control" required minlength="8">
                <p class="form-text mb-0">{{ __('people.password_help') }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="create-locale">{{ __('people.locale') }}</label>
                <select id="create-locale" name="preferred_locale" class="form-select">
                    @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                        <option value="{{ $code }}" @selected($code === 'en')>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="form-text mb-0">{{ __('people.locale_help') }}</p>
            </div>
            <div class="col-12">
                <p class="small spims-text-dim mb-2">{{ __('superadmin.users_roles_help') }}</p>
                @foreach($assignableRoles as $role)
                    @php $roleChkVal = $role->value; @endphp
                    <label class="me-3">
                        <input type="checkbox" name="roles[]" value="{{ $roleChkVal }}">
                        {{ __('people.role_'.$role->value) }}
                    </label>
                @endforeach
                <p class="form-text">{{ __('people.assign_role_help') }}</p>
            </div>
            <div class="col-12">
                <label class="me-2">
                    <input type="checkbox" name="is_reviewer" value="1">
                    {{ __('people.reviewer_label') }}
                </label>
                <p class="form-text d-inline">{{ __('people.reviewer_help') }}</p>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">{{ __('ui.create_user') }}</button>
            </div>
        </form>
    </x-card>

    <x-card variant="panel" class="mb-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="people-q">{{ __('people.search_label') }}</label>
                <input id="people-q" name="q" type="search" class="form-control" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('people.search_placeholder') }}" autocomplete="off">
                <p class="form-text mb-0">{{ __('people.search_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="people-role">{{ __('people.filter_role') }}</label>
                <select id="people-role" name="role" class="form-select">
                    <option value="">{{ __('people.filter_all') }}</option>
                    @foreach($roleOptions as $role)
                        @php $roleOptVal = $role->value; @endphp
                        <option value="{{ $roleOptVal }}" @selected(($filters['role'] ?? '') === $roleOptVal)>
                            {{ __('people.role_'.$role->value) }}
                        </option>
                    @endforeach
                </select>
                <p class="form-text mb-0">{{ __('people.filter_role_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="people-status">{{ __('people.filter_status') }}</label>
                <select id="people-status" name="status" class="form-select">
                    <option value="">{{ __('people.filter_all') }}</option>
                    @foreach($statusOptions as $status)
                        @php $statusOptVal = $status->value; @endphp
                        <option value="{{ $statusOptVal }}" @selected(($filters['status'] ?? '') === $statusOptVal)>
                            {{ __('people.status_'.$status->value) }}
                        </option>
                    @endforeach
                </select>
                <p class="form-text mb-0">{{ __('people.filter_status_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="people-locale">{{ __('people.filter_locale') }}</label>
                <select id="people-locale" name="locale" class="form-select">
                    <option value="">{{ __('people.filter_all') }}</option>
                    @foreach($localeOptions as $code)
                        <option value="{{ $code }}" @selected(($filters['locale'] ?? '') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
                <p class="form-text mb-0">{{ __('people.filter_locale_help') }}</p>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary">{{ __('people.apply_filters') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">{{ __('people.clear_filters') }}</a>
            </div>
        </form>
    </x-card>

    <p class="small spims-text-dim mb-2">{{ __('people.results_help') }}</p>
    <x-card variant="panel">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('ui.email') }}</th>
                        <th>{{ __('ui.name') }}</th>
                        <th>{{ __('ui.roles') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th>{{ __('people.locale') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $user) }}">{{ $user->email }}</a>
                        </td>
                        <td>
                            <a href="{{ route('admin.users.show', $user) }}">{{ $user->displayName() }}</a>
                        </td>
                        <td>
                            {{ $user->roleTypes()->map(fn ($role) => __('people.role_'.$role->value))->join(', ') }}
                        </td>
                        <td>
                            <span class="people-status people-status-{{ strtolower($user->status->value) }}">
                                {{ __('people.status_'.$user->status->value) }}
                            </span>
                        </td>
                        <td>{{ $user->preferred_locale }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $user) }}">
                                {{ __('people.open_dossier') }}
                            </a>
                            @if($user->status === UserStatus::Active && ! $user->isSeededSuperAdmin() && auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">{{ __('ui.suspend') }}</button>
                                </form>
                            @elseif($user->status === UserStatus::Suspended && !empty($capabilities['unsuspend']))
                                <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">{{ __('people.unsuspend') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <p class="mb-1">{{ __('people.empty') }}</p>
                            <p class="small spims-text-dim mb-0">{{ __('people.empty_help') }}</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    {{ $users->links() }}
</div>
@endsection
