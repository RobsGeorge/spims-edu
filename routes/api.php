<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CompletionController;
use App\Http\Controllers\Api\V1\ContentItemController;
use App\Http\Controllers\Api\V1\CredentialController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DegreeAuditController;
use App\Http\Controllers\Api\V1\DiscussionController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\EventCheckInController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedbackSurveyController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LiveQuizController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationSettingsController;
use App\Http\Controllers\Api\V1\OfferingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeachAnnouncementController;
use App\Http\Controllers\Api\V1\TeachAssessmentGradingController;
use App\Http\Controllers\Api\V1\TeachAssignmentController;
use App\Http\Controllers\Api\V1\TeachAttendanceController;
use App\Http\Controllers\Api\V1\TeachCompletionController;
use App\Http\Controllers\Api\V1\TeachContentController;
use App\Http\Controllers\Api\V1\TeachDiscussionController;
use App\Http\Controllers\Api\V1\TeachGradebookController;
use App\Http\Controllers\Api\V1\TeachLiveQuizController;
use App\Http\Controllers\Api\V1\TeachLiveSessionController;
use App\Http\Controllers\Api\V1\TeachOfferingController;
use App\Http\Controllers\Api\V1\TeachProjectController;
use App\Http\Controllers\Api\V1\TranscriptController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Middleware\Api\SetApiLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| /api/v1 — mobile clients (student, instructor)
|--------------------------------------------------------------------------
|
| Bearer-token auth via Sanctum. Controllers are thin: validate, delegate to
| the same App\Services\* used by the web app, return a Resource or a plain
| `data`-wrapped array. See docs/api/openapi.yaml for the contract and
| docs/academic-roadmap/mobile-api-spec.md for the full design.
|
*/
Route::prefix('v1')->name('api.v1.')->middleware(SetApiLocale::class)->group(function () {
    Route::get('/branding', [BrandingController::class, 'show'])->name('branding');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    // Public catalog (matches web /catalog).
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/courses/{course}', [CatalogController::class, 'showCourse'])->name('catalog.courses.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [MeController::class, 'show'])->name('me');

        // --- S6 Wave A (owned by cursor/s6-student-api-bcff) ---
        Route::put('/me/preferences', [MeController::class, 'updatePreferences'])->name('me.preferences');
        Route::post('/me/picture', [MeController::class, 'storePicture'])->name('me.picture');
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
        Route::post('/announcements/{announcement}/dismiss-banner', [AnnouncementController::class, 'dismissBanner'])
            ->name('announcements.dismiss-banner');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::get('/notification-settings', [NotificationSettingsController::class, 'show'])->name('notification-settings.show');
        Route::put('/notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');

        Route::post('/teach/offerings/{offering}/announcements', [TeachAnnouncementController::class, 'store'])
            ->name('teach.announcements.store');
        Route::put('/teach/announcements/{announcement}', [TeachAnnouncementController::class, 'update'])
            ->name('teach.announcements.update');
        Route::post('/teach/announcements/{announcement}/publish', [TeachAnnouncementController::class, 'publish'])
            ->name('teach.announcements.publish');

        Route::get('/attendance/mine', [AttendanceController::class, 'mine'])->name('attendance.mine');
        Route::get('/offerings/{offering}/attendance/mine', [AttendanceController::class, 'offeringMine'])->name('offerings.attendance.mine');
        Route::post('/sessions/{session}/check-in', [AttendanceController::class, 'checkIn'])->name('sessions.check-in');

        // --- S4 completion / credentials API (owned by cursor/s4-completion-api-bcff) ---
        Route::get('/me/credentials', [CredentialController::class, 'index'])->name('me.credentials');
        Route::get('/credentials/{credential}/download', [CredentialController::class, 'download'])->name('credentials.download');
        Route::get('/offerings/{offering}/completion', [CompletionController::class, 'show'])->name('offerings.completion');
        Route::post('/offerings/{offering}/completion/evaluate', [CompletionController::class, 'evaluate'])->name('offerings.completion.evaluate');

        // --- S6 Wave B ---
        Route::get('/offerings', [OfferingController::class, 'index'])->name('offerings.index');
        Route::get('/offerings/{offering}', [OfferingController::class, 'show'])->name('offerings.show');
        Route::get('/offerings/{offering}/weeks', [OfferingController::class, 'weeks'])->name('offerings.weeks');
        Route::get('/offerings/{offering}/weeks/{week}/items', [OfferingController::class, 'weekItems'])->name('offerings.weeks.items');
        Route::post('/offerings/{offering}/weeks/{week}/complete', [OfferingController::class, 'completeWeek'])->name('offerings.weeks.complete');
        Route::get('/offerings/{offering}/grades', [OfferingController::class, 'grades'])->name('offerings.grades');
        Route::get('/items/{item}', [ContentItemController::class, 'show'])->name('items.show');
        Route::post('/items/{item}/complete', [ContentItemController::class, 'complete'])->name('items.complete');
        Route::get('/transcript', [TranscriptController::class, 'show'])->name('transcript');
        Route::get('/degree-audit/{studentProgram}', [DegreeAuditController::class, 'show'])->name('degree-audit.show');

        // --- S6 Wave C ---
        Route::get('/offerings/{offering}/assignments', [AssignmentController::class, 'index'])->name('offerings.assignments');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('assignments.submit');
        Route::post('/assignments/{assignment}/resubmit', [AssignmentController::class, 'resubmit'])->name('assignments.resubmit');
        Route::get('/offerings/{offering}/assessments', [AssessmentController::class, 'index'])->name('offerings.assessments');
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::post('/assessments/{assessment}/start', [AssessmentController::class, 'start'])->name('assessments.start');
        Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
        Route::post('/attempts/{attempt}/save', [AttemptController::class, 'save'])->name('attempts.save');
        Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
        Route::get('/attempts/{attempt}/timer', [AttemptController::class, 'timer'])->name('attempts.timer');
        Route::post('/attempts/{attempt}/focus-loss', [AttemptController::class, 'focusLoss'])->name('attempts.focus-loss');
        Route::get('/offerings/{offering}/discussions', [DiscussionController::class, 'index'])->name('offerings.discussions');
        Route::get('/discussions/threads/{thread}', [DiscussionController::class, 'showThread'])->name('discussions.threads.show');
        Route::post('/discussions/threads/{thread}/posts', [DiscussionController::class, 'storePost'])->name('discussions.threads.posts');
        Route::post('/offerings/{offering}/discussions/threads', [DiscussionController::class, 'storeThread'])->name('offerings.discussions.threads.store');

        // --- S6 Wave D ---
        Route::post('/catalog/courses/{course}/interest', [CatalogController::class, 'flagInterest'])->name('catalog.courses.interest');
        Route::get('/application-forms/{applicationForm}', [ApplicationController::class, 'form'])->name('application-forms.show');
        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop'])->name('enrollments.drop');
        Route::post('/enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])->name('enrollments.withdraw');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}/checkout', [InvoiceController::class, 'checkout'])->name('invoices.checkout');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::get('/wallet', [WalletController::class, 'show'])->name('wallet');
        Route::post('/donations', [DonationController::class, 'store'])->name('donations.store');

        // --- S6E surveys (owned by cursor/s6e-surveys-bcff) ---
        Route::get('/feedback/surveys', [FeedbackSurveyController::class, 'index'])->name('feedback.surveys.index');
        Route::get('/feedback/surveys/{feedbackSurvey}', [FeedbackSurveyController::class, 'show'])->name('feedback.surveys.show');
        Route::post('/feedback/surveys/{feedbackSurvey}/submit', [FeedbackSurveyController::class, 'submit'])->name('feedback.surveys.submit');

        // --- S6E events (owned by cursor/s6e-events-bcff) ---
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/mine', [EventController::class, 'mine'])->name('events.mine');
        Route::post('/events/check-in/verify', [EventCheckInController::class, 'verify'])->name('events.check-in.verify');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::post('/events/{event}/reserve', [EventController::class, 'reserve'])->name('events.reserve');
        Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');

        // --- S6E live quiz (owned by cursor/s6e-livequiz-bcff) ---
        Route::post('/live-quiz/join', [LiveQuizController::class, 'join'])->name('live-quiz.join');
        Route::get('/live-quiz/sessions/{liveQuizSession}', [LiveQuizController::class, 'show'])
            ->name('live-quiz.sessions.show');
        Route::post('/live-quiz/sessions/{liveQuizSession}/questions/{liveQuizQuestion}/answer', [LiveQuizController::class, 'answer'])
            ->name('live-quiz.sessions.answer');

        // --- S6E projects (owned by cursor/s6e-projects-bcff) ---
        Route::get('/offerings/{offering}/project-assessments', [ProjectController::class, 'index'])
            ->name('offerings.project-assessments');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('/project-assessments/{projectAssessment}/join', [ProjectController::class, 'join'])
            ->name('project-assessments.join');
        Route::post('/project-assessments/{projectAssessment}/leave', [ProjectController::class, 'leave'])
            ->name('project-assessments.leave');
        Route::post('/projects/{project}/deliverables/{projectDeliverable}/submit', [ProjectController::class, 'submit'])
            ->name('projects.deliverables.submit');
        Route::delete('/projects/{project}/submission-files/{projectSubmissionFile}', [ProjectController::class, 'destroyFile'])
            ->name('projects.submission-files.destroy');
        Route::get('/projects/{project}/peer-evaluations/pending', [ProjectController::class, 'pendingPeerEvaluations'])
            ->name('projects.peer-evaluations.pending');
        Route::post('/projects/{project}/peer-evaluations', [ProjectController::class, 'storePeerEvaluation'])
            ->name('projects.peer-evaluations.store');

        Route::prefix('teach')->name('teach.')->middleware('api.instructor')->group(function () {
            // --- S8 core (owned by cursor/s8-core-bcff) ---
            Route::get('/offerings', [TeachOfferingController::class, 'index'])->name('offerings.index');
            Route::get('/offerings/{offering}', [TeachOfferingController::class, 'show'])->name('offerings.show');
            Route::get('/offerings/{offering}/students/{student}', [TeachOfferingController::class, 'student'])
                ->name('offerings.students.show');
            Route::post('/offerings/{offering}/close', [TeachOfferingController::class, 'close'])->name('offerings.close');

            Route::get('/offerings/{offering}/sessions', [TeachAttendanceController::class, 'sessions'])->name('offerings.sessions');
            Route::post('/offerings/{offering}/sessions', [TeachAttendanceController::class, 'storeSession'])->name('offerings.sessions.store');
            Route::get('/sessions/{session}/roster', [TeachAttendanceController::class, 'roster'])->name('sessions.roster');
            Route::post('/sessions/{session}/attendance', [TeachAttendanceController::class, 'mark'])->name('sessions.attendance');
            Route::post('/sessions/{session}/attendance/fill-missing', [TeachAttendanceController::class, 'fillMissing'])->name('sessions.fill-missing');
            Route::post('/sessions/{session}/close', [TeachAttendanceController::class, 'close'])->name('sessions.close');
            Route::post('/sessions/{session}/check-in-code', [TeachAttendanceController::class, 'issueCheckInCode'])->name('sessions.check-in-code');
            Route::get('/offerings/{offering}/attendance/report', [TeachAttendanceController::class, 'report'])->name('offerings.attendance.report');
            Route::get('/offerings/{offering}/roster', [TeachAttendanceController::class, 'offeringRoster'])->name('offerings.roster');
            Route::get('/offerings/{offering}/birthdays', [TeachAttendanceController::class, 'birthdays'])->name('offerings.birthdays');

            // --- S4 completion / credentials API (owned by cursor/s4-completion-api-bcff) ---
            Route::get('/offerings/{offering}/students/{student}/notes', [TeachCompletionController::class, 'notes'])
                ->name('offerings.students.notes');
            Route::post('/offerings/{offering}/students/{student}/notes', [TeachCompletionController::class, 'storeNote'])
                ->name('offerings.students.notes.store');
            Route::put('/offerings/{offering}/weeks/{week}/students/{student}/assessment', [TeachCompletionController::class, 'rate'])
                ->name('offerings.weeks.students.assessment');

            // --- S6E live quiz host (owned by cursor/s6e-livequiz-bcff) ---
            Route::post('/offerings/{offering}/live-quizzes', [TeachLiveQuizController::class, 'store'])
                ->name('offerings.live-quizzes.store');
            Route::post('/live-quiz/{liveQuiz}/host/start', [TeachLiveQuizController::class, 'start'])
                ->name('live-quiz.host.start');
            Route::post('/live-quiz/sessions/{liveQuizSession}/launch', [TeachLiveQuizController::class, 'launch'])
                ->name('live-quiz.sessions.launch');
            Route::post('/live-quiz/sessions/{liveQuizSession}/close', [TeachLiveQuizController::class, 'close'])
                ->name('live-quiz.sessions.close');
            Route::post('/live-quiz/sessions/{liveQuizSession}/results', [TeachLiveQuizController::class, 'results'])
                ->name('live-quiz.sessions.results');
            Route::post('/live-quiz/sessions/{liveQuizSession}/end', [TeachLiveQuizController::class, 'end'])
                ->name('live-quiz.sessions.end');

            // --- S6E projects staff (owned by cursor/s6e-projects-bcff) ---
            Route::get('/offerings/{offering}/project-assessments', [TeachProjectController::class, 'index'])
                ->name('offerings.project-assessments');
            Route::get('/project-assessments/{projectAssessment}/teams', [TeachProjectController::class, 'teams'])
                ->name('project-assessments.teams');
            Route::post('/project-assessments/{projectAssessment}/announce', [TeachProjectController::class, 'announce'])
                ->name('project-assessments.announce');
            Route::get('/projects/{project}/peer-evaluations', [TeachProjectController::class, 'peerAggregates'])
                ->name('projects.peer-evaluations');

            // --- S8 grading (owned by cursor/s8-grading-bcff) ---
            Route::get('/offerings/{offering}/gradebook', [TeachGradebookController::class, 'show'])
                ->name('offerings.gradebook');
            Route::post('/offerings/{offering}/gradebook/submit', [TeachGradebookController::class, 'submit'])
                ->name('offerings.gradebook.submit');
            Route::post('/offerings/{offering}/gradebook/lock', [TeachGradebookController::class, 'lock'])
                ->name('offerings.gradebook.lock');
            Route::get('/offerings/{offering}/assignments', [TeachAssignmentController::class, 'index'])
                ->name('offerings.assignments');
            Route::get('/assignments/{assignment}/submissions', [TeachAssignmentController::class, 'submissions'])
                ->name('assignments.submissions');
            Route::post('/submissions/{assignmentSubmission}/grade', [TeachAssignmentController::class, 'grade'])
                ->name('submissions.grade');
            Route::post('/submissions/{assignmentSubmission}/mark-received', [TeachAssignmentController::class, 'markReceived'])
                ->name('submissions.mark-received');
            Route::post('/assignments/{assignment}/remind-unsubmitted', [TeachAssignmentController::class, 'remindUnsubmitted'])
                ->name('assignments.remind-unsubmitted');
            Route::get('/assessments/{assessment}/attempts', [TeachAssessmentGradingController::class, 'attempts'])
                ->name('assessments.attempts');
            Route::post('/answers/{attemptAnswer}/grade', [TeachAssessmentGradingController::class, 'gradeAnswer'])
                ->name('answers.grade');
            Route::post('/assessments/{assessment}/announce-results', [TeachAssessmentGradingController::class, 'announceResults'])
                ->name('assessments.announce-results');

            // --- S8 ops (owned by cursor/s8-ops-bcff) ---
            Route::get('/offerings/{offering}/live-sessions', [TeachLiveSessionController::class, 'index'])
                ->name('offerings.live-sessions');
            Route::post('/live-sessions/{liveSession}/attendance/import', [TeachLiveSessionController::class, 'importAttendance'])
                ->name('live-sessions.attendance.import');

            Route::post('/projects/{project}/members/move', [TeachProjectController::class, 'moveMember'])
                ->name('projects.members.move');
            Route::post('/project-submissions/{projectDeliverableSubmission}/review', [TeachProjectController::class, 'reviewSubmission'])
                ->name('project-submissions.review');
            Route::post('/projects/{project}/grade', [TeachProjectController::class, 'grade'])
                ->name('projects.grade');

            Route::get('/offerings/{offering}/discussions/threads', [TeachDiscussionController::class, 'threads'])
                ->name('offerings.discussions.threads');
            Route::post('/discussions/threads/{discussionThread}/moderate', [TeachDiscussionController::class, 'moderate'])
                ->name('discussions.threads.moderate');
            Route::post('/discussions/threads/{discussionThread}/grade', [TeachDiscussionController::class, 'grade'])
                ->name('discussions.threads.grade');

            Route::post('/offerings/{offering}/weeks', [TeachContentController::class, 'storeWeek'])
                ->name('offerings.weeks.store');
            Route::post('/weeks/{week}/items', [TeachContentController::class, 'storeItem'])
                ->name('weeks.items.store');
            Route::put('/items/{contentItem}', [TeachContentController::class, 'updateItem'])
                ->name('items.update');
            Route::delete('/items/{contentItem}', [TeachContentController::class, 'destroyItem'])
                ->name('items.destroy');
        });
    });
});
