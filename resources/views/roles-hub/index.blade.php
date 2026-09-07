@extends('layouts.app')

@section('title', __('roles_hub.title'))

@section('content')
@php
    use App\Enums\RoleType;
@endphp
<div class="roles-hub sa-console animate-in">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>

    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('superadmin.role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('roles_hub.title') }}</h1>
    </div>
    <p class="text-muted-theme mb-3">{{ __('roles_hub.desc') }}</p>

    <div class="sa-callout sa-callout-danger mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('roles_hub.safety_title') }}</strong>
            <p class="mb-0">{{ __('roles_hub.safety_body') }}</p>
        </div>
    </div>
    @include('partials.access-entrance-banner', ['caption' => __('access.entrance_from_roles')])

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <label class="form-label" for="roles-hub-search">{{ __('roles_hub.search_label') }}</label>
    <input type="search" id="roles-hub-search" class="form-control mb-2" autocomplete="off"
           placeholder="{{ __('roles_hub.search_placeholder') }}">
    <p class="small text-muted-theme mb-4">{{ __('roles_hub.search_help') }}</p>

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
                        <details class="roles-hub-panel mb-3" data-role-panel id="role-{{ $roleKey }}">
                            <summary class="roles-hub-summary">
                                <span class="fw-semibold">{{ __('roles_hub.role_'.$roleKey) }}</span>
                                <span class="text-muted-theme small ms-2"><code>{{ $roleKey }}</code></span>
                            </summary>
                            <div class="p-3">
                                <p class="small text-muted-theme">{{ __('roles_hub.role_help') }}</p>
                                <form method="POST" action="{{ route('roles.hub.role.reset', $roleKey) }}" class="mb-3"
                                      onsubmit="return confirm(@json(__('roles_hub.reset_confirm', ['role' => __('roles_hub.role_'.$roleKey)])));">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('roles_hub.reset_role') }}
                                    </button>
                                    <span class="small text-muted-theme ms-2">{{ __('roles_hub.reset_help') }}</span>
                                </form>
                                <form method="POST" action="{{ route('roles.hub.role.update', $roleKey) }}">
                                    @csrf
                                    @method('PUT')
                                    @foreach($groups as $group)
                                        <details class="roles-hub-subpanel mb-2" data-perm-group>
                                            <summary class="roles-hub-subsummary text-uppercase small">
                                                {{ $group['label'] }}
                                                <span class="text-muted-theme fw-normal">· {{ $group['id'] }}</span>
                                            </summary>
                                            <div class="row g-2 mt-2">
                                                @foreach($group['keys'] as $permKey)
                                                    @php
                                                        $checked = isset($matrix[$permKey][$roleKey]);
                                                    @endphp
                                                    <div class="col-md-6 col-lg-4" data-perm-row data-perm-key="{{ $permKey }}">
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
                    @include('partials.people-entrance-banner')
                    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_roles')])
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-people"></i> {{ __('people.directory_title') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('roles-hub-search');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('[data-perm-row]').forEach(function (row) {
            const key = (row.getAttribute('data-perm-key') || '').toLowerCase();
            row.hidden = q !== '' && !key.includes(q);
        });
        document.querySelectorAll('[data-perm-group]').forEach(function (group) {
            const visible = group.querySelectorAll('[data-perm-row]:not([hidden])').length;
            group.hidden = q !== '' && visible === 0;
        });
    });
})();
</script>
@endpush
