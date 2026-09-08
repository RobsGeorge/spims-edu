<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentMode;
use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\FormFieldType;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\LedgerReason;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProgramType;
use App\Enums\QuestionType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Enums\WalletKind;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Assessment;
use App\Models\ClassSession;
use App\Models\ContentItem;
use App\Models\Course;
use App\Support\Ui\CourseCoverLibrary;
use App\Models\CourseOffering;
use App\Models\DiscussionPost;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\GradingScheme;
use App\Models\Invoice;
use App\Models\LiveSession;
use App\Models\OfferingStaff;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\QuestionBank;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Week;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Communications\AnnouncementService;
use App\Services\Discussions\DiscussionService;
use App\Services\Enrollment\EnrollmentService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\WalletService;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Services\Live\LiveSessionService;
use App\Services\Offerings\OfferingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Spims@Test2026!';

    public function run(): void
    {
        if (! config('spims.seed_demo_data', true)) {
            return;
        }

        $hash = Hash::make(self::PASSWORD);
        $scheme = GradingScheme::query()->where('is_default', true)->first();

        $users = $this->seedUsers($hash);
        $programs = $this->seedPrograms($scheme);
        $courses = $this->seedCourses();
        $this->attachCurriculum($programs, $courses);
        [$year, $fall, $spring] = $this->seedCalendar();
        $offerings = $this->seedOfferings($courses, $fall, $spring, $users);
        $forms = $this->seedApplicationForms($programs);
        $this->seedApplicationsAndEnrollments($users, $programs, $forms, $offerings);
        $this->seedClassroomAndMoney($users, $offerings);

        $this->command?->info('Demo accounts password: '.self::PASSWORD);
        $this->command?->info('See docs/demo-accounts.md for the full account list.');
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(string $hash): array
    {
        $defs = [
            'adm@spims.test' => ['Admin', 'Office', RoleType::AdministrativeAdmin, 'en'],
            'aca@spims.test' => ['Academic', 'Dean', RoleType::AcademicAdmin, 'en'],
            'fin@spims.test' => ['Finance', 'Bursar', RoleType::FinancialAdmin, 'en'],
            'ins1@spims.test' => ['Mina', 'Instructor', RoleType::Instructor, 'en'],
            'ins2@spims.test' => ['Mariana', 'Teacher', RoleType::Instructor, 'ar'],
            'ta1@spims.test' => ['Yousef', 'Assistant', RoleType::Ta, 'en'],
            'student1@spims.test' => ['John', 'Student', RoleType::Student, 'en'],
            'student2@spims.test' => ['Sara', 'Habib', RoleType::Student, 'ar'],
            'student3@spims.test' => ['Mark', 'Shenouda', RoleType::Student, 'en'],
            'student4@spims.test' => ['Mary', 'Guirguis', RoleType::Student, 'fr'],
            'student5@spims.test' => ['David', 'Bishoy', RoleType::Student, 'en'],
            'student6@spims.test' => ['Hannah', 'Rizk', RoleType::Student, 'ar'],
            'student7@spims.test' => ['Peter', 'Atallah', RoleType::Student, 'en'],
            'student8@spims.test' => ['Rebecca', 'Fawzy', RoleType::Student, 'en'],
            'student9@spims.test' => ['Andrew', 'Naguib', RoleType::Student, 'en'],
            'student10@spims.test' => ['Christine', 'Wahba', RoleType::Student, 'ar'],
        ];

        $users = [];
        foreach ($defs as $email => [$first, $last, $role, $locale]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'password_hash' => $hash,
                    'email_verified' => true,
                    'preferred_locale' => $locale,
                    'status' => UserStatus::Active,
                    'country_code' => $locale === 'ar' ? 'EG' : 'US',
                ]
            );
            UserRole::query()->updateOrCreate(
                ['user_id' => $user->id, 'role' => $role],
                ['role' => $role]
            );
            $users[$email] = $user->fresh(['roles']);
        }

        // Dual-role demo: instructor who is also a student in another program context
        $dual = User::query()->updateOrCreate(
            ['email' => 'dual@spims.test'],
            [
                'first_name' => 'Dual',
                'last_name' => 'Role',
                'password_hash' => $hash,
                'email_verified' => true,
                'preferred_locale' => 'en',
                'status' => UserStatus::Active,
            ]
        );
        foreach ([RoleType::Instructor, RoleType::Student] as $role) {
            UserRole::query()->updateOrCreate(
                ['user_id' => $dual->id, 'role' => $role],
                ['role' => $role]
            );
        }
        $users['dual@spims.test'] = $dual->fresh(['roles']);

        return $users;
    }

    /**
     * @return array<string, Program>
     */
    private function seedPrograms(?GradingScheme $scheme): array
    {
        $defs = [
            'DIP-THEO' => ['Diploma in Theology', ProgramType::Diploma, 60, 15, 5, 8, 3],
            'CERT-LIT' => ['Certificate in Liturgics', ProgramType::Certificate, 70, 12, 4, 4, 0],
            'DEG-BTH' => ['Bachelor of Theology', ProgramType::Degree, 60, 18, 6, 12, 6],
            'CERT-BIB' => ['Certificate in Biblical Studies', ProgramType::Certificate, 65, 9, 3, 3, 0],
        ];

        $programs = [];
        foreach ($defs as $code => [$name, $type, $pass, $maxCredits, $maxCourses, $maxSem, $electives]) {
            $programs[$code] = Program::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'passing_threshold' => $pass,
                    'max_credits_per_semester' => $maxCredits,
                    'max_courses_per_semester' => $maxCourses,
                    'max_semesters_to_graduate' => $maxSem,
                    'elective_credits_required' => $electives,
                    'signatory_name' => 'Dean of Studies',
                    'signatory_title' => 'Academic Dean',
                    'grading_scheme_id' => $scheme?->id,
                    'active' => true,
                ]
            );
        }

        return $programs;
    }

    /**
     * @return array<string, Course>
     */
    private function seedCourses(): array
    {
        $defs = [
            ['TH101', 'Introduction to Theology', 3, 15000, 75000, false],
            ['TH201', 'Patristics I', 3, 18000, 90000, false],
            ['TH301', 'Systematic Theology', 4, 20000, 100000, false],
            ['BI101', 'Old Testament Survey', 3, 15000, 75000, false],
            ['BI102', 'New Testament Survey', 3, 15000, 75000, false],
            ['BI201', 'Pauline Epistles', 3, 17000, 85000, false],
            ['LI101', 'Coptic Liturgy Basics', 2, 10000, 50000, false],
            ['LI201', 'Divine Liturgy Practicum', 2, 12000, 60000, false],
            ['CH101', 'Church History I', 3, 14000, 70000, false],
            ['CH201', 'Church History II', 3, 14000, 70000, false],
            ['ET101', 'Christian Ethics', 2, 11000, 55000, true],
            ['FREE1', 'Open Orientation (Free)', 1, 0, 0, true],
        ];

        $courses = [];
        foreach ($defs as [$code, $title, $credits, $usd, $egp, $free]) {
            $attrs = [
                'title' => $title,
                'credit_hours' => $credits,
                'default_price_usd' => $usd,
                'default_price_egp' => $egp,
                'is_free' => $free || $usd === 0,
                'is_standalone' => in_array($code, ['ET101', 'FREE1'], true),
                'passing_threshold' => 60,
                'active' => true,
            ];
            $existing = Course::query()->where('code', $code)->first();
            if ($existing === null || blank($existing->cover_image_url)) {
                $attrs['cover_image_url'] = CourseCoverLibrary::urlForSeed($code);
            }
            $courses[$code] = Course::query()->updateOrCreate(
                ['code' => $code],
                $attrs
            );
        }

        return $courses;
    }

    /**
     * @param  array<string, Program>  $programs
     * @param  array<string, Course>  $courses
     */
    private function attachCurriculum(array $programs, array $courses): void
    {
        $map = [
            'DIP-THEO' => [
                ['TH101', RequirementType::Required, 1],
                ['BI101', RequirementType::Required, 1],
                ['BI102', RequirementType::Required, 1],
                ['CH101', RequirementType::Required, 2],
                ['TH201', RequirementType::Required, 2],
                ['ET101', RequirementType::Elective, 2],
            ],
            'CERT-LIT' => [
                ['LI101', RequirementType::Required, 1],
                ['LI201', RequirementType::Required, 1],
                ['TH101', RequirementType::Required, 1],
            ],
            'DEG-BTH' => [
                ['TH101', RequirementType::Required, 1],
                ['TH201', RequirementType::Required, 2],
                ['TH301', RequirementType::Required, 3],
                ['BI101', RequirementType::Required, 1],
                ['BI102', RequirementType::Required, 1],
                ['BI201', RequirementType::Required, 2],
                ['CH101', RequirementType::Required, 1],
                ['CH201', RequirementType::Required, 2],
                ['ET101', RequirementType::Elective, 2],
                ['FREE1', RequirementType::Elective, 1],
            ],
            'CERT-BIB' => [
                ['BI101', RequirementType::Required, 1],
                ['BI102', RequirementType::Required, 1],
                ['BI201', RequirementType::Required, 1],
            ],
        ];

        foreach ($map as $programCode => $rows) {
            foreach ($rows as [$courseCode, $req, $year]) {
                ProgramCourse::query()->updateOrCreate(
                    [
                        'program_id' => $programs[$programCode]->id,
                        'course_id' => $courses[$courseCode]->id,
                    ],
                    [
                        'requirement' => $req,
                        'year_level' => $year,
                    ]
                );
            }
        }
    }

    /**
     * @return array{0: AcademicYear, 1: Semester, 2: Semester}
     */
    private function seedCalendar(): array
    {
        $year = AcademicYear::query()->updateOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-09-01', 'end_date' => '2027-06-30']
        );

        $fall = Semester::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Fall'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-20',
                'registration_start' => now()->subMonths(2),
                'registration_end' => now()->addMonths(3),
                'add_drop_end_week' => 2,
                'last_withdrawal_week' => 8,
                'withdrawal_refund_percent' => 50,
                'status' => OfferingStatus::Open,
            ]
        );

        $spring = Semester::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Spring'],
            [
                'start_date' => '2027-01-15',
                'end_date' => '2027-05-30',
                'registration_start' => '2026-11-01',
                'registration_end' => '2027-01-10',
                'add_drop_end_week' => 2,
                'last_withdrawal_week' => 8,
                'withdrawal_refund_percent' => 40,
                'status' => OfferingStatus::Draft,
            ]
        );

        $prior = AcademicYear::query()->updateOrCreate(
            ['name' => '2025/2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-06-30']
        );
        Semester::query()->updateOrCreate(
            ['academic_year_id' => $prior->id, 'name' => 'Fall'],
            [
                'start_date' => '2025-09-01',
                'end_date' => '2025-12-20',
                'registration_start' => '2025-07-01',
                'registration_end' => '2025-08-31',
                'add_drop_end_week' => 2,
                'last_withdrawal_week' => 8,
                'withdrawal_refund_percent' => 50,
                'status' => OfferingStatus::Completed,
            ]
        );

        return [$year, $fall, $spring];
    }

    /**
     * @param  array<string, Course>  $courses
     * @param  array<string, User>  $users
     * @return list<CourseOffering>
     */
    private function seedOfferings(array $courses, Semester $fall, Semester $spring, array $users): array
    {
        $offerings = [];
        $fallCodes = ['TH101', 'BI101', 'BI102', 'LI101', 'CH101', 'ET101', 'FREE1', 'TH201'];
        $springCodes = ['TH301', 'BI201', 'LI201', 'CH201'];

        foreach ($fallCodes as $i => $code) {
            $offering = CourseOffering::query()->updateOrCreate(
                [
                    'course_id' => $courses[$code]->id,
                    'semester_id' => $fall->id,
                    'mode' => OfferingMode::Cohort,
                ],
                [
                    'seat_capacity' => 20 + ($i * 5),
                    'attendance_threshold_percent' => 75,
                    'status' => OfferingStatus::Open,
                    'start_date' => $fall->start_date,
                    'end_date' => $fall->end_date,
                ]
            );
            Week::query()->updateOrCreate(
                ['offering_id' => $offering->id, 'number' => 1],
                ['title' => 'Week 1', 'unlock_date' => $offering->start_date, 'order' => 1]
            );
            Week::query()->updateOrCreate(
                ['offering_id' => $offering->id, 'number' => 2],
                ['title' => 'Week 2', 'unlock_date' => $offering->start_date?->copy()->addWeek(), 'order' => 2]
            );
            $ins = $i % 2 === 0 ? $users['ins1@spims.test'] : $users['ins2@spims.test'];
            OfferingStaff::query()->updateOrCreate(
                ['offering_id' => $offering->id, 'user_id' => $ins->id, 'role' => OfferingStaffRole::Instructor],
                ['role' => OfferingStaffRole::Instructor]
            );
            if ($i < 3) {
                OfferingStaff::query()->updateOrCreate(
                    ['offering_id' => $offering->id, 'user_id' => $users['ta1@spims.test']->id, 'role' => OfferingStaffRole::Ta],
                    ['role' => OfferingStaffRole::Ta]
                );
            }
            $offerings[] = $offering;
        }

        // Self-paced standalone
        $selfPaced = CourseOffering::query()->updateOrCreate(
            [
                'course_id' => $courses['ET101']->id,
                'semester_id' => null,
                'mode' => OfferingMode::SelfPaced,
            ],
            [
                'seat_capacity' => 100,
                'attendance_threshold_percent' => 0,
                'status' => OfferingStatus::Open,
                'start_date' => now()->subMonth(),
                'end_date' => now()->addYear(),
            ]
        );
        Week::query()->updateOrCreate(
            ['offering_id' => $selfPaced->id, 'number' => 1],
            ['title' => 'Module 1', 'unlock_date' => now()->subMonth(), 'order' => 1]
        );
        $offerings[] = $selfPaced;

        foreach ($springCodes as $code) {
            $offerings[] = CourseOffering::query()->updateOrCreate(
                [
                    'course_id' => $courses[$code]->id,
                    'semester_id' => $spring->id,
                    'mode' => OfferingMode::Cohort,
                ],
                [
                    'seat_capacity' => 25,
                    'attendance_threshold_percent' => 75,
                    'status' => OfferingStatus::Draft,
                    'start_date' => $spring->start_date,
                    'end_date' => $spring->end_date,
                ]
            );
        }

        return $offerings;
    }

    /**
     * @param  array<string, Program>  $programs
     * @return array<string, ApplicationForm>
     */
    private function seedApplicationForms(array $programs): array
    {
        $forms = [];
        foreach ($programs as $code => $program) {
            $form = ApplicationForm::query()->updateOrCreate(
                ['program_id' => $program->id, 'name' => $program->name.' Application'],
                ['active' => true]
            );
            ApplicationFormField::query()->updateOrCreate(
                ['form_id' => $form->id, 'order' => 1],
                [
                    'label' => 'Why do you want to join?',
                    'type' => FormFieldType::Textarea,
                    'required' => true,
                    'options' => null,
                ]
            );
            ApplicationFormField::query()->updateOrCreate(
                ['form_id' => $form->id, 'order' => 2],
                [
                    'label' => 'Parish name',
                    'type' => FormFieldType::Text,
                    'required' => true,
                    'options' => null,
                ]
            );
            $forms[$code] = $form;
        }

        return $forms;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Program>  $programs
     * @param  array<string, ApplicationForm>  $forms
     * @param  list<CourseOffering>  $offerings
     */
    private function seedApplicationsAndEnrollments(array $users, array $programs, array $forms, array $offerings): void
    {
        $adm = $users['adm@spims.test'];
        $students = [];
        foreach ($users as $email => $user) {
            if (str_starts_with($email, 'student')) {
                $students[] = $user;
            }
        }

        $statuses = [
            ApplicationStatus::Accepted,
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::Waitlisted,
            ApplicationStatus::Rejected,
            ApplicationStatus::Accepted,
            ApplicationStatus::Accepted,
            ApplicationStatus::Draft,
            ApplicationStatus::Accepted,
            ApplicationStatus::Submitted,
        ];

        foreach ($students as $i => $student) {
            $program = $i < 5 ? $programs['DIP-THEO'] : ($i < 8 ? $programs['CERT-LIT'] : $programs['DEG-BTH']);
            $form = $forms[$program->code];
            $status = $statuses[$i] ?? ApplicationStatus::Submitted;

            $app = Application::query()->updateOrCreate(
                ['applicant_id' => $student->id, 'program_id' => $program->id],
                [
                    'form_id' => $form->id,
                    'status' => $status,
                    'reviewer_id' => in_array($status, [ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Waitlisted], true) ? $adm->id : null,
                    'decision_note' => $status === ApplicationStatus::Accepted ? 'Welcome!' : null,
                    'submitted_at' => $status === ApplicationStatus::Draft ? null : now()->subDays(10 - $i),
                    'decided_at' => in_array($status, [ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Waitlisted], true)
                        ? now()->subDays(3)
                        : null,
                ]
            );

            if ($status === ApplicationStatus::Accepted) {
                $sp = StudentProgram::query()->updateOrCreate(
                    ['student_id' => $student->id, 'program_id' => $program->id],
                    [
                        'status' => StudentProgramStatus::Active,
                        'enrolled_at' => now()->subDays(2),
                        'cached_gpa' => null,
                    ]
                );

                // Enroll in first 2–3 open fall offerings
                foreach (array_slice($offerings, 0, 3) as $j => $offering) {
                    if ($offering->status !== OfferingStatus::Open) {
                        continue;
                    }
                    Enrollment::query()->updateOrCreate(
                        ['student_id' => $student->id, 'offering_id' => $offering->id],
                        [
                            'student_program_id' => $sp->id,
                            'status' => $j === 2 && $i === 0 ? EnrollmentStatus::Waitlisted : EnrollmentStatus::Enrolled,
                            'is_audit' => false,
                            'enrolled_at' => now()->subDay(),
                            'grade_type' => GradeType::InProgress,
                            'grade_status' => GradeStatus::InProgress,
                            'progress_percent' => ($i + 1) * 5,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Classroom + money rows so a client walkthrough is not empty after seed.
     *
     * @param  array<string, User>  $users
     * @param  list<CourseOffering>  $offerings
     */
    private function seedClassroomAndMoney(array $users, array $offerings): void
    {
        foreach ($offerings as $offering) {
            $offering->loadMissing(['course', 'weeks']);
        }

        $ins1 = $users['ins1@spims.test'];
        $ins2 = $users['ins2@spims.test'];
        $fin = $users['fin@spims.test'];
        $aca = $users['aca@spims.test'];
        $student1 = $users['student1@spims.test'];
        $student3 = $users['student3@spims.test'];
        $student6 = $users['student6@spims.test'];
        $student7 = $users['student7@spims.test'];
        $student9 = $users['student9@spims.test'];
        $dual = $users['dual@spims.test'];

        $th101 = $this->offeringByCourseCode($offerings, 'TH101', OfferingMode::Cohort);
        $bi101 = $this->offeringByCourseCode($offerings, 'BI101', OfferingMode::Cohort);
        $free1 = $this->offeringByCourseCode($offerings, 'FREE1', OfferingMode::Cohort);
        $et101SelfPaced = $this->offeringByCourseCode($offerings, 'ET101', OfferingMode::SelfPaced);

        $offeringsService = app(OfferingService::class);
        $th101Items = $this->seedWeekOneContent($offeringsService, $ins1, $th101, [
            [ContentItemType::Text->value, 'Welcome to Introduction to Theology', 'A short orientation for Week 1. Read this note, then the assigned reading.'],
            [ContentItemType::Reading->value, 'Week 1 reading — course overview', 'Skim the course outline and note the weekly rhythm: reading, live session, and a short check.'],
            [ContentItemType::Text->value, 'How this course is organized', 'Each week unlocks on its date. Complete the reading before the live session.'],
        ]);
        $this->seedWeekOneContent($offeringsService, $ins2, $bi101, [
            [ContentItemType::Text->value, 'Welcome to Old Testament Survey', 'Week 1 introduces the survey map for this course.'],
            [ContentItemType::Reading->value, 'Week 1 reading — survey map', 'Read the unit map and list the books covered in the first half of the term.'],
            [ContentItemType::Text->value, 'Study notes for Week 1', 'Bring one question from the reading to the live session.'],
        ]);

        $this->seedTh101AssessmentAndGradebook($ins1, $th101, $th101Items[0] ?? null);
        $this->seedInvoicesPaymentsAndWallet($fin, $student1, $student9);
        $this->seedTh101Attendance($ins1, $th101, $student1, $student6, $student7);
        $this->seedTh101Announcement($ins1, $th101);
        $this->seedTh101LiveSession($ins1, $th101);
        $this->seedTh101Discussion($ins1, $student1, $th101);
        $this->seedApplicationAnswers($student1, $student3);
        $this->seedDualRoleAccess($aca, $dual, $free1, $et101SelfPaced);
    }

    /**
     * @param  list<CourseOffering>  $offerings
     */
    private function offeringByCourseCode(array $offerings, string $code, OfferingMode $mode): CourseOffering
    {
        foreach ($offerings as $offering) {
            $offering->loadMissing('course');
            if ($offering->course?->code === $code && $offering->mode === $mode) {
                return $offering;
            }
        }

        throw new \RuntimeException("Demo offering {$code} ({$mode->value}) is missing.");
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $items
     * @return list<ContentItem>
     */
    private function seedWeekOneContent(OfferingService $offerings, User $actor, CourseOffering $offering, array $items): array
    {
        $week = Week::query()
            ->where('offering_id', $offering->id)
            ->where('number', 1)
            ->first();

        if ($week === null) {
            return [];
        }

        $created = [];
        foreach ($items as $i => [$type, $title, $body]) {
            $existing = ContentItem::query()
                ->where('week_id', $week->id)
                ->where('title', $title)
                ->first();

            if ($existing !== null) {
                if (! $existing->isPublished()) {
                    $offerings->publishContentItem($actor, $existing);
                    $existing = $existing->fresh();
                }
                $created[] = $existing;

                continue;
            }

            $created[] = $offerings->addContentItem($actor, $week, [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'order' => $i + 1,
                'published' => true,
            ]);
        }

        return $created;
    }

    private function seedTh101AssessmentAndGradebook(User $ins1, CourseOffering $th101, ?ContentItem $hostItem): void
    {
        $th101->loadMissing('course');
        $banks = app(QuestionBankService::class);
        $assessments = app(AssessmentService::class);
        $gradebook = app(GradebookService::class);

        $bank = QuestionBank::query()
            ->where('course_id', $th101->course_id)
            ->where('name', 'TH101 Week 1')
            ->first();

        if ($bank === null) {
            $bank = $banks->createBank($ins1, $th101->course, 'TH101 Week 1');
        }

        if ($bank->questions()->count() === 0) {
            $banks->addQuestion($ins1, $bank, [
                'type' => QuestionType::McqSingle->value,
                'prompt' => 'What is the catalog code for Introduction to Theology?',
                'points' => 1,
                'options' => [
                    ['text' => 'TH101', 'is_correct' => true],
                    ['text' => 'BI101', 'is_correct' => false],
                    ['text' => 'ET101', 'is_correct' => false],
                    ['text' => 'FREE1', 'is_correct' => false],
                ],
            ]);
            $banks->addQuestion($ins1, $bank, [
                'type' => QuestionType::TrueFalse->value,
                'prompt' => 'TH101 is listed as a three-credit course.',
                'points' => 1,
                'options' => [
                    ['text' => 'True', 'is_correct' => true],
                    ['text' => 'False', 'is_correct' => false],
                ],
            ]);
            $banks->addQuestion($ins1, $bank, [
                'type' => QuestionType::ShortAnswer->value,
                'prompt' => 'Write the course code for Introduction to Theology.',
                'points' => 1,
                'config' => ['accepted_answers' => ['TH101', 'th101']],
            ]);
            $banks->addQuestion($ins1, $bank, [
                'type' => QuestionType::Numeric->value,
                'prompt' => 'How many credit hours does TH101 carry?',
                'points' => 1,
                'config' => ['correct_value' => 3, 'tolerance' => 0],
            ]);
        }

        $assessment = Assessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 check')
            ->first();

        if ($assessment === null) {
            $assessment = $assessments->create($ins1, $th101, [
                'title' => 'TH101 Week 1 check',
                'mode' => AssessmentMode::Quiz->value,
                'content_item_id' => $hostItem?->id,
                'language' => 'en',
                'time_limit_minutes' => 15,
                'opens_at' => now()->subHour(),
                'closes_at' => now()->addWeeks(2),
                'attempts_allowed' => 2,
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'max_points' => 4,
                'released' => false,
            ]);

            foreach ($bank->questions()->orderBy('id')->get() as $i => $question) {
                $assessments->attachQuestion($ins1, $assessment, $question, null, $i + 1);
            }

            $assessments->release($ins1, $assessment);
        }

        if (GradebookComponent::query()->where('offering_id', $th101->id)->doesntExist()) {
            $gradebook->addComponent($ins1, $th101, [
                'name' => 'Exam',
                'weight_percent' => 70,
                'kind' => ComponentKind::Exam->value,
            ]);
            $gradebook->addComponent($ins1, $th101, [
                'name' => 'Attendance',
                'weight_percent' => 30,
                'kind' => ComponentKind::Attendance->value,
            ]);
        }
    }

    private function seedInvoicesPaymentsAndWallet(User $fin, User $student1, User $student9): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $wallets = app(WalletService::class);

        $enrollments = Enrollment::query()
            ->with(['offering.course', 'student'])
            ->where('status', EnrollmentStatus::Enrolled)
            ->get();

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->offering?->course;
            if ($course === null || $course->is_free) {
                continue;
            }

            $invoices->createForEnrollment($fin, $enrollment);
        }

        $student9Invoice = Invoice::query()
            ->where('student_id', $student9->id)
            ->where('total_minor', '>', 0)
            ->orderBy('created_at')
            ->first();

        if ($student9Invoice !== null && $student9Invoice->payments()->doesntExist()) {
            $payment = $payments->recordManual(
                $fin,
                $student9Invoice,
                PaymentMethod::ManualCash,
                $student9Invoice->total_minor,
                null,
                'DEMO-CASH-STUDENT9'
            );
            $payments->verifyManual($fin, $payment);
        }

        $wallet = $wallets->ensureWallet($student1);
        if ($wallet->balance(Currency::Egp, WalletKind::Money) === 0) {
            $wallets->credit(
                $student1,
                Currency::Egp,
                WalletKind::Money,
                5000,
                LedgerReason::AdminGrant,
                $fin,
                note: 'Demo EGP wallet balance'
            );
        }
    }

    private function seedTh101Attendance(User $ins1, CourseOffering $th101, User $student1, User $student6, User $student7): void
    {
        $attendance = app(AttendanceService::class);

        $session = ClassSession::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 class')
            ->first();

        if ($session === null) {
            $session = $attendance->openSession($ins1, $th101, [
                'title' => 'TH101 Week 1 class',
                'scheduled_start' => now()->subHours(2),
                'duration_minutes' => 60,
                'mode' => ClassSessionMode::InPerson->value,
                'location' => 'Demo classroom',
            ]);
        }

        if ($session->entries()->exists()) {
            return;
        }

        $attendance->markRoster($ins1, $session, [
            ['student_id' => $student1->id, 'status' => AttendanceStatus::Present->value],
            ['student_id' => $student6->id, 'status' => AttendanceStatus::Late->value],
            ['student_id' => $student7->id, 'status' => AttendanceStatus::Absent->value],
        ], (int) $session->lock_version);

        $session = $session->fresh();
        $attendance->excuse($ins1, $session, $student7, 'Family obligation', (int) $session->lock_version);
    }

    private function seedTh101Announcement(User $ins1, CourseOffering $th101): void
    {
        if (Announcement::query()->where('offering_id', $th101->id)->exists()) {
            return;
        }

        $announcements = app(AnnouncementService::class);
        $draft = $announcements->draft($ins1, $th101, [
            'title' => 'Week 1 is open',
            'body' => 'Please complete the Week 1 reading and join the live session this week.',
            'is_banner' => true,
        ]);
        $announcements->publish($ins1, $draft);
    }

    private function seedTh101LiveSession(User $ins1, CourseOffering $th101): void
    {
        if (LiveSession::query()->where('offering_id', $th101->id)->exists()) {
            return;
        }

        app(LiveSessionService::class)->schedule($ins1, $th101, [
            'title' => 'TH101 Week 1 live session',
            'scheduled_start' => now()->addHours(8),
            'duration_minutes' => 60,
        ]);
    }

    private function seedTh101Discussion(User $ins1, User $student1, CourseOffering $th101): void
    {
        $discussions = app(DiscussionService::class);
        $board = $discussions->provisionBoard($ins1, $th101);

        if (DiscussionPost::query()->whereHas('thread', fn ($q) => $q->where('board_id', $board->id))->exists()) {
            return;
        }

        $thread = $discussions->createThread($ins1, $board, [
            'title' => 'Week 1 introductions',
            'body' => 'Introduce yourself in a sentence and say what you hope to learn this term.',
        ]);
        $discussions->post($student1, $thread, 'Looking forward to this course.');
    }

    private function seedApplicationAnswers(User $student1, User $student3): void
    {
        $answers = [
            $student1->id => [
                'Why do you want to join?' => 'I want to study theology in a structured program.',
                'Parish name' => 'St. Mark Parish',
            ],
            $student3->id => [
                'Why do you want to join?' => 'I hope to join the diploma this year.',
                'Parish name' => 'St. Mary Parish',
            ],
        ];

        foreach ($answers as $applicantId => $byLabel) {
            $application = Application::query()
                ->where('applicant_id', $applicantId)
                ->with('form.fields')
                ->first();

            if ($application === null || $application->form === null) {
                continue;
            }

            foreach ($application->form->fields as $field) {
                $value = $byLabel[$field->label] ?? null;
                if ($value === null) {
                    continue;
                }

                ApplicationFieldValue::query()->updateOrCreate(
                    ['application_id' => $application->id, 'field_id' => $field->id],
                    ['value' => $value]
                );
            }
        }
    }

    private function seedDualRoleAccess(User $aca, User $dual, CourseOffering $free1, CourseOffering $et101SelfPaced): void
    {
        app(OfferingService::class)->assignStaff(
            $aca,
            $free1,
            $dual->id,
            OfferingStaffRole::Instructor->value
        );

        $already = Enrollment::query()
            ->where('student_id', $dual->id)
            ->where('offering_id', $et101SelfPaced->id)
            ->exists();

        if ($already) {
            return;
        }

        app(EnrollmentService::class)->register($dual, $et101SelfPaced);
    }
}
