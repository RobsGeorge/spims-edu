<?php

/**
 * In-portal system documentation catalog (v1).
 *
 * Bodies live in resources/system-docs/{locale}/{slug}.md
 * Titles/summaries live in lang/{locale}/system_docs.php under pages.*.
 *
 * guest_eligible pages are readable by guests by default. Super Admin can
 * turn guest access off via settings key system_docs.guest_published.
 */
return [
    'setting_key' => 'system_docs.guest_published',

    'pages' => [
        [
            'slug' => 'overview',
            'audience' => 'client',
            'guest_eligible' => true,
            'sort' => 10,
            'icon' => 'bi-journal-richtext',
        ],
        [
            'slug' => 'roles-guide',
            'audience' => 'client',
            'guest_eligible' => true,
            'sort' => 20,
            'icon' => 'bi-people',
        ],
        [
            'slug' => 'student-journey',
            'audience' => 'client',
            'guest_eligible' => true,
            'sort' => 30,
            'icon' => 'bi-mortarboard',
        ],
        [
            'slug' => 'staff-journeys',
            'audience' => 'client',
            'guest_eligible' => true,
            'sort' => 40,
            'icon' => 'bi-easel2',
        ],
        [
            'slug' => 'portal-navigation',
            'audience' => 'client',
            'guest_eligible' => true,
            'sort' => 50,
            'icon' => 'bi-layout-sidebar',
        ],
        [
            'slug' => 'architecture',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 110,
            'icon' => 'bi-diagram-3',
        ],
        [
            'slug' => 'frontend',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 120,
            'icon' => 'bi-window',
        ],
        [
            'slug' => 'backend',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 130,
            'icon' => 'bi-server',
        ],
        [
            'slug' => 'database',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 140,
            'icon' => 'bi-database',
        ],
        [
            'slug' => 'navigation-map',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 150,
            'icon' => 'bi-map',
        ],
        [
            'slug' => 'feature-flows',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 160,
            'icon' => 'bi-signpost-2',
        ],
        [
            'slug' => 'roles-permissions',
            'audience' => 'technical',
            'guest_eligible' => false,
            'sort' => 170,
            'icon' => 'bi-shield-check',
        ],
    ],
];
