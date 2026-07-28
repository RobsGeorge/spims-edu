<?php

/**
 * SPIMS permission keys — traces to permissions-matrix v1.
 * Super Admin bypasses all checks in AuthorizeService.
 */
return [
    'users.manage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'roles.assign' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'roles.assign_admin' => [],
    'profile.edit_own' => [
        'ADMINISTRATIVE_ADMIN' => 'O',
        'ACADEMIC_ADMIN' => 'O',
        'FINANCIAL_ADMIN' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
    ],
    'audit.view' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'R',
        'FINANCIAL_ADMIN' => 'R',
    ],
    'settings.manage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'theme.manage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'foundation.demo' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'programs.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'programs.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'R',
        'INSTRUCTOR' => 'R',
        'TA' => 'R',
        'STUDENT' => 'R',
    ],
    'courses.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'courses.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'R',
        'INSTRUCTOR' => 'R',
        'TA' => 'R',
        'STUDENT' => 'R',
    ],
    'courses.flag_interest' => [
        'STUDENT' => 'O',
    ],
    'courses.interest_counts' => [
        'ACADEMIC_ADMIN' => 'R',
        'ADMINISTRATIVE_ADMIN' => 'R',
    ],
    'assessment_templates.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'grading_schemes.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'translations.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
];
