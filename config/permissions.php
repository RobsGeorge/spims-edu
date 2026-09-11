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
    'help.manage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'help.view' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'R',
        'FINANCIAL_ADMIN' => 'R',
        'INSTRUCTOR' => 'R',
        'TA' => 'R',
        'STUDENT' => 'R',
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
        // Pricing lives on offering show/index. Financial Admin is R here; never offerings.manage.
        'FINANCIAL_ADMIN' => 'R',
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
    /*
     * Instructor O is VIEW ONLY on staffed offerings. Promote/override stay
     * Administrative Admin F (`enrollment.override`). Academic Admin stays R.
     * TA is not granted. Offering-scoped — authorize with the offering.
     */
    'enrollment.waitlist' => [
        'ACADEMIC_ADMIN' => 'R',
        'ADMINISTRATIVE_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    /*
     * Advising-lite. These keys are NOT offering-scoped — do not add them to
     * `permission_scopes.offering_scoped`. Instructor O is assigned-advisee
     * only and is enforced in AdvisingService (fail closed without a student).
     */
    'advising.assign' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'advising.hold' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    'advising.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'STUDENT' => 'O',
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
    /*
     * Grant for a human reviewing/acting on proctor events (e.g. viewing the log before
     * deciding whether to ask an admin to clear a termination). Distinct from
     * ProctorService::recordEvent(), which runs as a side effect of the student's own
     * self-scoped `assessments.take` action and is never gated by this key.
     */
    'assessments.proctor' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'assessments.announce_results' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    /*
     * Deliberately school-wide, not offering-scoped: clearing a cheating flag is an
     * admin override, not an instructor-level action. Do not add to
     * `permission_scopes.offering_scoped`.
     */
    'assessments.clear_termination' => [
        'ACADEMIC_ADMIN' => 'F',
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
    'assignments.dashboard' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assignments.remind' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'assignments.mark_received' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'gradebook.configure' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'R',
    ],
    'gradebook.lock' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'lock',
    ],
    'gradebook.reopen' => [
        'ACADEMIC_ADMIN' => 'reopen',
    ],
    'live.schedule' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'live.join' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'F',
        'STUDENT' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.record' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.view_all' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.view_own' => [
        'STUDENT' => 'O',
    ],
    'attendance.edit' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    'attendance.reopen' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'attendance.configure' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'attendance.report' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'attendance.self_check_in' => [
        'STUDENT' => 'O',
    ],
    'roster.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'roster.export' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'roster.announce' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
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
    'announcements.view' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'R',
        'FINANCIAL_ADMIN' => 'R',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
    ],
    'announcements.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'announcements.publish' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    'communications.report' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'R',
    ],
    /*
     * School-wide registrar/finance reports. Do not add to
     * `permission_scopes.offering_scoped`.
     */
    'reports.view' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'R',
        'FINANCIAL_ADMIN' => 'R',
    ],
    /*
     * School-wide GPA cutoffs. Do not add to permission_scopes.offering_scoped.
     * Financial admin keeps reports.view but cannot change standing thresholds.
     */
    'academic_standing.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'email_templates.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    'notifications.preferences' => [
        'ADMINISTRATIVE_ADMIN' => 'O',
        'ACADEMIC_ADMIN' => 'O',
        'FINANCIAL_ADMIN' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
    ],

    // S4 — completion criteria, offering closing, credentials.
    'completion.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'STUDENT' => 'O',
    ],
    'completion.configure' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'offering.close' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
    ],
    'certificate_templates.manage' => [
        'ACADEMIC_ADMIN' => 'F',
    ],
    'student_notes.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'student_notes.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'module_assessment.view' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'module_assessment.manage' => [
        'ACADEMIC_ADMIN' => 'F',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],

    // S6E surveys
    'feedback.view' => [
        'STUDENT' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'feedback.manage' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'feedback.report' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'feedback.identity.request' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'feedback.identity.reveal' => [
        // Super Admin only (empty role map is OK; Super Admin bypasses AuthorizeService)
    ],

    // S6E events
    'events.view' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'F',
        'FINANCIAL_ADMIN' => 'R',
        'INSTRUCTOR' => 'R',
        'TA' => 'R',
        'STUDENT' => 'O',
    ],
    'events.reserve' => [
        'STUDENT' => 'O',
    ],
    'events.admin' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'events.check_in' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'F',
    ],

    // S6E live quiz
    'live_quiz.play' => [
        'STUDENT' => 'O',
    ],
    'live_quiz.host' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
    ],
    'live_quiz.manage' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],

    // S6E projects
    'projects.view' => [
        'STUDENT' => 'O',
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'projects.join' => [
        'STUDENT' => 'O',
    ],
    'projects.manage' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'projects.grade' => [
        'INSTRUCTOR' => 'O',
        'TA' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'projects.announce' => [
        'INSTRUCTOR' => 'O',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'projects.peer_eval' => [
        'STUDENT' => 'O',
    ],

    /*
     * Legacy data import (Populi + Canvas -> SPIMS). None of these are offering-scoped
     * — they act school-wide by nature — so none appears in permission_scopes.php's
     * offering_scoped list; see the note there. A FINANCE-entity batch additionally
     * requires finance.manage and an ACTIVE-population batch additionally requires
     * users.manage, enforced in the controller rather than here, since those checks
     * depend on the specific batch being acted on. See docs/legacy-data-import-plan.md §12.
     */
    'import.view' => [
        'ADMINISTRATIVE_ADMIN' => 'R',
        'ACADEMIC_ADMIN' => 'R',
        'FINANCIAL_ADMIN' => 'R',
    ],
    'import.configure' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'import.stage' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
        'ACADEMIC_ADMIN' => 'F',
    ],
    'import.commit' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'import.rollback' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],

    /*
     * Super Admin control-plane keys (SA0+). Empty role maps: Super Admin bypasses
     * AuthorizeService. Roles Hub lists them so they stay visible and grantable later.
     */
    'features.manage' => [],
    'system_settings.manage' => [],
    'users.impersonate' => [],
    'users.unsuspend' => [
        'ADMINISTRATIVE_ADMIN' => 'F',
    ],
    'users.reset_password' => [],
    'audit.export' => [],
    'reports.school' => [],
    'ops.failed_jobs' => [],
    'ops.backup' => [],
];
