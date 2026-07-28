<?php

use App\Http\Controllers\Admin\ApplicationFormController;
use App\Http\Controllers\Admin\ApplicationReviewController;
use App\Http\Controllers\Admin\AssessmentTemplateController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EnrollmentAdminController;
use App\Http\Controllers\Admin\OfferingController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\ThemeEditorController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FoundationDemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\OfferingPreviewController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/branding', [BrandingController::class, 'show'])->name('api.branding');
Route::get('/offerings/{offering}/preview', [OfferingPreviewController::class, 'show'])->name('offerings.preview');
Route::get('/api/offerings/{offering}/preview', [OfferingPreviewController::class, 'json'])->name('api.offerings.preview');
Route::get('/api/offerings/{offering}/pricing', [OfferingPreviewController::class, 'pricing'])->name('api.offerings.pricing');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/verify-email', [VerifyEmailController::class, 'show'])->name('auth.verify');
    Route::post('/verify-email', [VerifyEmailController::class, 'store']);
    Route::get('/set-password', [SetPasswordController::class, 'create'])->name('auth.password.create');
    Route::post('/set-password', [SetPasswordController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('auth.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp']);
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('auth.password.reset.form');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('auth.password.reset');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('auth.logout');
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/me', [MeController::class, 'show'])->name('api.me');
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/{course}/interest', [CatalogController::class, 'flagInterest'])
        ->middleware('permission:courses.flag_interest')
        ->name('catalog.interest');

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
    });
});
