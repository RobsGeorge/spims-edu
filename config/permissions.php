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
    'roles.manage_matrix' => [],
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
    'semesters.manage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'semesters.view' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'R',
        'INSTRUCTOR' => 'R',
        'TA' => 'R',
        'STUDENT' => 'R',
    ],
    'offerings.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'offerings.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'R',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'R',
    ],
    'offerings.content' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'offerings.pricing' => [
        'FINANCIAL_ADMIN' => 'F',
    ],
    'admissions.forms' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'admissions.apply' => [
        'STUDENT' => 'O',
    ],
    'admissions.decide' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'admissions.review' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'enrollment.register' => [
        'STUDENT' => 'O',
    ],
    'enrollment.override' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'finance.invoices' => [
        'FINANCIAL_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'R',
    ],
    'finance.pay' => [
        'STUDENT' => 'O',
    ],
    'finance.manual' => [
        'FINANCIAL_ADMIN' => 'F',
    ],
    'finance.refunds' => [
        'FINANCIAL_ADMIN' => 'F',
    ],
    'finance.wallet' => [
        'FINANCIAL_ADMIN' => 'F',
    ],
    'finance.donate' => [
        'STUDENT' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'O',
        'ADMINISTRATIVE_ADMIN' => 'O',
        'FINANCIAL_ADMIN' => 'O',
    ],
    'questions.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assessments.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assessments.take' => [
        'STUDENT' => 'O',
    ],
    'assessments.grade' => [
        'ACADEMIC_ADMIN' => 'R',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assignments.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assignments.submit' => [
        'STUDENT' => 'O',
    ],
    'assignments.grade' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'R',
    ],
    'gradebook.configure' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'R',
    ],
    'gradebook.lock' => [
        'INSTRUCTOR' => 'lock',
    ],
    'gradebook.reopen' => [
        'ACADEMIC_ADMIN' => 'reopen',
    ],
    'live.schedule' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'R',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'live.join' => [
        'STUDENT' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'discussions.configure' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'discussions.thread' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
    ],
    'discussions.post' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
        'ACADEMIC_ADMIN' => 'O',
    ],
    'discussions.moderate' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'discussions.grade' => [
        'ACADEMIC_ADMIN' => 'R',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'credentials.issue' => [
        'ADMINISTRATIVE_ADMIN' => 'issue',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'transcript.view' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'STUDENT' => 'O',
    ],
];
