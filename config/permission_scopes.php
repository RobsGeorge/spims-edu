<?php

/**
 * Which permission grants are limited to a resource, and for whom.
 *
 * `config/permissions.php` records an `O` ("own") level against several roles, but a
 * level alone cannot say what "own" means: for a student it means "acting on my own
 * behalf", while for an instructor it means "an offering I am staffed on". This file
 * makes that distinction explicit so `AuthorizeService` can enforce it.
 */
return [

    /*
     * Permission keys where a grant held by a scoped role applies only to offerings the
     * actor is staffed on. Calling one of these without a resource fails closed.
     *
     * Keys deliberately absent are self-scoped: `assessments.take`, `assignments.submit`,
     * `discussions.post`, `discussions.thread`, `live.join`, `finance.pay`, `finance.donate`,
     * `admissions.apply`, `enrollment.register`, `courses.flag_interest`, `profile.edit_own`,
     * `transcript.view`, `attendance.view_own`, `attendance.self_check_in`, `live_quiz.play`,
     * `projects.join` and `projects.peer_eval` all describe acting on your own behalf, and
     * their membership checks live in the services that own the data.
     *
     * `import.view`, `import.configure`, `import.stage`, `import.commit`,
     * `import.rollback` and `import.merge_resolve` are also deliberately absent: a legacy
     * import batch is not owned by an offering, it is school-wide by nature. See
     * docs/legacy-data-import-plan.md §12.
     */
    'offering_scoped' => [
        'offerings.view',
        'offerings.content',
        'enrollment.waitlist',
        'questions.manage',
        'assessments.manage',
        'assessments.grade',
        'assessments.proctor',
        'assessments.announce_results',
        'assignments.manage',
        'assignments.grade',
        'assignments.dashboard',
        'assignments.remind',
        'assignments.mark_received',
        'gradebook.configure',
        'gradebook.lock',
        'live.schedule',
        'attendance.manage',
        'attendance.record',
        'attendance.view_all',
        'attendance.edit',
        'attendance.report',
        'roster.view',
        'roster.export',
        'roster.announce',
        'discussions.configure',
        'discussions.moderate',
        'discussions.grade',
        'announcements.manage',
        'announcements.publish',
        'email_templates.manage',
        'completion.view',
        'completion.configure',
        'offering.close',
        'student_notes.view',
        'student_notes.manage',
        'module_assessment.view',
        'module_assessment.manage',
        'feedback.manage',
        'feedback.report',
        'feedback.identity.request',
        'live_quiz.host',
        'live_quiz.manage',
        'projects.view',
        'projects.manage',
        'projects.grade',
        'projects.announce',
    ],

    /*
     * Roles whose grants on the keys above are confined to their own offerings. Every
     * other role (the admin tier) holds those grants school-wide, which is what the `F`
     * and `R` levels in the matrix already intend.
     */
    'scoped_roles' => [
        'INSTRUCTOR',
        'TA',
    ],

];
