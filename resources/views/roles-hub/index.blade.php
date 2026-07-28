@extends('layouts.app')

@section('title', __('roles_hub.title'))

@section('content')
@php
    use App\Enums\RoleType;
@endphp
<div class="roles-hub animate-in" style="max-width:960px;margin:0 auto;">
    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('superadmin.role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('roles_hub.title') }}</h1>
    </div>
    <p class="text-muted-theme mb-4">{{ __('roles_hub.desc') }}</p>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="accordion roles-hub-accordion" id="rolesHubAccordion">
        <div class="accordion-item app-card mb-3 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#templatesSection" aria-expanded="true">
                    <i class="bi bi-shield-check me-2"></i> {{ __('roles_hub.section_templates') }}
                </button>
            </h2>
            <div id="templatesSection" class="accordion-collapse collapse show" data-bs-parent="#rolesHubAccordion">
                <div class="accordion-body">
                    <p class="text-muted-theme small mb-3">{{ __('roles_hub.templates_hint') }}</p>

                    @foreach($roles as $role)
                        @php
                            /** @var RoleType $role */
                            $roleKey = $role->value;
                        @endphp
                        <details class="roles-hub-panel mb-3">
                            <summary class="roles-hub-summary">
                                <span class="fw-semibold">{{ __('roles_hub.role_'.$roleKey) }}</span>
                                <span class="text-muted-theme small ms-2"><code>{{ $roleKey }}</code></span>
                            </summary>
                            <div class="p-3">
                                <form method="POST" action="{{ route('roles.hub.role.update', $roleKey) }}">
                                    @csrf
                                    @method('PUT')
                                    @foreach($groups as $group => $keys)
                                        <details class="roles-hub-subpanel mb-2">
                                            <summary class="roles-hub-subsummary text-uppercase small">{{ $group }}</summary>
                                            <div class="row g-2 mt-2">
                                                @foreach($keys as $permKey)
                                                    @php
                                                        $checked = isset($matrix[$permKey][$roleKey]);
                                                    @endphp
                                                    <div class="col-md-6 col-lg-4">
                                                        <div class="form-check form-check-sm">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="permissions[]"
                                                                   id="perm-{{ $roleKey }}-{{ md5($permKey) }}"
                                                                   value="{{ $permKey }}"
                                                                   @checked($checked)>
                                                            <label class="form-check-label" for="perm-{{ $roleKey }}-{{ md5($permKey) }}">
                                                                {{ $permKey }}
                                                                @if($checked)
                                                                    <span class="badge text-bg-secondary">{{ $matrix[$permKey][$roleKey] }}</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                    <button type="submit" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-save"></i> {{ __('roles_hub.save_role') }}
                                    </button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="accordion-item app-card mb-3 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#assignmentsSection">
                    <i class="bi bi-people me-2"></i> {{ __('roles_hub.section_assignments') }}
                </button>
            </h2>
            <div id="assignmentsSection" class="accordion-collapse collapse" data-bs-parent="#rolesHubAccordion">
                <div class="accordion-body">
                    <p class="text-muted-theme mb-3">{{ __('roles_hub.assignments_hint') }}</p>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-people"></i> {{ __('hubs.users') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
