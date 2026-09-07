<?php

/**
 * School-wide module kill-switches. Stored in `settings` as features.{key}.
 * Super Admin toggles them; EnsureFeatureEnabled 404s gated routes when off.
 * Defaults stay on so existing Learn/Teach tests keep working without seed rows.
 */
return [
    'public_catalog' => [
        'default' => true,
        'group' => 'public',
        'requires_route' => 'catalog.index',
    ],
    'registration' => [
        'default' => true,
        'group' => 'public',
        'requires_route' => 'auth.register',
    ],
    'credentials_public' => [
        'default' => true,
        'group' => 'public',
        'requires_route' => 'credentials.verify',
    ],
    'admissions' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'applications.index',
    ],
    'enrollment' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'enrollments.store',
    ],
    'learn' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'courses.player',
    ],
    'teach' => [
        'default' => true,
        'group' => 'staff',
        'requires_route' => 'teach.index',
    ],
    'finance_checkout' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'finance.checkout',
    ],
    'live' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'live.index',
    ],
    'discussions' => [
        'default' => true,
        'group' => 'student',
        'requires_route' => 'discussions.board',
    ],
    'surveys' => [
        'default' => true,
        'group' => 's6e',
        'requires_route' => 'student.surveys.index',
    ],
    'events' => [
        'default' => true,
        'group' => 's6e',
        'requires_route' => 'events.index',
    ],
    'live_quiz' => [
        'default' => true,
        'group' => 's6e',
        'requires_route' => 'live-quiz.join',
    ],
    'projects' => [
        'default' => true,
        'group' => 's6e',
        'requires_route' => 'student.projects.mine',
    ],
];
