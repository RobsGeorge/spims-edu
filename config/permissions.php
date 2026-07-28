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
];
