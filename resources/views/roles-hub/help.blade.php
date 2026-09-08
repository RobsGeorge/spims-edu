@php
    $none = __('roles_hub.help_grant_none');
    $full = __('roles_hub.help_grant_full');
    $read = __('roles_hub.help_grant_read');
    $own = __('roles_hub.help_grant_own');
    $saOnly = __('roles_hub.help_grant_sa');
    $yes = __('roles_hub.help_vs_yes');
    $no = __('roles_hub.help_vs_no');

    $levels = [
        ['label' => __('roles_hub.help_level_F_label'), 'body' => __('roles_hub.help_level_F_body'), 'icon' => 'success'],
        ['label' => __('roles_hub.help_level_R_label'), 'body' => __('roles_hub.help_level_R_body'), 'icon' => 'view'],
        ['label' => __('roles_hub.help_level_O_label'), 'body' => __('roles_hub.help_level_O_body'), 'icon' => 'student'],
        ['label' => __('roles_hub.help_level_named_label'), 'body' => __('roles_hub.help_level_named_body'), 'icon' => 'grade'],
    ];

    $roleCards = [
        [
            'name' => __('roles_hub.role_ADMINISTRATIVE_ADMIN'),
            'owns' => __('roles_hub.help_adm_owns'),
            'can' => __('roles_hub.help_adm_can'),
            'cannot' => __('roles_hub.help_adm_cannot'),
            'icon' => 'settings',
            'variant' => 'panel',
        ],
        [
            'name' => __('roles_hub.role_ACADEMIC_ADMIN'),
            'owns' => __('roles_hub.help_aca_owns'),
            'can' => __('roles_hub.help_aca_can'),
            'cannot' => __('roles_hub.help_aca_cannot'),
            'icon' => 'course',
            'variant' => 'panel',
        ],
        [
            'name' => __('roles_hub.role_FINANCIAL_ADMIN'),
            'owns' => __('roles_hub.help_fin_owns'),
            'can' => __('roles_hub.help_fin_can'),
            'cannot' => __('roles_hub.help_fin_cannot'),
            'icon' => 'finance',
            'variant' => 'quiet',
        ],
        [
            'name' => __('roles_hub.role_INSTRUCTOR'),
            'owns' => __('roles_hub.help_ins_owns'),
            'can' => __('roles_hub.help_ins_can'),
            'cannot' => __('roles_hub.help_ins_cannot'),
            'icon' => 'assessment',
            'variant' => 'panel',
        ],
        [
            'name' => __('roles_hub.role_TA'),
            'owns' => __('roles_hub.help_ta_owns'),
            'can' => __('roles_hub.help_ta_can'),
            'cannot' => __('roles_hub.help_ta_cannot'),
            'icon' => 'attendance',
            'variant' => 'quiet',
        ],
        [
            'name' => __('roles_hub.role_STUDENT'),
            'owns' => __('roles_hub.help_stu_owns'),
            'can' => __('roles_hub.help_stu_can'),
            'cannot' => __('roles_hub.help_stu_cannot'),
            'icon' => 'student',
            'variant' => 'bare',
        ],
    ];

    $vsColumns = [
        ['key' => 'action', 'label' => __('roles_hub.help_vs_action')],
        ['key' => 'instructor', 'label' => __('roles_hub.role_INSTRUCTOR')],
        ['key' => 'ta', 'label' => __('roles_hub.role_TA')],
    ];
    $vsRows = [
        ['action' => __('roles_hub.help_vs_lock'), 'instructor' => $yes, 'ta' => $no],
        ['action' => __('roles_hub.help_vs_configure'), 'instructor' => $yes, 'ta' => __('roles_hub.help_vs_read')],
        ['action' => __('roles_hub.help_vs_publish'), 'instructor' => $yes, 'ta' => $no],
        ['action' => __('roles_hub.help_vs_results'), 'instructor' => $yes, 'ta' => $no],
        ['action' => __('roles_hub.help_vs_attendance'), 'instructor' => $yes, 'ta' => __('roles_hub.help_vs_record')],
        ['action' => __('roles_hub.help_vs_close'), 'instructor' => $yes, 'ta' => $no],
        ['action' => __('roles_hub.help_vs_advising'), 'instructor' => $yes, 'ta' => $no],
    ];

    $matrixColumns = [
        ['key' => 'domain', 'label' => __('roles_hub.help_matrix_domain')],
        ['key' => 'sa', 'label' => __('roles_hub.help_col_sa')],
        ['key' => 'adm', 'label' => __('roles_hub.help_col_adm')],
        ['key' => 'aca', 'label' => __('roles_hub.help_col_aca')],
        ['key' => 'fin', 'label' => __('roles_hub.help_col_fin')],
        ['key' => 'ins', 'label' => __('roles_hub.help_col_ins')],
        ['key' => 'ta', 'label' => __('roles_hub.help_col_ta')],
        ['key' => 'stu', 'label' => __('roles_hub.help_col_stu')],
    ];
    $matrixRows = [
        ['domain' => __('roles_hub.help_dom_people'), 'sa' => $saOnly, 'adm' => $full, 'aca' => $none, 'fin' => $none, 'ins' => $none, 'ta' => $none, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_theme'), 'sa' => $full, 'adm' => $full, 'aca' => $none, 'fin' => $none, 'ins' => $none, 'ta' => $none, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_curriculum'), 'sa' => $full, 'adm' => $read, 'aca' => $full, 'fin' => $none, 'ins' => $read, 'ta' => $read, 'stu' => $read],
        ['domain' => __('roles_hub.help_dom_semesters'), 'sa' => $full, 'adm' => $full, 'aca' => $read, 'fin' => $none, 'ins' => $read, 'ta' => $read, 'stu' => $read],
        ['domain' => __('roles_hub.help_dom_offerings'), 'sa' => $full, 'adm' => $read, 'aca' => $full, 'fin' => $none, 'ins' => $own, 'ta' => $own, 'stu' => $read],
        ['domain' => __('roles_hub.help_dom_pricing'), 'sa' => $full, 'adm' => $none, 'aca' => $none, 'fin' => $full, 'ins' => $none, 'ta' => $none, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_admissions'), 'sa' => $full, 'adm' => $full, 'aca' => $none, 'fin' => $none, 'ins' => $none, 'ta' => $none, 'stu' => $own],
        ['domain' => __('roles_hub.help_dom_enrollment'), 'sa' => $full, 'adm' => $full, 'aca' => $read, 'fin' => $none, 'ins' => $none, 'ta' => $none, 'stu' => $own],
        ['domain' => __('roles_hub.help_dom_finance'), 'sa' => $full, 'adm' => $read, 'aca' => $none, 'fin' => $full, 'ins' => $none, 'ta' => $none, 'stu' => $own],
        ['domain' => __('roles_hub.help_dom_teach'), 'sa' => $full, 'adm' => $none, 'aca' => $full, 'fin' => $none, 'ins' => $own, 'ta' => $own, 'stu' => $own],
        ['domain' => __('roles_hub.help_dom_grade_lock'), 'sa' => $full, 'adm' => $none, 'aca' => $full, 'fin' => $none, 'ins' => __('roles_hub.help_grant_lock'), 'ta' => $none, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_grade_reopen'), 'sa' => $full, 'adm' => $none, 'aca' => __('roles_hub.help_grant_reopen'), 'fin' => $none, 'ins' => $none, 'ta' => $none, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_live'), 'sa' => $full, 'adm' => $full, 'aca' => $read, 'fin' => $none, 'ins' => $own, 'ta' => $own, 'stu' => $none],
        ['domain' => __('roles_hub.help_dom_credentials'), 'sa' => $full, 'adm' => __('roles_hub.help_grant_issue'), 'aca' => $full, 'fin' => $none, 'ins' => $own, 'ta' => $none, 'stu' => $own],
        ['domain' => __('roles_hub.help_dom_events'), 'sa' => $full, 'adm' => $full, 'aca' => $full, 'fin' => $read, 'ins' => $read, 'ta' => $read, 'stu' => $own],
    ];

    $gaps = [
        __('roles_hub.help_gap_levels'),
        __('roles_hub.help_gap_unsuspend'),
        __('roles_hub.help_gap_holds'),
        __('roles_hub.help_gap_live_schedule'),
        __('roles_hub.help_gap_advising'),
        __('roles_hub.help_gap_pricing_view'),
        __('roles_hub.help_gap_waitlist'),
        __('roles_hub.help_gap_announcements_adm'),
        __('roles_hub.help_gap_parent'),
    ];
@endphp

<div class="roles-hub-help">
    <p class="spims-text-dim mb-0">{{ __('roles_hub.help_hint') }}</p>

    <x-card variant="panel">
        <h2 class="roles-hub-help-heading">{{ __('roles_hub.help_intro_title') }}</h2>
        <p class="mb-2">{{ __('roles_hub.help_intro_body') }}</p>
        <p class="spims-text-dim mb-0">{{ __('roles_hub.help_defaults_note') }}</p>
    </x-card>

    <div>
        <h2 class="roles-hub-help-heading">{{ __('roles_hub.help_levels_title') }}</h2>
        <div class="grid-auto-sm">
            @foreach ($levels as $level)
                <x-card variant="quiet">
                    <p class="roles-hub-help-kicker mb-2">
                        <x-icon :name="$level['icon']" size="sm" />
                        {{ $level['label'] }}
                    </p>
                    <p class="mb-0">{{ $level['body'] }}</p>
                </x-card>
            @endforeach
        </div>
    </div>

    <div class="sa-callout sa-callout-danger" role="note">
        <x-icon name="warning" />
        <div>
            <strong>{{ __('roles_hub.help_sa_title') }}</strong>
            <p class="mb-1 mt-2"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_owns') }}</span> {{ __('roles_hub.help_sa_owns') }}</p>
            <p class="mb-1"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_can') }}</span> {{ __('roles_hub.help_sa_can') }}</p>
            <p class="mb-0"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_cannot') }}</span> {{ __('roles_hub.help_sa_cannot') }}</p>
        </div>
    </div>

    <div class="grid-auto-md">
        @foreach ($roleCards as $card)
            <x-card :variant="$card['variant']" tag="article">
                <h3 class="roles-hub-help-heading">
                    <x-icon :name="$card['icon']" size="sm" />
                    {{ $card['name'] }}
                </h3>
                <p class="mb-2"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_owns') }}</span> {{ $card['owns'] }}</p>
                <p class="mb-2"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_can') }}</span> {{ $card['can'] }}</p>
                <p class="mb-0"><span class="roles-hub-help-kicker">{{ __('roles_hub.help_role_cannot') }}</span> {{ $card['cannot'] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card variant="quiet">
        <h2 class="roles-hub-help-heading">{{ __('roles_hub.help_assign_title') }}</h2>
        <p>{{ __('roles_hub.help_assign_body') }}</p>
        <ul class="roles-hub-help-list">
            <li>{{ __('roles_hub.help_assign_sa') }}</li>
            <li>{{ __('roles_hub.help_assign_adm') }}</li>
            <li>{{ __('roles_hub.help_assign_none') }}</li>
        </ul>
        <p class="mt-3 mb-2"><strong>{{ __('roles_hub.help_scoped_title') }}</strong> {{ __('roles_hub.help_scoped_body') }}</p>
        <p class="mb-0"><strong>{{ __('roles_hub.help_multi_title') }}</strong> {{ __('roles_hub.help_multi_body') }}</p>
    </x-card>

    <div>
        <h2 class="roles-hub-help-heading">{{ __('roles_hub.help_vs_title') }}</h2>
        <x-data-table :columns="$vsColumns" :rows="$vsRows" />
    </div>

    <div>
        <h2 class="roles-hub-help-heading">{{ __('roles_hub.help_matrix_title') }}</h2>
        <p class="spims-text-dim">{{ __('roles_hub.help_matrix_hint') }}</p>
        <x-data-table :columns="$matrixColumns" :rows="$matrixRows" />
    </div>

    <x-card variant="quiet">
        <h2 class="roles-hub-help-heading">
            <x-icon name="warning" size="sm" />
            {{ __('roles_hub.help_gaps_title') }}
        </h2>
        <p>{{ __('roles_hub.help_gaps_intro') }}</p>
        <ul class="roles-hub-help-list">
            @foreach ($gaps as $gap)
                <li>{{ $gap }}</li>
            @endforeach
        </ul>
    </x-card>
</div>
