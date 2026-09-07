<?php

use App\Http\Controllers\Admin\ApplicationFormController;
use App\Http\Controllers\Admin\ApplicationReviewController;
use App\Http\Controllers\Admin\AssessmentAdminController;
use App\Http\Controllers\Admin\AssessmentTemplateController;
use App\Http\Controllers\Admin\AttendanceAdminController;
use App\Http\Controllers\Admin\CertificateTemplateController;
use App\Http\Controllers\Admin\CommunicationAdminController;
use App\Http\Controllers\Admin\CompletionCriteriaController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CredentialAdminController;
use App\Http\Controllers\Admin\DiscussionAdminController;
use App\Http\Controllers\Admin\EmailTemplateAdminController;
use App\Http\Controllers\Admin\EnrollmentAdminController;
use App\Http\Controllers\Admin\EventAdminController;
use App\Http\Controllers\Admin\FinanceAdminController;
use App\Http\Controllers\Admin\GradebookController;
use App\Http\Controllers\Admin\GradingSchemeController;
use App\Http\Controllers\Admin\LiveSessionAdminController;
use App\Http\Controllers\Admin\OfferingClosingController;
use App\Http\Controllers\Admin\ContentItemController;
use App\Http\Controllers\Admin\OfferingController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\SurveyController as AdminSurveyController;
use App\Http\Controllers\Admin\ThemeEditorController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ZoomWebhookController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CommunicationOpenController;
use App\Http\Controllers\ContentItemFileController;
use App\Http\Controllers\CoursePlayerController;
use App\Http\Controllers\StudentPreviewController;
use App\Http\Controllers\CredentialDownloadController;
use App\Http\Controllers\CredentialVerifyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\AdvisingController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\Events\StudentEventController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FoundationDemoController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LiveQuizController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OfferingPreviewController;
use App\Http\Controllers\ProjectController as StudentProjectController;
use App\Http\Controllers\RolesHub\RolesHubController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentCompletionController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SuperAdmin\FeedbackRevealController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\Teach\AssessmentController as TeachAssessmentController;
use App\Http\Controllers\Teach\AssignmentController as TeachAssignmentController;
use App\Http\Controllers\Teach\AttendanceController as TeachAttendanceController;
use App\Http\Controllers\Teach\CompletionController as TeachCompletionController;
use App\Http\Controllers\Teach\DiscussionController as TeachDiscussionController;
use App\Http\Controllers\Teach\LiveQuizController as TeachLiveQuizController;
use App\Http\Controllers\Teach\LiveSessionController as TeachLiveSessionController;
use App\Http\Controllers\Teach\ProjectController as TeachProjectController;
use App\Http\Controllers\Teach\StudentController as TeachStudentController;
use App\Http\Controllers\Teach\SurveyController as TeachSurveyController;
use App\Http\Controllers\Teach\TeachController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TranscriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', HealthController::class)->name('health');
Route::get('/up', HealthController::class)->name('up');
Route::get('/api/branding', [BrandingController::class, 'show'])->name('api.branding');
Route::post('/api/webhooks/payments', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('api.webhooks.payments');
Route::post('/api/webhooks/zoom', ZoomWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('api.webhooks.zoom');
Route::get('/verify/{token}', CredentialVerifyController::class)->name('credentials.verify');
Route::get('/communications/open/{log}', CommunicationOpenController::class)->name('communications.open');
Route::get('/offerings/{offering}/preview', [OfferingPreviewController::class, 'show'])->name('offerings.preview');
Route::get('/offerings/{offering}/preview/items/{item}/file', [ContentItemFileController::class, 'publicPreview'])
    ->middleware('throttle:catalog-preview-file')
    ->name('offerings.preview.item.file');
Route::get('/api/offerings/{offering}/preview', [OfferingPreviewController::class, 'json'])->name('api.offerings.preview');
Route::get('/api/offerings/{offering}/pricing', [OfferingPreviewController::class, 'pricing'])->name('api.offerings.pricing');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth');
    Route::get('/login', [LoginController::class, 'create'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/verify-email', [VerifyEmailController::class, 'show'])->name('auth.verify');
    Route::post('/verify-email', [VerifyEmailController::class, 'store'])->middleware('throttle:auth');
    Route::get('/set-password', [SetPasswordController::class, 'create'])->name('auth.password.create');
    Route::post('/set-password', [SetPasswordController::class, 'store'])->middleware('throttle:auth');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('auth.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])->middleware('throttle:auth');
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('auth.password.reset.form');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth')
        ->name('auth.password.reset');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('auth.logout');
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/hubs/learning', [HubController::class, 'learning'])->name('hubs.learning');
    Route::get('/hubs/academic', [HubController::class, 'academic'])->name('hubs.academic');
    Route::get('/hubs/admin', [HubController::class, 'admin'])->name('hubs.admin');
    Route::get('/hubs/finance', [HubController::class, 'finance'])->name('hubs.finance');

    Route::get('/teach', [TeachController::class, 'index'])->name('teach.index');
    Route::get('/teach/{offering}', [TeachController::class, 'show'])->name('teach.show');
    Route::get('/teach/{offering}/students/{student}', [TeachStudentController::class, 'show'])
        ->name('teach.students.show');
    Route::post('/teach/{offering}/announcements', [TeachController::class, 'storeAnnouncement'])
        ->name('teach.announcements.store');
    Route::put('/teach/announcements/{announcement}', [TeachController::class, 'updateAnnouncement'])
        ->name('teach.announcements.update');
    Route::post('/teach/announcements/{announcement}/publish', [TeachController::class, 'publishAnnouncement'])
        ->name('teach.announcements.publish');
    Route::post('/teach/announcements/{announcement}/resend', [TeachController::class, 'resendAnnouncement'])
        ->name('teach.announcements.resend');

    Route::get('/surveys', [SurveyController::class, 'index'])
        ->middleware('permission:feedback.view')
        ->name('student.surveys.index');
    Route::get('/surveys/{survey}', [SurveyController::class, 'show'])
        ->middleware('permission:feedback.view')
        ->name('student.surveys.show');
    Route::post('/surveys/{survey}', [SurveyController::class, 'submit'])
        ->middleware('permission:feedback.view')
        ->name('student.surveys.submit');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::post('/announcements/{announcement}/dismiss-banner', [AnnouncementController::class, 'dismissBanner'])
        ->name('announcements.dismiss-banner');

    Route::get('/teach/{offering}/completion', [TeachCompletionController::class, 'show'])
        ->middleware('permission:completion.view')
        ->name('teach.completion.show');
    Route::post('/teach/{offering}/close', [TeachCompletionController::class, 'close'])
        ->name('teach.offerings.close');
    Route::post('/teach/{offering}/students/{student}/notes', [TeachCompletionController::class, 'storeNote'])
        ->middleware('permission:student_notes.manage')
        ->name('teach.completion.notes.store');
    Route::post('/teach/{offering}/weeks/{week}/students/{student}/assessment', [TeachCompletionController::class, 'rate'])
        ->middleware('permission:module_assessment.manage')
        ->name('teach.completion.assess');

    Route::get('/teach/{offering}/attendance', [TeachAttendanceController::class, 'index'])->name('teach.attendance.index');
    Route::post('/teach/{offering}/attendance/sessions', [TeachAttendanceController::class, 'store'])->name('teach.attendance.store');
    Route::get('/teach/{offering}/attendance/report.csv', [TeachAttendanceController::class, 'reportCsv'])->name('teach.attendance.report.csv');
    Route::get('/teach/{offering}/roster.csv', [TeachAttendanceController::class, 'rosterCsv'])->name('teach.attendance.roster.csv');
    Route::post('/teach/{offering}/roster/announce', [TeachAttendanceController::class, 'announce'])->name('teach.attendance.announce');
    Route::get('/teach/{offering}/sessions/{session}', [TeachAttendanceController::class, 'show'])->name('teach.attendance.show');
    Route::post('/teach/{offering}/sessions/{session}/attendance', [TeachAttendanceController::class, 'mark'])->name('teach.attendance.mark');
    Route::post('/teach/{offering}/sessions/{session}/fill-missing', [TeachAttendanceController::class, 'fillMissing'])->name('teach.attendance.fill-missing');
    Route::post('/teach/{offering}/sessions/{session}/close', [TeachAttendanceController::class, 'close'])->name('teach.attendance.close');
    Route::post('/teach/{offering}/sessions/{session}/reopen', [TeachAttendanceController::class, 'reopen'])->name('teach.attendance.reopen');
    Route::post('/teach/{offering}/sessions/{session}/excuse', [TeachAttendanceController::class, 'excuse'])->name('teach.attendance.excuse');
    Route::post('/teach/{offering}/sessions/{session}/check-in-code', [TeachAttendanceController::class, 'issueCode'])->name('teach.attendance.code');

    Route::get('/teach/{offering}/assessments/{assessment}/attempts', [TeachAssessmentController::class, 'attempts'])
        ->middleware('permission:assessments.grade')
        ->name('teach.assessments.attempts');
    Route::post('/teach/{offering}/assessments/{assessment}/answers/{attemptAnswer}/grade', [TeachAssessmentController::class, 'gradeAnswer'])
        ->middleware('permission:assessments.grade')
        ->name('teach.assessments.grade');
    Route::post('/teach/{offering}/assessments/{assessment}/announce-results', [TeachAssessmentController::class, 'announceResults'])
        ->middleware('permission:assessments.announce_results')
        ->name('teach.assessments.announce');

    Route::get('/teach/{offering}/assignments', [TeachAssignmentController::class, 'index'])->name('teach.assignments.index');
    Route::post('/teach/{offering}/assignments/{assignment}/remind', [TeachAssignmentController::class, 'remind'])->name('teach.assignments.remind');
    Route::post('/teach/{offering}/assignments/{assignment}/mark-received', [TeachAssignmentController::class, 'markReceived'])->name('teach.assignments.mark-received');
    Route::post('/teach/{offering}/assignments/{assignment}/bulk-grade', [TeachAssignmentController::class, 'bulkGradeOffline'])->name('teach.assignments.bulk-grade');

    Route::get('/teach/{offering}/discussions', [TeachDiscussionController::class, 'index'])
        ->name('teach.discussions.index');
    Route::post('/teach/{offering}/discussions/threads/{thread}/grade', [TeachDiscussionController::class, 'grade'])
        ->name('teach.discussions.grade');

    Route::get('/teach/{offering}/surveys', [TeachSurveyController::class, 'index'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.index');
    Route::post('/teach/{offering}/surveys', [TeachSurveyController::class, 'store'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.store');
    Route::get('/teach/{offering}/surveys/{survey}', [TeachSurveyController::class, 'show'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.show');
    Route::post('/teach/{offering}/surveys/{survey}/questions', [TeachSurveyController::class, 'addQuestion'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.questions.store');
    Route::post('/teach/{offering}/surveys/{survey}/publish', [TeachSurveyController::class, 'publish'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.publish');
    Route::post('/teach/{offering}/surveys/{survey}/close', [TeachSurveyController::class, 'close'])
        ->middleware('permission:feedback.manage')
        ->name('teach.surveys.close');
    Route::get('/teach/{offering}/surveys/{survey}/report', [TeachSurveyController::class, 'report'])
        ->middleware('permission:feedback.report')
        ->name('teach.surveys.report');
    Route::post('/teach/{offering}/surveys/{survey}/submissions/{submission}/reveal', [TeachSurveyController::class, 'requestReveal'])
        ->middleware('permission:feedback.identity.request')
        ->name('teach.surveys.reveals.store');

    Route::get('/teach/{offering}/projects', [TeachProjectController::class, 'index'])
        ->middleware('permission:projects.view')
        ->name('teach.projects.index');
    Route::post('/teach/{offering}/projects', [TeachProjectController::class, 'store'])
        ->middleware('permission:projects.manage')
        ->name('teach.projects.store');
    Route::get('/teach/{offering}/projects/{assessment}', [TeachProjectController::class, 'show'])
        ->middleware('permission:projects.view')
        ->name('teach.projects.show');
    Route::post('/teach/{offering}/projects/{assessment}/publish', [TeachProjectController::class, 'publish'])
        ->middleware('permission:projects.manage')
        ->name('teach.projects.publish');
    Route::post('/teach/{offering}/projects/{assessment}/move', [TeachProjectController::class, 'move'])
        ->middleware('permission:projects.manage')
        ->name('teach.projects.move');
    Route::post('/teach/{offering}/projects/{assessment}/merge', [TeachProjectController::class, 'merge'])
        ->middleware('permission:projects.manage')
        ->name('teach.projects.merge');
    Route::post('/teach/{offering}/projects/{assessment}/team-score', [TeachProjectController::class, 'teamScore'])
        ->middleware('permission:projects.grade')
        ->name('teach.projects.team-score');
    Route::post('/teach/{offering}/projects/{assessment}/student-score', [TeachProjectController::class, 'studentScore'])
        ->middleware('permission:projects.grade')
        ->name('teach.projects.student-score');
    Route::post('/teach/{offering}/projects/{assessment}/announce', [TeachProjectController::class, 'announce'])
        ->middleware('permission:projects.announce')
        ->name('teach.projects.announce');
    Route::get('/teach/{offering}/projects/{assessment}/submissions/{submission}', [TeachProjectController::class, 'showSubmission'])
        ->middleware('permission:projects.grade')
        ->name('teach.projects.submissions.show');
    Route::post('/teach/{offering}/projects/{assessment}/submissions/{submission}/review', [TeachProjectController::class, 'reviewSubmission'])
        ->middleware('permission:projects.grade')
        ->name('teach.projects.submissions.review');

    Route::get('/teach/{offering}/live', [TeachLiveSessionController::class, 'index'])
        ->middleware('permission:live.schedule')
        ->name('teach.live.index');
    Route::post('/teach/{offering}/live/{liveSession}/attendance/import', [TeachLiveSessionController::class, 'importAttendance'])
        ->middleware('permission:attendance.manage')
        ->name('teach.live.attendance.import');

    Route::get('/teach/{offering}/live-quiz', [TeachLiveQuizController::class, 'index'])
        ->middleware('permission:live_quiz.manage')
        ->name('teach.live-quiz.index');
    Route::post('/teach/{offering}/live-quiz', [TeachLiveQuizController::class, 'store'])
        ->middleware('permission:live_quiz.manage')
        ->name('teach.live-quiz.store');
    Route::post('/teach/{offering}/live-quiz/{quiz}/questions', [TeachLiveQuizController::class, 'addQuestion'])
        ->middleware('permission:live_quiz.manage')
        ->name('teach.live-quiz.questions.store');
    Route::post('/teach/{offering}/live-quiz/{quiz}/start', [TeachLiveQuizController::class, 'start'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.start');
    Route::get('/teach/{offering}/live-quiz/sessions/{session}', [TeachLiveQuizController::class, 'session'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.session');
    Route::post('/teach/{offering}/live-quiz/sessions/{session}/launch', [TeachLiveQuizController::class, 'launch'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.launch');
    Route::post('/teach/{offering}/live-quiz/sessions/{session}/close', [TeachLiveQuizController::class, 'closeQuestion'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.close');
    Route::post('/teach/{offering}/live-quiz/sessions/{session}/results', [TeachLiveQuizController::class, 'results'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.results');
    Route::post('/teach/{offering}/live-quiz/sessions/{session}/end', [TeachLiveQuizController::class, 'end'])
        ->middleware('permission:live_quiz.host')
        ->name('teach.live-quiz.end');

    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->middleware('permission:attendance.view_own')
        ->name('attendance.index');
    Route::get('/attendance/check-in', [AttendanceController::class, 'checkInForm'])
        ->middleware('permission:attendance.self_check_in')
        ->name('attendance.check-in');
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])
        ->middleware('permission:attendance.self_check_in')
        ->name('attendance.check-in.store');

    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/security', [SuperAdminController::class, 'security'])->name('security');
        Route::post('/sessions/flush', [SuperAdminController::class, 'flushSessions'])->name('sessions.flush');
        Route::get('/audit', [SuperAdminController::class, 'audit'])->name('audit.index');
        Route::get('/observability', [SuperAdminController::class, 'observability'])->name('observability.index');
        Route::get('/scheduled-tasks', [SuperAdminController::class, 'scheduledTasks'])->name('scheduled-tasks.index');
        Route::get('/system-tests', [SuperAdminController::class, 'systemTests'])->name('system-tests.index');
        Route::get('/feedback-reveals', [FeedbackRevealController::class, 'index'])->name('feedback-reveals.index');
        Route::post('/feedback-reveals/{reveal}/decide', [FeedbackRevealController::class, 'decide'])->name('feedback-reveals.decide');
    });

    Route::middleware('superadmin')->prefix('roles-hub')->group(function () {
        Route::get('/', [RolesHubController::class, 'index'])->name('roles.hub');
        Route::put('/roles/{role}', [RolesHubController::class, 'updateRole'])->name('roles.hub.role.update');
    });

    Route::get('/api/me', [MeController::class, 'show'])->name('api.me');
    Route::post('/api/uploads', [UploadController::class, 'store'])->name('api.uploads.store');
    Route::post('/catalog/{course}/interest', [CatalogController::class, 'flagInterest'])
        ->middleware('permission:courses.flag_interest')
        ->name('catalog.interest');

    Route::post('/offerings/{offering}/view-as-student', [StudentPreviewController::class, 'start'])
        ->middleware('permission:offerings.view')
        ->name('offerings.preview.student');
    Route::post('/offerings/{offering}/view-as-student/stop', [StudentPreviewController::class, 'stop'])
        ->middleware('permission:offerings.view')
        ->name('offerings.preview.stop');
    Route::get('/courses/{offering}', [CoursePlayerController::class, 'show'])
        ->middleware('permission:offerings.view')
        ->name('courses.player');
    Route::post('/courses/{offering}/weeks/{week}/complete', [CoursePlayerController::class, 'completeWeek'])
        ->middleware('permission:offerings.view')
        ->name('courses.weeks.complete');

    Route::get('/grades', [GradesController::class, 'index'])
        ->middleware('permission:transcript.view')
        ->name('grades.index');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:profile.edit_own')
        ->name('settings.update');
    Route::post('/settings/picture', [SettingsController::class, 'storePicture'])
        ->middleware('permission:profile.edit_own')
        ->name('settings.picture');
    Route::get('/settings/notifications', [NotificationSettingsController::class, 'edit'])
        ->name('settings.notifications.edit');
    Route::put('/settings/notifications', [NotificationSettingsController::class, 'update'])
        ->name('settings.notifications.update');
    Route::post('/settings/reminders', [NotificationSettingsController::class, 'storeReminder'])
        ->name('settings.reminders.store');
    Route::delete('/settings/reminders/{reminder}', [NotificationSettingsController::class, 'cancelReminder'])
        ->name('settings.reminders.cancel');

    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/forms/{form}', [ApplicationController::class, 'create'])
        ->middleware('permission:admissions.apply')
        ->name('applications.create');
    Route::post('/applications/{application}', [ApplicationController::class, 'store'])
        ->middleware('permission:admissions.apply')
        ->name('applications.store');
    Route::post('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])
        ->middleware('permission:admissions.apply')
        ->name('applications.withdraw'); // application withdraw

    Route::get('/projects', [StudentProjectController::class, 'mine'])
        ->middleware('permission:projects.view')
        ->name('student.projects.mine');
    Route::get('/offerings/{offering}/projects', [StudentProjectController::class, 'index'])
        ->middleware('permission:projects.view')
        ->name('student.projects.index');
    Route::post('/offerings/{offering}/projects/{assessment}/join', [StudentProjectController::class, 'join'])
        ->middleware('permission:projects.join')
        ->name('student.projects.join');
    Route::post('/offerings/{offering}/projects/{assessment}/leave', [StudentProjectController::class, 'leave'])
        ->middleware('permission:projects.join')
        ->name('student.projects.leave');
    Route::get('/projects/{project}', [StudentProjectController::class, 'show'])
        ->middleware('permission:projects.view')
        ->name('student.projects.show');
    Route::post('/projects/{project}/deliverables/{deliverable}', [StudentProjectController::class, 'submit'])
        ->middleware('permission:projects.join')
        ->name('student.projects.submit');
    Route::delete('/projects/{project}/submission-files/{file}', [StudentProjectController::class, 'destroyFile'])
        ->middleware('permission:projects.join')
        ->name('student.projects.files.destroy');
    Route::post('/projects/{project}/peer-evaluations', [StudentProjectController::class, 'storePeerEvaluation'])
        ->middleware('permission:projects.peer_eval')
        ->name('student.projects.peer.store');

    Route::get('/learn/{offering}', [LearnController::class, 'offering'])
        ->middleware('permission:offerings.view')
        ->name('learn.offering');
    Route::get('/learn/{offering}/weeks/{week}', [LearnController::class, 'week'])
        ->middleware('permission:offerings.view')
        ->name('learn.week');
    Route::get('/learn/{offering}/items/{item}', [LearnController::class, 'item'])
        ->middleware('permission:offerings.view')
        ->name('learn.item');
    Route::get('/learn/items/{item}/file', [ContentItemFileController::class, 'show'])
        ->middleware('permission:offerings.view')
        ->name('learn.item.file');
    Route::post('/learn/{offering}/items/{item}/complete', [LearnController::class, 'complete'])
        ->middleware('permission:offerings.view')
        ->name('learn.item.complete');

    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])
        ->middleware('permission:enrollment.register')
        ->name('enrollments.store');
    Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop'])
        ->middleware('permission:enrollment.register')
        ->name('enrollments.drop');
    Route::post('/enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])
        ->middleware('permission:enrollment.register')
        ->name('enrollments.withdraw');
    Route::get('/degree-audit/{studentProgram}', [EnrollmentController::class, 'audit'])->name('enrollments.audit');

    // #16 advising + what-if
    Route::get('/advising', [AdvisingController::class, 'index'])->name('advising.index');
    Route::post('/advising/assign', [AdvisingController::class, 'assign'])->name('advising.assign');
    Route::get('/advising/students/{student}', [AdvisingController::class, 'show'])->name('advising.show');
    Route::post('/advising/students/{student}/holds', [AdvisingController::class, 'placeHold'])->name('advising.holds.store');
    Route::post('/advising/holds/{hold}/release', [AdvisingController::class, 'releaseHold'])->name('advising.holds.release');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/invoices/{invoice}', [FinanceController::class, 'showInvoice'])->name('finance.invoices.show');
    Route::get('/finance/receipts/{payment}', [FinanceController::class, 'showReceipt'])->name('finance.receipts.show');
    Route::post('/finance/invoices/{invoice}/checkout', [FinanceController::class, 'checkout'])
        ->middleware('permission:finance.pay')
        ->name('finance.checkout');
    // #17 payment plans + gateways
    Route::post('/finance/invoices/{invoice}/payment-plan', [FinanceController::class, 'storePaymentPlan'])
        ->middleware('permission:finance.pay')
        ->name('finance.payment-plan.store');
    Route::post('/finance/invoices/{invoice}/installments/{installment}/pay', [FinanceController::class, 'payInstallment'])
        ->middleware('permission:finance.pay')
        ->name('finance.installments.pay');
    // #13 live recurrence + refund
    Route::post('/finance/payments/{payment}/refund-request', [FinanceController::class, 'requestRefund'])
        ->middleware('permission:finance.pay')
        ->name('finance.refund-request');
    Route::get('/donate', [DonationController::class, 'create'])
        ->middleware('permission:finance.donate')
        ->name('donate.create');
    Route::post('/donate', [DonationController::class, 'store'])
        ->middleware('permission:finance.donate')
        ->name('donate.store');

    Route::get('/assessments/{assessment}', [ExamAttemptController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/start', [ExamAttemptController::class, 'start'])
        ->middleware('permission:assessments.take')
        ->name('assessments.start');
    Route::get('/attempts/{attempt}', [ExamAttemptController::class, 'runner'])->name('assessments.runner');
    Route::post('/attempts/{attempt}/save', [ExamAttemptController::class, 'save'])
        ->middleware('permission:assessments.take')
        ->name('assessments.save');
    Route::post('/attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])
        ->middleware('permission:assessments.take')
        ->name('assessments.submit');
    Route::post('/attempts/{attempt}/upload', [ExamAttemptController::class, 'upload'])
        ->middleware('permission:assessments.take')
        ->name('assessments.upload');
    Route::post('/attempts/{attempt}/focus-loss', [ExamAttemptController::class, 'focusLoss'])
        ->middleware('permission:assessments.take')
        ->name('assessments.focus-loss');
    Route::get('/attempts/{attempt}/timer', [ExamAttemptController::class, 'timer'])->name('assessments.timer');

    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])
        ->middleware('permission:assignments.submit')
        ->name('assignments.submit');

    Route::get('/live', [LiveSessionController::class, 'index'])->name('live.index');
    Route::post('/live/{session}/join', [LiveSessionController::class, 'join'])
        ->middleware('permission:live.join')
        ->name('live.join');

    Route::get('/live-quiz/join', [LiveQuizController::class, 'create'])
        ->middleware('permission:live_quiz.play')
        ->name('live-quiz.join');
    Route::post('/live-quiz/join', [LiveQuizController::class, 'join'])
        ->middleware('permission:live_quiz.play')
        ->name('live-quiz.join.store');
    Route::get('/live-quiz/sessions/{session}', [LiveQuizController::class, 'show'])
        ->middleware('permission:live_quiz.play')
        ->name('live-quiz.sessions.show');
    Route::post('/live-quiz/sessions/{session}/questions/{question}/answer', [LiveQuizController::class, 'answer'])
        ->middleware('permission:live_quiz.play')
        ->name('live-quiz.sessions.answer');

    Route::get('/events', [StudentEventController::class, 'index'])
        ->middleware('permission:events.view')
        ->name('events.index');
    Route::get('/events/mine', [StudentEventController::class, 'mine'])
        ->middleware('permission:events.view')
        ->name('events.mine');
    Route::get('/events/{event}', [StudentEventController::class, 'show'])
        ->middleware('permission:events.view')
        ->name('events.show');
    Route::post('/events/{event}/reserve', [StudentEventController::class, 'reserve'])
        ->middleware('permission:events.reserve')
        ->name('events.reserve');
    Route::post('/events/{event}/cancel', [StudentEventController::class, 'cancel'])
        ->middleware('permission:events.reserve')
        ->name('events.cancel');

    Route::get('/offerings/{offering}/discussions', [DiscussionController::class, 'showBoard'])->name('discussions.board');
    Route::post('/offerings/{offering}/discussions/threads', [DiscussionController::class, 'storeThread'])
        ->middleware('permission:discussions.thread')
        ->name('discussions.threads.store');
    Route::get('/discussions/threads/{thread}', [DiscussionController::class, 'showThread'])->name('discussions.thread');
    Route::post('/discussions/threads/{thread}/posts', [DiscussionController::class, 'storePost'])
        ->middleware('permission:discussions.post')
        ->name('discussions.posts.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/transcript', TranscriptController::class)
        ->middleware('permission:transcript.view')
        ->name('transcript.show');

    Route::get('/offerings/{offering}/completion', [StudentCompletionController::class, 'show'])
        ->middleware('permission:completion.view')
        ->name('offerings.completion');

    Route::get('/credentials/{credential}/download', CredentialDownloadController::class)
        ->name('credentials.download');

    Route::post('/foundation/demo', [FoundationDemoController::class, 'mutate'])
        ->middleware('permission:foundation.demo')
        ->name('foundation.demo');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.manage')
            ->name('users.show');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:users.manage')
            ->name('users.suspend');
        // #12 admissions/users/discussions
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole'])
            ->middleware('permission:roles.assign')
            ->name('users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole'])
            ->middleware('permission:roles.assign')
            ->name('users.roles.remove');

        Route::get('/theme', [ThemeEditorController::class, 'edit'])
            ->middleware('permission:theme.manage')
            ->name('theme.edit');
        Route::put('/theme/{theme}', [ThemeEditorController::class, 'update'])
            ->middleware('permission:theme.manage')
            ->name('theme.update');

        Route::get('/programs', [ProgramController::class, 'index'])
            ->middleware('permission:programs.view')
            ->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])
            ->middleware('permission:programs.manage')
            ->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])
            ->middleware('permission:programs.manage')
            ->name('programs.store');
        Route::get('/programs/{program}', [ProgramController::class, 'show'])
            ->middleware('permission:programs.view')
            ->name('programs.show');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])
            ->middleware('permission:programs.manage')
            ->name('programs.edit');
        Route::match(['put', 'patch'], '/programs/{program}', [ProgramController::class, 'update'])
            ->middleware('permission:programs.manage')
            ->name('programs.update');
        Route::post('/programs/{program}/courses', [ProgramController::class, 'attachCourse'])
            ->middleware('permission:programs.manage')
            ->name('programs.attach-course');
        Route::delete('/programs/{program}/courses/{programCourse}', [ProgramController::class, 'detachCourse'])
            ->middleware('permission:programs.manage')
            ->name('programs.detach-course');

        Route::get('/courses', [CourseController::class, 'index'])
            ->middleware('permission:courses.view')
            ->name('courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])
            ->middleware('permission:courses.manage')
            ->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])
            ->middleware('permission:courses.manage')
            ->name('courses.store');
        Route::get('/courses/{course}', [CourseController::class, 'show'])
            ->middleware('permission:courses.view')
            ->name('courses.show');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])
            ->middleware('permission:courses.manage')
            ->name('courses.edit');
        Route::match(['put', 'patch'], '/courses/{course}', [CourseController::class, 'update'])
            ->middleware('permission:courses.manage')
            ->name('courses.update');
        Route::post('/courses/{course}/prerequisites', [CourseController::class, 'addPrerequisite'])
            ->middleware('permission:courses.manage')
            ->name('courses.prerequisites');
        Route::delete('/courses/{course}/prerequisites/{prerequisite}', [CourseController::class, 'removePrerequisite'])
            ->middleware('permission:courses.manage')
            ->name('courses.detach-prerequisite');

        Route::get('/assessment-templates', [AssessmentTemplateController::class, 'index'])
            ->middleware('permission:assessment_templates.manage')
            ->name('assessment-templates.index');
        Route::post('/assessment-templates', [AssessmentTemplateController::class, 'store'])
            ->middleware('permission:assessment_templates.manage')
            ->name('assessment-templates.store');

        Route::get('/grading-schemes', [GradingSchemeController::class, 'index'])
            ->middleware('permission:grading_schemes.manage')
            ->name('grading-schemes.index');
        Route::post('/grading-schemes', [GradingSchemeController::class, 'store'])
            ->middleware('permission:grading_schemes.manage')
            ->name('grading-schemes.store');
        Route::put('/grading-schemes/{gradingScheme}', [GradingSchemeController::class, 'update'])
            ->middleware('permission:grading_schemes.manage')
            ->name('grading-schemes.update');

        Route::get('/translations', [TranslationController::class, 'index'])
            ->middleware('permission:translations.manage')
            ->name('translations.index');
        Route::post('/translations', [TranslationController::class, 'store'])
            ->middleware('permission:translations.manage')
            ->name('translations.store');
        Route::post('/translations/{translation}/verify', [TranslationController::class, 'verify'])
            ->middleware('permission:translations.manage')
            ->name('translations.verify');
        Route::post('/translations/ai', [TranslationController::class, 'requestAi'])
            ->middleware('permission:translations.manage')
            ->name('translations.ai');

        Route::get('/semesters', [SemesterController::class, 'index'])
            ->middleware('permission:semesters.view')
            ->name('semesters.index');
        Route::post('/academic-years', [SemesterController::class, 'storeYear'])
            ->middleware('permission:semesters.manage')
            ->name('academic-years.store');
        Route::post('/academic-years/{year}/semesters', [SemesterController::class, 'storeSemester'])
            ->middleware('permission:semesters.manage')
            ->name('semesters.store');
        Route::get('/academic-years/{year}/edit', [SemesterController::class, 'editYear'])
            ->middleware('permission:semesters.manage')
            ->name('academic-years.edit');
        Route::put('/academic-years/{year}', [SemesterController::class, 'updateYear'])
            ->middleware('permission:semesters.manage')
            ->name('academic-years.update');
        Route::get('/semesters/{semester}/edit', [SemesterController::class, 'editSemester'])
            ->middleware('permission:semesters.manage')
            ->name('semesters.edit');
        Route::put('/semesters/{semester}', [SemesterController::class, 'updateSemester'])
            ->middleware('permission:semesters.manage')
            ->name('semesters.update');

        Route::get('/offerings', [OfferingController::class, 'index'])
            ->middleware('permission:offerings.view')
            ->name('offerings.index');
        Route::get('/offerings/create', [OfferingController::class, 'create'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.create');
        Route::post('/offerings', [OfferingController::class, 'store'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.store');
        Route::get('/offerings/{offering}', [OfferingController::class, 'show'])
            ->middleware('permission:offerings.view')
            ->name('offerings.show');
        Route::get('/offerings/{offering}/edit', [OfferingController::class, 'edit'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.edit');
        Route::match(['put', 'patch'], '/offerings/{offering}', [OfferingController::class, 'update'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.update');
        Route::post('/offerings/{offering}/staff', [OfferingController::class, 'assignStaff'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.staff');
        Route::delete('/offerings/{offering}/staff/{staff}', [OfferingController::class, 'removeStaff'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.unstaff');
        Route::post('/offerings/{offering}/pricing', [OfferingController::class, 'setPricing'])
            ->middleware('permission:offerings.pricing')
            ->name('offerings.pricing');
        Route::post('/offerings/{offering}/weeks', [OfferingController::class, 'addWeek'])
            ->middleware('permission:offerings.content')
            ->name('offerings.weeks');
        Route::post('/weeks/{week}/items', [ContentItemController::class, 'store'])
            ->middleware('permission:offerings.content')
            ->name('weeks.items');
        Route::match(['put', 'patch'], '/content-items/{item}', [ContentItemController::class, 'update'])
            ->middleware('permission:offerings.content')
            ->name('content-items.update');
        Route::delete('/content-items/{item}', [ContentItemController::class, 'destroy'])
            ->middleware('permission:offerings.content')
            ->name('content-items.destroy');
        Route::post('/content-items/{item}/publish', [ContentItemController::class, 'publish'])
            ->middleware('permission:offerings.content')
            ->name('content-items.publish');
        Route::post('/content-items/{item}/unpublish', [ContentItemController::class, 'unpublish'])
            ->middleware('permission:offerings.content')
            ->name('content-items.unpublish');
        Route::post('/content-items/{item}/move-up', [ContentItemController::class, 'moveUp'])
            ->middleware('permission:offerings.content')
            ->name('content-items.move-up');
        Route::post('/content-items/{item}/move-down', [ContentItemController::class, 'moveDown'])
            ->middleware('permission:offerings.content')
            ->name('content-items.move-down');
        Route::post('/content-items/{item}/move', [ContentItemController::class, 'move'])
            ->middleware('permission:offerings.content')
            ->name('content-items.move');

        Route::get('/application-forms', [ApplicationFormController::class, 'index'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.index');
        Route::post('/application-forms', [ApplicationFormController::class, 'store'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.store');
        Route::get('/application-forms/{form}', [ApplicationFormController::class, 'show'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.show');
        Route::put('/application-forms/{form}', [ApplicationFormController::class, 'update'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.update');
        Route::post('/application-forms/{form}/fields', [ApplicationFormController::class, 'addField'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.fields.store');
        Route::post('/application-forms/{form}/fields/{field}/deactivate', [ApplicationFormController::class, 'deactivateField'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.fields.deactivate');

        Route::get('/applications', [ApplicationReviewController::class, 'index'])
            ->middleware('permission:admissions.review')
            ->name('applications.index');
        Route::get('/applications/{application}', [ApplicationReviewController::class, 'show'])
            ->middleware('permission:admissions.review')
            ->name('applications.show');
        Route::post('/applications/{application}/decide', [ApplicationReviewController::class, 'decide'])
            ->middleware('permission:admissions.decide')
            ->name('applications.decide');

        Route::get('/enrollments', [EnrollmentAdminController::class, 'index'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.index');
        Route::post('/enrollments/override', [EnrollmentAdminController::class, 'overrideRegister'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.override');
        Route::post('/users/{user}/financial-hold', [EnrollmentAdminController::class, 'financialHold'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.financial-hold');
        Route::get('/offerings/{offering}/waitlist', [EnrollmentAdminController::class, 'waitlist'])
            ->middleware('permission:enrollment.waitlist')
            ->name('enrollments.waitlist');

        Route::get('/finance', [FinanceAdminController::class, 'index'])
            ->middleware('permission:finance.invoices')
            ->name('finance.index');
        Route::get('/finance/reports', [FinanceAdminController::class, 'reports'])
            ->name('finance.reports');
        Route::post('/finance/invoices', [FinanceAdminController::class, 'storeInvoice'])
            ->middleware('permission:finance.invoices')
            ->name('finance.invoices.store');
        // #17 payment plans + gateways
        Route::get('/finance/invoices/{invoice}', [FinanceAdminController::class, 'showInvoice'])
            ->middleware('permission:finance.invoices')
            ->name('finance.invoices.show');
        Route::post('/finance/invoices/{invoice}/payment-plan', [FinanceAdminController::class, 'attachPaymentPlan'])
            ->middleware('permission:finance.invoices')
            ->name('finance.payment-plan.store');
        Route::post('/finance/invoices/{invoice}/manual', [FinanceAdminController::class, 'recordManual'])
            ->middleware('permission:finance.manual')
            ->name('finance.manual');
        Route::post('/finance/payments/{payment}/verify', [FinanceAdminController::class, 'verifyManual'])
            ->middleware('permission:finance.manual')
            ->name('finance.verify');
        Route::post('/finance/points', [FinanceAdminController::class, 'grantPoints'])
            ->middleware('permission:finance.wallet')
            ->name('finance.points');
        Route::post('/finance/top-up', [FinanceAdminController::class, 'topUp'])
            ->middleware('permission:finance.wallet')
            ->name('finance.top-up');
        Route::post('/finance/refunds/{refund}/approve', [FinanceAdminController::class, 'approveRefund'])
            ->middleware('permission:finance.refunds')
            ->name('finance.refunds.approve');

        // #15 reports + standing
        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/headcount', [ReportController::class, 'headcount'])
            ->name('reports.headcount');
        Route::get('/reports/admissions', [ReportController::class, 'admissions'])
            ->name('reports.admissions');
        Route::get('/reports/attendance', [ReportController::class, 'attendance'])
            ->name('reports.attendance');
        Route::get('/reports/grades', [ReportController::class, 'grades'])
            ->name('reports.grades');
        Route::get('/reports/finance', [ReportController::class, 'finance'])
            ->name('reports.finance');
        Route::get('/reports/standing', [ReportController::class, 'standing'])
            ->name('reports.standing');
        // leftover polish
        Route::get('/reports/standing/thresholds', [ReportController::class, 'standingThresholds'])
            ->name('reports.standing.thresholds');
        Route::post('/reports/standing/thresholds', [ReportController::class, 'updateStandingThresholds'])
            ->name('reports.standing.thresholds.update');
        Route::get('/reports/{report}/csv', [ReportController::class, 'csv'])
            ->where('report', 'headcount|admissions|attendance|grades|finance|standing')
            ->name('reports.csv');

        Route::get('/courses/{course}/banks', [AssessmentAdminController::class, 'banksIndex'])
            ->middleware('permission:questions.manage')
            ->name('banks.index');
        Route::post('/courses/{course}/banks', [AssessmentAdminController::class, 'storeBank'])
            ->middleware('permission:questions.manage')
            ->name('banks.store');
        Route::post('/banks/{bank}/questions', [AssessmentAdminController::class, 'storeQuestion'])
            ->middleware('permission:questions.manage')
            ->name('banks.questions');
        Route::get('/offerings/{offering}/assessments/create', [AssessmentAdminController::class, 'createAssessment'])
            ->middleware('permission:assessments.manage')
            ->name('assessments.create');
        Route::post('/offerings/{offering}/assessments', [AssessmentAdminController::class, 'storeAssessment'])
            ->middleware('permission:assessments.manage')
            ->name('assessments.store');
        Route::get('/assessments/{assessment}', [AssessmentAdminController::class, 'show'])
            ->middleware('permission:assessments.manage')
            ->name('assessments.show');
        Route::post('/assessments/{assessment}/questions', [AssessmentAdminController::class, 'attachQuestion'])
            ->middleware('permission:assessments.manage')
            ->name('assessments.attach');
        Route::post('/assessments/{assessment}/release', [AssessmentAdminController::class, 'release'])
            ->middleware('permission:assessments.manage')
            ->name('assessments.release');
        Route::post('/assessments/{assessment}/announce', [AssessmentAdminController::class, 'announceResults'])
            ->middleware('permission:assessments.announce_results')
            ->name('assessments.announce');
        Route::post('/answers/{answer}/grade', [AssessmentAdminController::class, 'overrideScore'])
            ->middleware('permission:assessments.grade')
            ->name('answers.grade');
        Route::get('/attempts/{attempt}/proctor', [AssessmentAdminController::class, 'proctorEvents'])
            ->middleware('permission:assessments.proctor')
            ->name('attempts.proctor');
        Route::post('/attempts/{attempt}/clear-termination', [AssessmentAdminController::class, 'clearTermination'])
            ->middleware('permission:assessments.clear_termination')
            ->name('attempts.clear-termination');

        Route::get('/offerings/{offering}/gradebook', [GradebookController::class, 'show'])
            ->middleware('permission:gradebook.configure')
            ->name('gradebook.show');
        Route::get('/offerings/{offering}/gradebook.csv', [GradebookController::class, 'export'])
            ->middleware('permission:gradebook.configure')
            ->name('gradebook.csv');
        Route::post('/offerings/{offering}/gradebook/components', [GradebookController::class, 'addComponent'])
            ->middleware('permission:gradebook.configure')
            ->name('gradebook.components');
        Route::post('/offerings/{offering}/gradebook/seed', [GradebookController::class, 'seedTemplate'])
            ->middleware('permission:gradebook.configure')
            ->name('gradebook.seed');
        Route::post('/offerings/{offering}/gradebook/submit', [GradebookController::class, 'submit'])
            ->middleware('permission:gradebook.lock')
            ->name('gradebook.submit');
        Route::post('/offerings/{offering}/gradebook/lock', [GradebookController::class, 'lock'])
            ->middleware('permission:gradebook.lock')
            ->name('gradebook.lock');
        Route::post('/offerings/{offering}/gradebook/reopen', [GradebookController::class, 'reopen'])
            ->middleware('permission:gradebook.reopen')
            ->name('gradebook.reopen');
        Route::post('/content-items/{item}/assignments', [GradebookController::class, 'storeAssignment'])
            ->middleware('permission:assignments.manage')
            ->name('assignments.store');
        Route::post('/submissions/{submission}/grade', [GradebookController::class, 'gradeSubmission'])
            ->middleware('permission:assignments.grade')
            ->name('submissions.grade');

        Route::get('/offerings/{offering}/live', [LiveSessionAdminController::class, 'index'])
            ->middleware('permission:live.schedule')
            ->name('live.index');
        Route::post('/offerings/{offering}/live', [LiveSessionAdminController::class, 'store'])
            ->middleware('permission:live.schedule')
            ->name('live.store');
        // #13 live recurrence + refund
        Route::post('/offerings/{offering}/live/recurrence', [LiveSessionAdminController::class, 'storeRecurrence'])
            ->middleware('permission:live.schedule')
            ->name('live.recurrence');
        Route::post('/live/{session}/attendance/import', [LiveSessionAdminController::class, 'importAttendance'])
            ->middleware('permission:attendance.manage')
            ->name('live.attendance.import');
        Route::post('/live/{session}/attendance/override', [LiveSessionAdminController::class, 'overrideAttendance'])
            ->middleware('permission:attendance.manage')
            ->name('live.attendance.override');

        Route::get('/attendance/policy', [AttendanceAdminController::class, 'policy'])
            ->middleware('permission:attendance.configure')
            ->name('attendance.policy');
        Route::post('/attendance/policy', [AttendanceAdminController::class, 'savePolicy'])
            ->middleware('permission:attendance.configure')
            ->name('attendance.policy.save');
        Route::get('/attendance/report', [AttendanceAdminController::class, 'report'])
            ->middleware('permission:attendance.report')
            ->name('attendance.report');

        Route::post('/offerings/{offering}/discussions/configure', [DiscussionAdminController::class, 'configure'])
            ->middleware('permission:discussions.configure')
            ->name('discussions.configure');
        Route::post('/discussions/threads/{thread}/moderate', [DiscussionAdminController::class, 'moderate'])
            ->middleware('permission:discussions.moderate')
            ->name('discussions.moderate');
        Route::post('/discussions/threads/{thread}/grade', [DiscussionAdminController::class, 'grade'])
            ->middleware('permission:discussions.grade')
            ->name('discussions.grade');

        Route::get('/credentials', [CredentialAdminController::class, 'index'])
            ->middleware('permission:credentials.issue')
            ->name('credentials.index');
        Route::post('/credentials', [CredentialAdminController::class, 'store'])
            ->middleware('permission:credentials.issue')
            ->name('credentials.store');
        Route::post('/credentials/{credential}/regenerate', [CredentialAdminController::class, 'regenerate'])
            ->middleware('permission:credentials.issue')
            ->name('credentials.regenerate');

        Route::get('/courses/{course}/completion-criteria', [CompletionCriteriaController::class, 'index'])
            ->middleware('permission:completion.configure')
            ->name('completion-criteria.index');
        Route::post('/courses/{course}/completion-criteria', [CompletionCriteriaController::class, 'store'])
            ->middleware('permission:completion.configure')
            ->name('completion-criteria.store');
        Route::delete('/completion-criteria/{criterion}', [CompletionCriteriaController::class, 'destroy'])
            ->middleware('permission:completion.configure')
            ->name('completion-criteria.destroy');

        Route::get('/offerings/{offering}/closing', [OfferingClosingController::class, 'show'])
            ->middleware('permission:offering.close')
            ->name('offering-closing.show');
        Route::post('/offerings/{offering}/closing/evaluate', [OfferingClosingController::class, 'evaluate'])
            ->middleware('permission:completion.configure')
            ->name('offering-closing.evaluate');
        Route::post('/offerings/{offering}/closing/lock', [OfferingClosingController::class, 'lock'])
            ->middleware('permission:offering.close')
            ->name('offering-closing.lock');
        Route::post('/offerings/{offering}/closing/grace', [OfferingClosingController::class, 'graceMarks'])
            ->middleware('permission:offering.close')
            ->name('offering-closing.grace');
        Route::post('/offerings/{offering}/closing/announce', [OfferingClosingController::class, 'announce'])
            ->middleware('permission:offering.close')
            ->name('offering-closing.announce');
        Route::post('/offerings/{offering}/closing/close', [OfferingClosingController::class, 'close'])
            ->middleware('permission:offering.close')
            ->name('offering-closing.close');

        Route::get('/certificate-templates', [CertificateTemplateController::class, 'index'])
            ->middleware('permission:certificate_templates.manage')
            ->name('certificate-templates.index');
        Route::post('/certificate-templates', [CertificateTemplateController::class, 'store'])
            ->middleware('permission:certificate_templates.manage')
            ->name('certificate-templates.store');
        Route::get('/certificate-templates/preview', [CertificateTemplateController::class, 'preview'])
            ->middleware('permission:certificate_templates.manage')
            ->name('certificate-templates.preview');

        Route::get('/communications', [CommunicationAdminController::class, 'index'])
            ->middleware('permission:communications.report')
            ->name('communications.report');
        Route::get('/communications/export', [CommunicationAdminController::class, 'export'])
            ->middleware('permission:communications.report')
            ->name('communications.export');
        Route::get('/email-templates', [EmailTemplateAdminController::class, 'index'])
            ->middleware('permission:email_templates.manage')
            ->name('email-templates.index');
        Route::post('/email-templates', [EmailTemplateAdminController::class, 'store'])
            ->middleware('permission:email_templates.manage')
            ->name('email-templates.store');
        Route::post('/email-templates/preview', [EmailTemplateAdminController::class, 'preview'])
            ->middleware('permission:email_templates.manage')
            ->name('email-templates.preview');

        Route::get('/surveys', [AdminSurveyController::class, 'index'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.index');
        Route::post('/surveys', [AdminSurveyController::class, 'store'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.store');
        Route::get('/surveys/{survey}', [AdminSurveyController::class, 'show'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.show');
        Route::post('/surveys/{survey}/questions', [AdminSurveyController::class, 'addQuestion'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.questions.store');
        Route::post('/surveys/{survey}/publish', [AdminSurveyController::class, 'publish'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.publish');
        Route::post('/surveys/{survey}/close', [AdminSurveyController::class, 'close'])
            ->middleware('permission:feedback.manage')
            ->name('surveys.close');
        Route::get('/surveys/{survey}/report', [AdminSurveyController::class, 'report'])
            ->middleware('permission:feedback.report')
            ->name('surveys.report');
        Route::post('/surveys/{survey}/submissions/{submission}/reveal', [AdminSurveyController::class, 'requestReveal'])
            ->middleware('permission:feedback.identity.request')
            ->name('surveys.reveals.store');

        Route::get('/events', [EventAdminController::class, 'index'])
            ->middleware('permission:events.admin')
            ->name('events.index');
        Route::post('/events', [EventAdminController::class, 'store'])
            ->middleware('permission:events.admin')
            ->name('events.store');
        Route::get('/events/{event}', [EventAdminController::class, 'show'])
            ->middleware('permission:events.admin')
            ->name('events.show');
        Route::post('/events/{event}', [EventAdminController::class, 'update'])
            ->middleware('permission:events.admin')
            ->name('events.update');
        Route::post('/events/{event}/publish', [EventAdminController::class, 'publish'])
            ->middleware('permission:events.admin')
            ->name('events.publish');
        Route::post('/events/{event}/cancel', [EventAdminController::class, 'cancel'])
            ->middleware('permission:events.admin')
            ->name('events.cancel');
        Route::post('/events/{event}/exceptions', [EventAdminController::class, 'storeException'])
            ->middleware('permission:events.admin')
            ->name('events.exceptions.store');
        Route::post('/events/{event}/check-in', [EventAdminController::class, 'checkIn'])
            ->middleware('permission:events.check_in')
            ->name('events.check-in');
    });
});
