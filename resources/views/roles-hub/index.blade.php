@extends('layouts.app')

@section('title', __('roles_hub.title'))

@section('content')
@php
    use App\Enums\RoleType;
@endphp
<div class="roles-hub sa-console animate-in">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none spims-text-dim">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>

    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('superadmin.role') }}
        </span>
        <h1 class="spims-title mb-0">{{ __('roles_hub.title') }}</h1>
    </div>
    <p class="spims-text-dim mb-3">{{ __('roles_hub.desc') }}</p>

    @php
        $section = $section ?? 'templates';
        $helpOpen = $section === 'help';
        $assignmentsOpen = $section === 'assignments';
        $templatesOpen = ! $helpOpen && ! $assignmentsOpen;
    @endphp

    <a href="{{ route('roles.hub', ['section' => 'help']) }}" class="btn btn-outline-primary roles-hub-help-jump mb-4">
        <x-icon name="course" size="sm" />
        {{ __('roles_hub.help_jump') }}
    </a>

    <div class="sa-callout sa-callout-danger mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('roles_hub.safety_title') }}</strong>
            <p class="mb-0">{{ __('roles_hub.safety_body') }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="accordion roles-hub-accordion" id="rolesHubAccordion">
        <div class="accordion-item app-card mb-3 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button{{ $helpOpen ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#helpSection" aria-expanded="{{ $helpOpen ? 'true' : 'false' }}" aria-controls="helpSection">
                    <x-icon name="course" size="sm" class="me-2" /> {{ __('roles_hub.section_help') }}
                </button>
            </h2>
            <div id="helpSection" class="accordion-collapse collapse{{ $helpOpen ? ' show' : '' }}" data-bs-parent="#rolesHubAccordion">
                <div class="accordion-body">
                    @include('roles-hub.partials.portal-guides')
                    @include('roles-hub.help')
                </div>
            </div>
        </div>

        <div class="accordion-item app-card mb-3 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button{{ $templatesOpen ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#templatesSection" aria-expanded="{{ $templatesOpen ? 'true' : 'false' }}" aria-controls="templatesSection">
                    <i class="bi bi-shield-check me-2"></i> {{ __('roles_hub.section_templates') }}
                </button>
            </h2>
            <div id="templatesSection" class="accordion-collapse collapse{{ $templatesOpen ? ' show' : '' }}" data-bs-parent="#rolesHubAccordion">
                <div class="accordion-body">
                    <p class="spims-text-dim small mb-3">{{ __('roles_hub.templates_hint') }}</p>
                    <label class="form-label" for="roles-hub-search">{{ __('roles_hub.search_label') }}</label>
                    <input type="search" id="roles-hub-search" class="form-control mb-2" autocomplete="off"
                           placeholder="{{ __('roles_hub.search_placeholder') }}">
                    <p class="small spims-text-dim mb-4">{{ __('roles_hub.search_help') }}</p>

                    @foreach($roles as $role)
                        @php
                            /** @var RoleType $role */
                            $roleKey = $role->value;
                        @endphp
                        <details class="roles-hub-panel mb-3" data-role-panel>
                            <summary class="roles-hub-summary">
                                <span class="fw-semibold">{{ __('roles_hub.role_'.$roleKey) }}</span>
                                <span class="spims-text-dim small ms-2"><code>{{ $roleKey }}</code></span>
                            </summary>
                            <div class="p-3">
                                <p class="small spims-text-dim">{{ __('roles_hub.role_help') }}</p>
                                <form method="POST" action="{{ route('roles.hub.role.reset', $roleKey) }}" class="mb-3"
                                      onsubmit="return confirm(@json(__('roles_hub.reset_confirm', ['role' => __('roles_hub.role_'.$roleKey)])));">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('roles_hub.reset_role') }}
                                    </button>
                                    <span class="small spims-text-dim ms-2">{{ __('roles_hub.reset_help') }}</span>
                                </form>
                                <form method="POST" action="{{ route('roles.hub.role.update', $roleKey) }}">
                                    @csrf
                                    @method('PUT')
                                    @foreach($groups as $group)
                                        <details class="roles-hub-subpanel mb-2" data-perm-group>
                                            <summary class="roles-hub-subsummary text-uppercase small">
                                                {{ $group['label'] }}
                                                <span class="spims-text-dim fw-normal">· {{ $group['id'] }}</span>
                                            </summary>
                                            <div class="row g-2 mt-2">
                                                @foreach($group['keys'] as $permKey)
                                                    @include('roles-hub.partials.permission-level-row', [
                                                        'permKey' => $permKey,
                                                        'roleKey' => $roleKey,
                                                        'matrix' => $matrix,
                                                        'grantLevels' => $grantLevels,
                                                    ])
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                    <button type="submit" class="btn btn-primary mt-2">
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
                <button class="accordion-button{{ $assignmentsOpen ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#assignmentsSection" aria-expanded="{{ $assignmentsOpen ? 'true' : 'false' }}" aria-controls="assignmentsSection">
                    <i class="bi bi-people me-2"></i> {{ __('roles_hub.section_assignments') }}
                </button>
            </h2>
            <div id="assignmentsSection" class="accordion-collapse collapse{{ $assignmentsOpen ? ' show' : '' }}" data-bs-parent="#rolesHubAccordion">
                <div class="accordion-body">
                    <p class="spims-text-dim mb-3">{{ __('roles_hub.assignments_hint') }}</p>
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
