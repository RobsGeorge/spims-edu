<?php

use App\Http\Controllers\Admin\CredentialAdminController;
use App\Http\Controllers\Admin\AssessmentAdminController;
use App\Http\Controllers\Admin\DiscussionAdminController;
use App\Http\Controllers\Admin\FinanceAdminController;
use App\Http\Controllers\Admin\ApplicationFormController;
use App\Http\Controllers\Admin\ApplicationReviewController;
use App\Http\Controllers\Admin\AssessmentTemplateController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EnrollmentAdminController;
use App\Http\Controllers\Admin\GradebookController;
use App\Http\Controllers\Admin\LiveSessionAdminController;
use App\Http\Controllers\Admin\OfferingController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\ThemeEditorController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ZoomWebhookController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CoursePlayerController;
use App\Http\Controllers\CredentialVerifyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FoundationDemoController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferingPreviewController;
use App\Http\Controllers\RolesHub\RolesHubController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
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
Route::get('/offerings/{offering}/preview', [OfferingPreviewController::class, 'show'])->name('offerings.preview');
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

    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/security', [SuperAdminController::class, 'security'])->name('security');
        Route::post('/sessions/flush', [SuperAdminController::class, 'flushSessions'])->name('sessions.flush');
        Route::get('/audit', [SuperAdminController::class, 'audit'])->name('audit.index');
        Route::get('/observability', [SuperAdminController::class, 'observability'])->name('observability.index');
        Route::get('/scheduled-tasks', [SuperAdminController::class, 'scheduledTasks'])->name('scheduled-tasks.index');
        Route::get('/system-tests', [SuperAdminController::class, 'systemTests'])->name('system-tests.index');
    });

    Route::middleware('superadmin')->prefix('roles-hub')->group(function () {
        Route::get('/', [RolesHubController::class, 'index'])->name('roles.hub');
        Route::put('/roles/{role}', [RolesHubController::class, 'updateRole'])->name('roles.hub.role.update');
    });

    Route::get('/api/me', [MeController::class, 'show'])->name('api.me');
    Route::post('/catalog/{course}/interest', [CatalogController::class, 'flagInterest'])
        ->middleware('permission:courses.flag_interest')
        ->name('catalog.interest');

    Route::get('/courses/{offering}', [CoursePlayerController::class, 'show'])->name('courses.player');
    Route::post('/courses/{offering}/weeks/{week}/complete', [CoursePlayerController::class, 'completeWeek'])
        ->name('courses.weeks.complete');

    Route::get('/grades', [GradesController::class, 'index'])
        ->middleware('permission:transcript.view')
        ->name('grades.index');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:profile.edit_own')
        ->name('settings.update');

    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/forms/{form}', [ApplicationController::class, 'create'])
        ->middleware('permission:admissions.apply')
        ->name('applications.create');
    Route::post('/applications/{application}', [ApplicationController::class, 'store'])
        ->middleware('permission:admissions.apply')
        ->name('applications.store');

    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])
        ->middleware('permission:enrollment.register')
        ->name('enrollments.store');
    Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop'])->name('enrollments.drop');
    Route::post('/enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])->name('enrollments.withdraw');
    Route::get('/degree-audit/{studentProgram}', [EnrollmentController::class, 'audit'])->name('enrollments.audit');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/invoices/{invoice}', [FinanceController::class, 'showInvoice'])->name('finance.invoices.show');
    Route::post('/finance/invoices/{invoice}/checkout', [FinanceController::class, 'checkout'])
        ->middleware('permission:finance.pay')
        ->name('finance.checkout');
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

    Route::post('/foundation/demo', [FoundationDemoController::class, 'mutate'])
        ->middleware('permission:foundation.demo')
        ->name('foundation.demo');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:users.manage')
            ->name('users.suspend');

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
        Route::post('/programs/{program}/courses', [ProgramController::class, 'attachCourse'])
            ->middleware('permission:programs.manage')
            ->name('programs.attach-course');

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
        Route::post('/courses/{course}/prerequisites', [CourseController::class, 'addPrerequisite'])
            ->middleware('permission:courses.manage')
            ->name('courses.prerequisites');

        Route::get('/assessment-templates', [AssessmentTemplateController::class, 'index'])
            ->middleware('permission:assessment_templates.manage')
            ->name('assessment-templates.index');
        Route::post('/assessment-templates', [AssessmentTemplateController::class, 'store'])
            ->middleware('permission:assessment_templates.manage')
            ->name('assessment-templates.store');

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
        Route::post('/offerings/{offering}/staff', [OfferingController::class, 'assignStaff'])
            ->middleware('permission:offerings.manage')
            ->name('offerings.staff');
        Route::post('/offerings/{offering}/pricing', [OfferingController::class, 'setPricing'])
            ->middleware('permission:offerings.pricing')
            ->name('offerings.pricing');
        Route::post('/offerings/{offering}/weeks', [OfferingController::class, 'addWeek'])
            ->middleware('permission:offerings.content')
            ->name('offerings.weeks');
        Route::post('/weeks/{week}/items', [OfferingController::class, 'addContent'])
            ->middleware('permission:offerings.content')
            ->name('weeks.items');

        Route::get('/application-forms', [ApplicationFormController::class, 'index'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.index');
        Route::post('/application-forms', [ApplicationFormController::class, 'store'])
            ->middleware('permission:admissions.forms')
            ->name('application-forms.store');

        Route::get('/applications', [ApplicationReviewController::class, 'index'])
            ->middleware('permission:admissions.review')
            ->name('applications.index');
        Route::get('/applications/{application}', [ApplicationReviewController::class, 'show'])
            ->middleware('permission:admissions.review')
            ->name('applications.show');
        Route::post('/applications/{application}/decide', [ApplicationReviewController::class, 'decide'])
            ->middleware('permission:admissions.decide')
            ->name('applications.decide');

        Route::post('/enrollments/override', [EnrollmentAdminController::class, 'overrideRegister'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.override');
        Route::post('/users/{user}/financial-hold', [EnrollmentAdminController::class, 'financialHold'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.financial-hold');
        Route::get('/offerings/{offering}/waitlist', [EnrollmentAdminController::class, 'waitlist'])
            ->middleware('permission:enrollment.override')
            ->name('enrollments.waitlist');

        Route::get('/finance', [FinanceAdminController::class, 'index'])
            ->middleware('permission:finance.invoices')
            ->name('finance.index');
        Route::post('/finance/invoices', [FinanceAdminController::class, 'storeInvoice'])
            ->middleware('permission:finance.invoices')
            ->name('finance.invoices.store');
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
        Route::post('/answers/{answer}/grade', [AssessmentAdminController::class, 'overrideScore'])
            ->middleware('permission:assessments.grade')
            ->name('answers.grade');

        Route::get('/offerings/{offering}/gradebook', [GradebookController::class, 'show'])
            ->middleware('permission:gradebook.configure')
            ->name('gradebook.show');
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
        Route::post('/live/{session}/attendance/import', [LiveSessionAdminController::class, 'importAttendance'])
            ->middleware('permission:attendance.manage')
            ->name('live.attendance.import');
        Route::post('/live/{session}/attendance/override', [LiveSessionAdminController::class, 'overrideAttendance'])
            ->middleware('permission:attendance.manage')
            ->name('live.attendance.override');

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
    });
});
