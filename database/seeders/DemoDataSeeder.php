<?php

namespace Database\Seeders;

use App\Enums\AdvisingHoldKind;
use App\Enums\ApplicationStatus;
use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\CommunicationChannel;
use App\Enums\CompletionCriterionKind;
use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\EventReservationStatus;
use App\Enums\FeedbackQuestionKind;
use App\Enums\FormFieldType;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\LedgerReason;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectDeliverableKind;
use App\Enums\ProjectGradingMode;
use App\Enums\ProjectReviewStatus;
use App\Enums\ProgramType;
use App\Enums\QuestionType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Enums\SubmissionType;
use App\Enums\UserStatus;
use App\Enums\WalletKind;
use App\Models\AcademicYear;
use App\Models\AdvisingHold;
use App\Models\AdvisorAssignment;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CertificateTemplate;
use App\Models\ClassSession;
use App\Models\CommunicationLog;
use App\Models\CompletionCriterion;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\DiscussionPost;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSubmissionIdentity;
use App\Models\FeedbackSurvey;
use App\Models\GradebookComponent;
use App\Models\GradingScheme;
use App\Models\Invoice;
use App\Models\LiveQuiz;
use App\Models\LiveSession;
use App\Models\Notification;
use App\Models\OfferingStaff;
use App\Models\PaymentPlan;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectMembership;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WalletTransaction;
use App\Models\Week;
use App\Services\Academics\TranslationService;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AssignmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Communications\AnnouncementService;
use App\Services\Communications\ChannelDispatcher;
use App\Services\Completion\CompletionService;
use App\Services\Credentials\CertificateTemplateService;
use App\Services\Credentials\CredentialService;
use App\Services\Discussions\DiscussionService;
use App\Services\Enrollment\AdvisingService;
use App\Services\Enrollment\EnrollmentService;
use App\Services\Events\EventCheckInService;
use App\Services\Events\EventService;
use App\Services\Feedback\FeedbackIdentityRevealService;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\FeedbackSurveyService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentPlanService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\WalletService;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Services\Live\LiveSessionService;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\Notifications\NotificationService;
use App\Services\Offerings\OfferingService;
use App\Services\Projects\ProjectAssessmentService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectTeamService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Spims@Test2026!';

    /** Email suffix that identifies all demo accounts. */
    public const DEMO_EMAIL_SUFFIX = '@spims.test';

    /** Demo program codes — used by DemoResetCommand to scope the wipe. */
    public const DEMO_PROGRAM_CODES = ['DIP-THEO', 'CERT-LIT', 'DEG-BTH', 'CERT-BIB', 'DEG-DIAC'];

    /** Demo course codes — used by DemoResetCommand to scope the wipe. */
    public const DEMO_COURSE_CODES = ['TH101', 'TH201', 'TH301', 'BI101', 'BI102', 'BI201', 'LI101', 'LI201', 'CH101', 'CH201', 'ET101', 'FREE1'];

    /** Demo academic year names — used by DemoResetCommand to scope the wipe. */
    public const DEMO_YEAR_NAMES = ['2025/2026', '2026/2027'];

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
        $this->seedClassroomAndMoney($users, $offerings, $programs, $courses);

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
            'DIP-THEO' => [
                'name' => 'Diploma in Theology',
                'type' => ProgramType::Diploma,
                'passing_threshold' => 60,
                'max_credits_per_semester' => 15,
                'max_courses_per_semester' => 5,
                'max_semesters_to_graduate' => 8,
                'elective_credits_required' => 3,
                'description' => 'A comprehensive diploma that introduces students to foundational theological disciplines including biblical studies, church history, and systematic theology.',
                'marketing_summary' => 'Begin your theological journey with our structured Diploma in Theology — ideal for those entering ministry or lay service.',
                'enforce_year_sequence' => false,
            ],
            'CERT-LIT' => [
                'name' => 'Certificate in Liturgics',
                'type' => ProgramType::Certificate,
                'passing_threshold' => 70,
                'max_credits_per_semester' => 12,
                'max_courses_per_semester' => 4,
                'max_semesters_to_graduate' => 4,
                'elective_credits_required' => 0,
                'description' => 'A focused certificate program covering Coptic liturgical tradition, including the Divine Liturgy, hymns, and liturgical calendar.',
                'marketing_summary' => 'Deepen your understanding of Coptic worship with our Certificate in Liturgics.',
                'enforce_year_sequence' => false,
            ],
            'DEG-BTH' => [
                'name' => 'Bachelor of Theology',
                'type' => ProgramType::Degree,
                'passing_threshold' => 60,
                'max_credits_per_semester' => 18,
                'max_courses_per_semester' => 6,
                'max_semesters_to_graduate' => 12,
                'elective_credits_required' => 6,
                'description' => 'A full degree program spanning systematic theology, biblical studies, church history, and practical ministry. Requires year-sequence completion for core courses.',
                'marketing_summary' => 'Earn a recognized Bachelor of Theology through our rigorous, parish-rooted academic program.',
                'enforce_year_sequence' => false,
            ],
            'CERT-BIB' => [
                'name' => 'Certificate in Biblical Studies',
                'type' => ProgramType::Certificate,
                'passing_threshold' => 65,
                'max_credits_per_semester' => 9,
                'max_courses_per_semester' => 3,
                'max_semesters_to_graduate' => 3,
                'elective_credits_required' => 0,
                'description' => 'An introductory certificate covering the Old Testament Survey, New Testament Survey, and Pauline Epistles.',
                'marketing_summary' => 'Study Scripture systematically with our Certificate in Biblical Studies.',
                'enforce_year_sequence' => false,
            ],
            // enforce_year_sequence = true program — demonstrates both branches of year-sequence rule (Step 6c)
            'DEG-DIAC' => [
                'name' => 'Diaconal Studies (Year-Sequenced)',
                'type' => ProgramType::Degree,
                'passing_threshold' => 65,
                'max_credits_per_semester' => 12,
                'max_courses_per_semester' => 4,
                'max_semesters_to_graduate' => 8,
                'elective_credits_required' => 0,
                'description' => 'A structured degree in diaconal ministry requiring students to complete Year 1 courses before advancing to Year 2. Year-sequencing is strictly enforced.',
                'marketing_summary' => 'A year-sequenced pathway preparing deacons for ordained service.',
                'enforce_year_sequence' => true,
            ],
        ];

        $programs = [];
        foreach ($defs as $code => $attrs) {
            $programs[$code] = Program::query()->updateOrCreate(
                ['code' => $code],
                array_merge($attrs, [
                    'signatory_name' => 'Dean of Studies',
                    'signatory_title' => 'Academic Dean',
                    'grading_scheme_id' => $scheme?->id,
                    'active' => true,
                ])
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
            ['TH101', 'Introduction to Theology', 3, 15000, 75000, false, 'An entry-level survey of theological method, the nature of doctrine, and the major categories of systematic theology.'],
            ['TH201', 'Patristics I', 3, 18000, 90000, false, 'A study of the early Church Fathers and their contribution to Christian doctrine and spiritual formation.'],
            ['TH301', 'Systematic Theology', 4, 20000, 100000, false, 'A comprehensive examination of the major loci of Christian systematic theology including Christology, pneumatology, and eschatology.'],
            ['BI101', 'Old Testament Survey', 3, 15000, 75000, false, 'An overview of the Hebrew Bible covering its historical, literary, and theological dimensions.'],
            ['BI102', 'New Testament Survey', 3, 15000, 75000, false, 'A survey of the New Testament from the Gospels through Revelation with attention to authorship, date, and message.'],
            ['BI201', 'Pauline Epistles', 3, 17000, 85000, false, 'An in-depth reading of Paul\'s letters with emphasis on Romans, Galatians, and the Corinthian correspondence.'],
            ['LI101', 'Coptic Liturgy Basics', 2, 10000, 50000, false, 'Introduction to the structure and theology of the Coptic Divine Liturgy, with attention to its Alexandrian roots.'],
            ['LI201', 'Divine Liturgy Practicum', 2, 12000, 60000, false, 'A practicum course in Coptic liturgical chant, rites, and deaconate duties within the Divine Liturgy.'],
            ['CH101', 'Church History I', 3, 14000, 70000, false, 'The history of Christianity from apostolic times through the Council of Chalcedon, with focus on the Coptic tradition.'],
            ['CH201', 'Church History II', 3, 14000, 70000, false, 'Continuation of Church History from the post-Chalcedonian era through the modern Coptic revival.'],
            ['ET101', 'Christian Ethics', 2, 11000, 55000, true, 'An introduction to Christian moral reasoning and its application to contemporary ethical questions.'],
            ['FREE1', 'Open Orientation (Free)', 1, 0, 0, true, 'A free orientation course welcoming new students to the SPIMS learning environment.'],
        ];

        $courses = [];
        foreach ($defs as [$code, $title, $credits, $usd, $egp, $free, $description]) {
            $courses[$code] = Course::query()->updateOrCreate(
                ['code' => $code],
                [
                    'title' => $title,
                    'credit_hours' => $credits,
                    'default_price_usd' => $usd,
                    'default_price_egp' => $egp,
                    'is_free' => $free || $usd === 0,
                    'is_standalone' => in_array($code, ['ET101', 'FREE1'], true),
                    'passing_threshold' => 60,
                    'active' => true,
                    'description' => $description,
                ]
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
            // DEG-DIAC has courses at Year 1 and Year 2 — both branches of enforce_year_sequence are testable
            'DEG-DIAC' => [
                ['TH101', RequirementType::Required, 1],
                ['CH101', RequirementType::Required, 1],
                ['TH201', RequirementType::Required, 2],
                ['CH201', RequirementType::Required, 2],
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

        // IN_PROGRESS semester — demonstrates the lifecycle state
        Semester::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Summer'],
            [
                'start_date' => '2027-06-15',
                'end_date' => '2027-08-30',
                'registration_start' => now()->subWeeks(2),
                'registration_end' => now()->addWeeks(3),
                'add_drop_end_week' => 1,
                'last_withdrawal_week' => 4,
                'withdrawal_refund_percent' => 25,
                'status' => OfferingStatus::InProgress,
            ]
        );

        // CLOSED semester — the prior year fall is Completed
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
            ApplicationStatus::Accepted,    // student1
            ApplicationStatus::Submitted,   // student2
            ApplicationStatus::UnderReview, // student3
            ApplicationStatus::Waitlisted,  // student4
            ApplicationStatus::Rejected,    // student5
            ApplicationStatus::Accepted,    // student6
            ApplicationStatus::Accepted,    // student7
            ApplicationStatus::Draft,       // student8
            ApplicationStatus::Accepted,    // student9
            ApplicationStatus::Submitted,   // student10
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

        // Withdrawn application — demonstrates EVERY application status
        $student1 = $users['student1@spims.test'];
        $certBibForm = $forms['CERT-BIB'];
        Application::query()->updateOrCreate(
            ['applicant_id' => $student1->id, 'program_id' => $programs['CERT-BIB']->id],
            [
                'form_id' => $certBibForm->id,
                'status' => ApplicationStatus::Withdrawn,
                'reviewer_id' => null,
                'decision_note' => null,
                'submitted_at' => now()->subDays(20),
                'decided_at' => null,
            ]
        );
    }

    /**
     * Classroom + money rows so a client walkthrough is not empty after seed.
     *
     * @param  array<string, User>  $users
     * @param  list<CourseOffering>  $offerings
     * @param  array<string, Program>  $programs
     * @param  array<string, Course>  $courses
     */
    private function seedClassroomAndMoney(array $users, array $offerings, array $programs, array $courses): void
    {
        foreach ($offerings as $offering) {
            $offering->loadMissing(['course', 'weeks']);
        }

        $ins1 = $users['ins1@spims.test'];
        $ins2 = $users['ins2@spims.test'];
        $fin = $users['fin@spims.test'];
        $aca = $users['aca@spims.test'];
        $adm = $users['adm@spims.test'];
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

        // Seed additional content item types (VIDEO, FILE) in TH101 Week 2
        $this->seedWeekTwoContent($offeringsService, $ins1, $th101);

        $this->seedTh101AssessmentAndGradebook($ins1, $th101, $th101Items[0] ?? null);
        $this->seedTh101AssignmentAndSubmissions($ins1, $th101, $student1, $student6);
        $this->seedTh101QuizAttempt($student1, $th101);
        $this->seedInvoicesPaymentsAndWallet($fin, $student1, $student9);
        $this->seedTh101Attendance($ins1, $th101, $student1, $student6, $student7);
        $this->seedTh101Announcement($ins1, $th101);
        $this->seedTh101LiveSession($ins1, $th101);
        $this->seedTh101Discussion($ins1, $student1, $th101);
        $this->seedApplicationAnswers($student1, $student3);
        $this->seedDualRoleAccess($aca, $dual, $free1, $et101SelfPaced);

        // 8A additions
        $this->seedReleasedGrades($ins1, $th101);
        $this->seedCredential($aca, $student1);
        $this->seedEvent($adm);
        $this->seedSurvey($ins1, $th101);
        $this->seedLiveQuiz($ins1, $th101);
        $this->seedTeamProject($aca, $student1, $th101);

        // Phase B fixtures (responses / reservations / finance locale / notifications)
        $this->seedSurveySubmission($ins1, $student1, $th101);
        $this->seedEventReservation($adm, $student1);
        $this->seedPaymentPlan($student1);
        $this->seedWalletPoints($fin, $student1);
        $this->seedTranslations($aca, $courses, $programs);
        $this->seedStudentNotifications($student1);

        // Phase C — admin depth fixtures
        $this->seedAdvising($aca, $ins1, $student1, $student6, $programs);
        $this->seedCertificateTemplate($aca, $th101);
        $this->seedCommunicationLogs($student1);
        $this->seedCompletionCriteriaAndEvaluate($aca, $th101);
        $this->seedProjectDeliverableSubmission($aca, $student1, $th101);
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

    /**
     * Seed VIDEO and FILE content item types in TH101 Week 2 so every major
     * content-item type is represented in the demo walkthrough offering.
     * FILE items store a real PDF so learn download works.
     */
    private function seedWeekTwoContent(OfferingService $offeringsService, User $ins1, CourseOffering $th101): void
    {
        $week2 = Week::query()
            ->where('offering_id', $th101->id)
            ->where('number', 2)
            ->first();

        if ($week2 === null) {
            return;
        }

        $fileTitle = 'TH101 Week 2 reference sheet (PDF)';
        $items = [
            [ContentItemType::Video->value, 'TH101 Week 2 intro video', 'Watch this short clip before the live session. Key themes: the nature of divine revelation.', null],
            [ContentItemType::File->value, $fileTitle, 'A downloadable reference sheet covering the key terms from Week 2.', $this->demoPdfUpload('th101-week2-reference.pdf')],
            [ContentItemType::Reading->value, 'TH101 Week 2 assigned reading', 'Complete the assigned pages before the Week 2 live session.', null],
            [ContentItemType::Text->value, 'TH101 Week 2 study notes', 'Notes to supplement the assigned reading. Review before the quiz.', null],
        ];

        foreach ($items as $i => [$type, $title, $body, $upload]) {
            $existing = ContentItem::query()
                ->where('week_id', $week2->id)
                ->where('title', $title)
                ->first();

            if ($existing !== null) {
                if ($type === ContentItemType::File->value && ! $existing->isStoredFile() && $upload !== null) {
                    $offeringsService->updateContentItem($ins1, $existing, [], $upload);
                    $existing = $existing->fresh();
                }
                if (! $existing->isPublished()) {
                    $offeringsService->publishContentItem($ins1, $existing);
                }
                continue;
            }

            $offeringsService->addContentItem($ins1, $week2, [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'order' => $i + 1,
                'published' => true,
            ], $upload);
        }
    }

    /**
     * Minimal valid PDF for demo FILE content uploads (magic-byte checked).
     */
    private function demoPdfUpload(string $originalName): UploadedFile
    {
        $contents = "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF\n";
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spims-demo-'.md5($originalName).'.pdf';
        file_put_contents($path, $contents);

        return new UploadedFile($path, $originalName, 'application/pdf', null, true);
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

    /**
     * ASSIGNMENT content item on TH101 Week 1 with two submissions:
     * student1 graded, student6 pending review.
     */
    private function seedTh101AssignmentAndSubmissions(
        User $ins1,
        CourseOffering $th101,
        User $student1,
        User $student6,
    ): void {
        $week = Week::query()
            ->where('offering_id', $th101->id)
            ->where('number', 1)
            ->first();

        if ($week === null) {
            throw new \RuntimeException('TH101 Week 1 is missing; cannot seed assignment.');
        }

        $title = 'TH101 Week 1 reflection';
        $item = ContentItem::query()
            ->where('week_id', $week->id)
            ->where('title', $title)
            ->first();

        $offerings = app(OfferingService::class);
        if ($item === null) {
            $item = $offerings->addContentItem($ins1, $week, [
                'type' => ContentItemType::Assignment->value,
                'title' => $title,
                'body' => 'Write a short reflection on the Week 1 reading.',
                'order' => ($week->items()->max('order') ?? 0) + 1,
                'published' => true,
            ]);
        } elseif (! $item->isPublished()) {
            $offerings->publishContentItem($ins1, $item);
            $item = $item->fresh();
        }

        $assignments = app(AssignmentService::class);
        $assignment = Assignment::query()->where('content_item_id', $item->id)->first();
        if ($assignment === null) {
            $examComponent = GradebookComponent::query()
                ->where('offering_id', $th101->id)
                ->where('kind', ComponentKind::Exam)
                ->first();

            $assignment = $assignments->create($ins1, $item, [
                'instructions' => 'In 150–250 words, summarize one idea from the Week 1 reading and why it matters for theology students.',
                'submission_type' => SubmissionType::Text->value,
                'max_points' => 100,
                'released' => true,
                'due_date' => now()->addWeeks(1),
                'component_id' => $examComponent?->id,
            ]);
        }

        if (AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student1->id)
            ->doesntExist()) {
            $graded = $assignments->submit(
                $student1,
                $assignment,
                'The Week 1 reading frames theology as disciplined reflection on revelation. That framing helps students separate devotion from study without opposing them.'
            );
            $assignments->grade(
                $ins1,
                $graded,
                88.0,
                'Clear summary with a useful takeaway. Expand the parish application next time.'
            );
        }

        if (AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student6->id)
            ->doesntExist()) {
            $assignments->submit(
                $student6,
                $assignment,
                'ملخص قصير لقراءة الأسبوع الأول — بانتظار المراجعة.'
            );
        }
    }

    /**
     * Submitted + auto-graded quiz attempt for student1 on TH101 Week 1 check.
     */
    private function seedTh101QuizAttempt(User $student1, CourseOffering $th101): void
    {
        $assessment = Assessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 check')
            ->first();

        if ($assessment === null) {
            throw new \RuntimeException('TH101 Week 1 check assessment is missing; cannot seed quiz attempt.');
        }

        if (AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student1->id)
            ->whereIn('status', [
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
                AttemptStatus::Graded,
            ])
            ->exists()) {
            return;
        }

        $attempts = app(AttemptService::class);
        $attempt = $attempts->start($student1, $assessment);
        $questions = Question::query()
            ->with('options')
            ->whereIn('id', $attempt->question_ids ?? [])
            ->get()
            ->keyBy('id');

        $answers = [];
        foreach ($attempt->question_ids ?? [] as $qid) {
            $question = $questions->get($qid);
            if ($question === null) {
                continue;
            }

            $answers[$qid] = match ($question->type) {
                QuestionType::McqSingle, QuestionType::TrueFalse => [
                    'option_id' => $question->options->firstWhere('is_correct', true)?->id,
                ],
                QuestionType::ShortAnswer, QuestionType::FillBlank => [
                    'text' => 'TH101',
                ],
                QuestionType::Numeric => [
                    'value' => 3,
                ],
                default => ['text' => ''],
            };
        }

        $attempts->autosave($student1, $attempt, $answers);
        $attempts->submit($student1, $attempt);
    }

    // ─── 8A additions ────────────────────────────────────────────────────────

    /**
     * Submit then lock TH101 grades so the walkthrough shows honest Locked records.
     */
    private function seedReleasedGrades(User $ins1, CourseOffering $th101): void
    {
        $gradebook = app(GradebookService::class);

        // submitGrades is idempotent: it skips Locked enrollments and sets the rest to Submitted.
        $gradebook->submitGrades($ins1, $th101);
        $gradebook->lockGrades($ins1, $th101);
    }

    /**
     * Issue a transcript credential for student1. The qr_token can be used with
     * the /verify/{token} endpoint to demonstrate the verifier widget.
     */
    private function seedCredential(User $aca, User $student1): void
    {
        if (Credential::query()->where('student_id', $student1->id)->whereNull('revoked_at')->exists()) {
            return;
        }

        try {
            app(CredentialService::class)->issueTranscript($aca, $student1, 'en');
        } catch (\Throwable) {
            // Credential issuance is best-effort for demo data (PDF rendering may be unavailable).
        }
    }

    /**
     * Publish an event with open seats so student1 can reserve a spot.
     */
    private function seedEvent(User $adm): void
    {
        if (Event::query()->where('title', 'Theology Orientation Day')->exists()) {
            return;
        }

        $eventService = app(EventService::class);
        $event = $eventService->create($adm, [
            'title' => 'Theology Orientation Day',
            'description' => 'A welcome day for all theology students with talks, Q&A, and fellowship.',
            'starts_at' => now()->addDays(14)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(14)->setTime(17, 0)->toDateTimeString(),
            'venue' => 'St. Mark Cathedral Hall',
            'capacity' => 50,
            'waitlist_enabled' => true,
        ]);
        $eventService->publish($adm, $event);
    }

    /**
     * Create and publish an open survey for TH101 with one question of each
     * FeedbackQuestionKind: TEXT, SINGLE, MULTI, SCALE.
     */
    private function seedSurvey(User $ins1, CourseOffering $th101): void
    {
        if (FeedbackSurvey::query()->where('offering_id', $th101->id)->where('title', 'TH101 Week 1 Feedback')->exists()) {
            return;
        }

        $surveyService = app(FeedbackSurveyService::class);
        $survey = $surveyService->create($ins1, [
            'title' => 'TH101 Week 1 Feedback',
            'anonymous_default' => true,
            'opens_at' => now()->subHour()->toDateTimeString(),
            'closes_at' => now()->addMonths(1)->toDateTimeString(),
        ], $th101);

        $surveyService->addQuestion($ins1, $survey, [
            'prompt' => 'How would you rate this week overall? (1 = poor, 5 = excellent)',
            'kind' => FeedbackQuestionKind::Scale->value,
            'position' => 1,
            'required' => true,
        ]);
        $surveyService->addQuestion($ins1, $survey, [
            'prompt' => 'What did you find most valuable in Week 1?',
            'kind' => FeedbackQuestionKind::Text->value,
            'position' => 2,
            'required' => false,
        ]);
        $surveyService->addQuestion($ins1, $survey, [
            'prompt' => 'Which topic interested you most in Week 1?',
            'kind' => FeedbackQuestionKind::Single->value,
            'position' => 3,
            'required' => true,
            'options' => ['Theology basics', 'Church history', 'Patristics', 'Liturgics'],
        ]);
        $surveyService->addQuestion($ins1, $survey, [
            'prompt' => 'Which of the following resources did you use this week?',
            'kind' => FeedbackQuestionKind::Multi->value,
            'position' => 4,
            'required' => false,
            'options' => ['Assigned reading', 'Live session', 'Study notes', 'Discussion board'],
        ]);

        $surveyService->publish($ins1, $survey);
    }

    /**
     * Create a live quiz for TH101 (Ready status, not yet started).
     * student1 can join when an instructor starts a session from it.
     */
    private function seedLiveQuiz(User $ins1, CourseOffering $th101): void
    {
        if (LiveQuiz::query()->where('offering_id', $th101->id)->where('title', 'TH101 Live Check Quiz')->exists()) {
            return;
        }

        app(LiveQuizHostService::class)->createQuiz($ins1, $th101, 'TH101 Live Check Quiz', [
            [
                'prompt' => 'What does the course code "TH" stand for in TH101?',
                'time_limit_seconds' => 30,
                'points' => 1000,
                'options' => [
                    ['label' => 'Theology', 'is_correct' => true],
                    ['label' => 'Theory', 'is_correct' => false],
                    ['label' => 'Thinking', 'is_correct' => false],
                    ['label' => 'Tradition', 'is_correct' => false],
                ],
            ],
            [
                'prompt' => 'How many credit hours does TH101 carry?',
                'time_limit_seconds' => 20,
                'points' => 500,
                'options' => [
                    ['label' => '2', 'is_correct' => false],
                    ['label' => '3', 'is_correct' => true],
                    ['label' => '4', 'is_correct' => false],
                    ['label' => '1', 'is_correct' => false],
                ],
            ],
        ]);
    }

    /**
     * Create a team project assessment for TH101 with student1 as a member.
     * The deliverable slot is open (due in the future).
     */
    private function seedTeamProject(User $aca, User $student1, CourseOffering $th101): void
    {
        if (ProjectAssessment::query()->where('offering_id', $th101->id)->where('title', 'TH101 Group Research Project')->exists()) {
            return;
        }

        $assessmentService = app(ProjectAssessmentService::class);
        $teamService = app(ProjectTeamService::class);

        // Deliverables grading mode: one deliverable worth 100 pts
        $assessment = $assessmentService->create($aca, $th101, [
            'title' => 'TH101 Group Research Project',
            'team_size_min' => 1,
            'team_size_max' => 4,
            'join_opens_at' => now()->subDay()->toDateTimeString(),
            'join_closes_at' => now()->addMonths(2)->toDateTimeString(),
            'allow_leave_once' => true,
            'grading_mode' => ProjectGradingMode::Deliverables->value,
            'max_points' => 100,
        ]);

        $phase = $assessmentService->addPhase($aca, $assessment, [
            'name' => 'Final Submission',
            'position' => 1,
            'due_at' => now()->addMonths(2)->toDateTimeString(),
        ]);

        $assessmentService->addDeliverable($aca, $phase, [
            'kind' => ProjectDeliverableKind::File->value,
            'title' => 'Research Paper',
            'max_files' => 1,
            'max_file_mb' => 10,
            'due_at' => now()->addMonths(2)->toDateTimeString(),
            'points' => 100,
        ]);

        $assessment = $assessment->fresh();
        $assessmentService->publish($aca, $assessment);
        $assessment = $assessment->fresh();

        // Enroll student1 in a team
        try {
            $existing = ProjectMembership::query()
                ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id))
                ->where('student_id', $student1->id)
                ->whereNull('left_at')
                ->exists();

            if (! $existing) {
                $teamService->join($student1, $assessment);
            }
        } catch (\Throwable) {
            // Team join is best-effort for demo data.
        }
    }

    // ─── Phase B fixtures ────────────────────────────────────────────────────

    /**
     * student1 submits the TH101 Week 1 feedback survey; instructor requests identity reveal.
     */
    private function seedSurveySubmission(User $ins1, User $student1, CourseOffering $th101): void
    {
        $survey = FeedbackSurvey::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 Feedback')
            ->with('questions')
            ->first();

        if ($survey === null) {
            return;
        }

        $already = FeedbackSubmissionIdentity::query()
            ->where('student_id', $student1->id)
            ->whereHas('submission', fn ($q) => $q->where('survey_id', $survey->id))
            ->exists();

        if (! $already) {
            $answers = [];
            foreach ($survey->questions as $question) {
                $kind = $question->kind instanceof FeedbackQuestionKind
                    ? $question->kind
                    : FeedbackQuestionKind::from((string) $question->kind);

                $answers[$question->id] = match ($kind) {
                    FeedbackQuestionKind::Scale => 5,
                    FeedbackQuestionKind::Text => 'The reading overview and live session were most valuable.',
                    FeedbackQuestionKind::Single => 'Theology basics',
                    FeedbackQuestionKind::Multi => ['Assigned reading', 'Live session'],
                };
            }

            app(FeedbackSubmissionService::class)->submit($student1, $survey, $answers);
        }

        $submission = FeedbackSubmission::query()
            ->where('survey_id', $survey->id)
            ->whereHas('identity', fn ($q) => $q->where('student_id', $student1->id))
            ->first();

        if ($submission === null) {
            return;
        }

        $revealExists = FeedbackIdentityRevealRequest::query()
            ->where('submission_id', $submission->id)
            ->exists();

        if (! $revealExists) {
            try {
                app(FeedbackIdentityRevealService::class)->request(
                    $ins1,
                    $submission,
                    'Demo walkthrough — review Week 1 feedback authenticity'
                );
            } catch (\Throwable) {
                // Identity reveal is optional for the demo walkthrough.
            }
        }
    }

    /**
     * student1 reserves a seat on Theology Orientation Day and is checked in.
     */
    private function seedEventReservation(User $adm, User $student1): void
    {
        $event = Event::query()->where('title', 'Theology Orientation Day')->first();
        if ($event === null) {
            return;
        }

        $events = app(EventService::class);
        $reservation = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('student_id', $student1->id)
            ->whereIn('status', [
                EventReservationStatus::Reserved->value,
                EventReservationStatus::Waitlisted->value,
            ])
            ->first();

        if ($reservation === null) {
            $reservation = $events->reserve($student1, $event);
        }

        if ($reservation->checkIn()->exists()) {
            return;
        }

        if ($reservation->status !== EventReservationStatus::Reserved) {
            return;
        }

        $checkIns = app(EventCheckInService::class);
        $payload = $checkIns->issueQr($reservation);
        $checkIns->verify($adm, $payload);
    }

    /**
     * Open a 3-installment payment plan on one of student1's unpaid invoices.
     * start_on in the past so the first installment is Due.
     */
    private function seedPaymentPlan(User $student1): void
    {
        if (PaymentPlan::query()->whereHas('invoice', fn ($q) => $q->where('student_id', $student1->id))->exists()) {
            return;
        }

        $invoice = Invoice::query()
            ->where('student_id', $student1->id)
            ->where('total_minor', '>', 0)
            ->orderBy('created_at')
            ->get()
            ->first(fn (Invoice $row) => $row->amountDue() > 0 && $row->amountPaid() === 0 && $row->openPaymentPlan() === null);

        if ($invoice === null) {
            return;
        }

        app(PaymentPlanService::class)->create($student1, $invoice, 3, now()->subDays(2));
    }

    /**
     * Credit points wallet for student1 (distinct from money balance).
     */
    private function seedWalletPoints(User $fin, User $student1): void
    {
        $wallets = app(WalletService::class);
        $wallet = $wallets->ensureWallet($student1);

        if ($wallet->balance(Currency::Egp, WalletKind::Points) > 0) {
            return;
        }

        if (WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('kind', WalletKind::Points)
            ->exists()) {
            return;
        }

        $wallets->grantPoints($fin, $student1, Currency::Egp, 2500, 'Demo EGP points grant');
    }

    /**
     * Human translations for one course title and one program name (ar + fr).
     *
     * @param  array<string, Course>  $courses
     * @param  array<string, Program>  $programs
     */
    private function seedTranslations(User $aca, array $courses, array $programs): void
    {
        $translations = app(TranslationService::class);
        $th101 = $courses['TH101'] ?? null;
        $diploma = $programs['DIP-THEO'] ?? null;

        if ($th101 !== null) {
            $translations->upsert($aca, 'Course', (string) $th101->id, 'title', 'ar', 'مقدمة في اللاهوت', true);
            $translations->upsert($aca, 'Course', (string) $th101->id, 'title', 'fr', 'Introduction à la théologie', true);
        }

        if ($diploma !== null) {
            $translations->upsert($aca, 'Program', (string) $diploma->id, 'name', 'ar', 'دبلوم في اللاهوت', true);
            $translations->upsert($aca, 'Program', (string) $diploma->id, 'name', 'fr', 'Diplôme en théologie', true);
        }
    }

    /**
     * In-app notifications for student1 covering announcement and invoice types.
     */
    private function seedStudentNotifications(User $student1): void
    {
        $notifications = app(NotificationService::class);

        if (! Notification::query()
            ->where('user_id', $student1->id)
            ->where('type', 'announcement.published')
            ->exists()) {
            $announcement = Announcement::query()->where('title', 'Week 1 is open')->first();
            $notifications->notify(
                $student1,
                'announcement.published',
                'Week 1 is open',
                'Please complete the Week 1 reading and join the live session this week.',
                $announcement !== null ? ['announcement_id' => $announcement->id] : null,
                alsoEmail: false,
            );
        }

        if (! Notification::query()
            ->where('user_id', $student1->id)
            ->where('type', 'finance.invoice_issued')
            ->exists()) {
            $invoice = Invoice::query()
                ->where('student_id', $student1->id)
                ->where('total_minor', '>', 0)
                ->orderBy('created_at')
                ->first();

            $notifications->notify(
                $student1,
                'finance.invoice_issued',
                'Tuition invoice ready',
                'You have an open tuition invoice ready for payment or installment plan.',
                $invoice !== null ? ['invoice_id' => $invoice->id] : null,
                alsoEmail: false,
            );
        }
    }

    // ─── Phase C — admin depth ────────────────────────────────────────────────

    /**
     * Advisor assignment for student1; active hold on student6; released hold
     * history on student1 (so the student1 walkthrough is not enrollment-blocked).
     *
     * @param  array<string, Program>  $programs
     */
    private function seedAdvising(User $aca, User $ins1, User $student1, User $student6, array $programs): void
    {
        $advising = app(AdvisingService::class);
        $programId = $programs['DIP-THEO']->id ?? null;

        if (! AdvisorAssignment::query()
            ->where('student_id', $student1->id)
            ->where('advisor_id', $ins1->id)
            ->exists()) {
            $advising->assignAdvisor($aca, $student1, $ins1, $programId);
        }

        if (! AdvisingHold::query()
            ->where('student_id', $student6->id)
            ->whereNull('released_at')
            ->exists()) {
            $advising->placeHold(
                $aca,
                $student6,
                AdvisingHoldKind::Advising,
                'Demo advising hold — parish recommendation pending.'
            );
        }

        if (! AdvisingHold::query()
            ->where('student_id', $student1->id)
            ->whereNotNull('released_at')
            ->exists()) {
            $hold = $advising->placeHold(
                $aca,
                $student1,
                AdvisingHoldKind::Discipline,
                'Demo released hold — resolved before term start.'
            );
            $advising->releaseHold($aca, $hold);
        }
    }

    /**
     * Global + TH101-scoped certificate templates for /admin/certificate-templates.
     * Credential PDF issuance stays best-effort in seedCredential().
     */
    private function seedCertificateTemplate(User $aca, CourseOffering $th101): void
    {
        $templates = app(CertificateTemplateService::class);
        $th101->loadMissing('course');

        if (CertificateTemplate::query()->whereNull('course_id')->where('locale', 'en')->doesntExist()) {
            $templates->upsert($aca, [
                'course_id' => null,
                'locale' => 'en',
                'title' => 'Certificate of Completion',
                'body' => 'This certifies that {{student_name}} has completed {{course_title}} on {{issued_at}}. Serial: {{serial}}.',
            ]);
        }

        if (CertificateTemplate::query()
            ->where('course_id', $th101->course_id)
            ->where('locale', 'en')
            ->doesntExist()) {
            $templates->upsert($aca, [
                'course_id' => $th101->course_id,
                'locale' => 'en',
                'title' => 'TH101 Certificate of Completion',
                'body' => 'SPIMS certifies that {{student_name}} completed {{course_title}} ({{issued_at}}). Credential {{serial}}.',
            ]);
        }
    }

    /**
     * Ensure /admin/communications has rows. Announcement publish usually writes
     * them; fall back to ChannelDispatcher if the cohort had no deliveries yet.
     */
    private function seedCommunicationLogs(User $student1): void
    {
        if (CommunicationLog::query()->where('recipient_id', $student1->id)->exists()) {
            return;
        }

        $dispatcher = app(ChannelDispatcher::class);
        $dispatcher->dispatch(
            CommunicationChannel::InApp,
            $student1,
            'announcement.published',
            'Demo in-app notice',
            'Week 1 materials are ready.',
        );
        $dispatcher->dispatch(
            CommunicationChannel::Mail,
            $student1,
            'announcement.published',
            'Demo mail notice',
            'Week 1 materials are ready.',
        );
    }

    /**
     * Offering-scoped completion criteria + evaluate for the closing UI.
     * Does not lock grading / announce / close — lockGrades marks enrollments
     * Completed and empties the student attendance walkthrough history.
     */
    private function seedCompletionCriteriaAndEvaluate(User $aca, CourseOffering $th101): void
    {
        $completion = app(CompletionService::class);
        $th101->loadMissing('course');

        $hasCriteria = CompletionCriterion::query()
            ->where(function ($q) use ($th101) {
                $q->where('offering_id', $th101->id)
                    ->orWhere('course_id', $th101->course_id);
            })
            ->exists();

        if (! $hasCriteria) {
            $completion->addCriterion($aca, $th101->course, [
                'kind' => CompletionCriterionKind::MinAttendance->value,
                'threshold' => 50,
                'is_required' => true,
                'offering_id' => $th101->id,
            ]);
            $completion->addCriterion($aca, $th101->course, [
                'kind' => CompletionCriterionKind::MinGrade->value,
                'threshold' => 50,
                'is_required' => false,
                'offering_id' => $th101->id,
            ]);
        }

        $completion->evaluate($aca, $th101);
    }

    /**
     * Upload student1's FILE deliverable and accept it for the TH101 team project.
     */
    private function seedProjectDeliverableSubmission(User $aca, User $student1, CourseOffering $th101): void
    {
        $assessment = ProjectAssessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Group Research Project')
            ->first();

        if ($assessment === null) {
            return;
        }

        $membership = ProjectMembership::query()
            ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id))
            ->where('student_id', $student1->id)
            ->whereNull('left_at')
            ->with('project')
            ->first();

        if ($membership?->project === null) {
            return;
        }

        $project = $membership->project;
        $deliverable = $assessment->phases()
            ->with('deliverables')
            ->get()
            ->flatMap(fn ($phase) => $phase->deliverables)
            ->first();

        if ($deliverable === null) {
            return;
        }

        if (ProjectDeliverableSubmission::query()
            ->where('project_id', $project->id)
            ->where('deliverable_id', $deliverable->id)
            ->exists()) {
            return;
        }

        try {
            $deliverables = app(ProjectDeliverableService::class);
            $submission = $deliverables->submit($student1, $project, $deliverable, [], [
                UploadedFile::fake()->create('research-paper.pdf', 40),
            ]);
            $deliverables->review($aca, $submission, [
                'review_status' => ProjectReviewStatus::Accepted->value,
            ]);
        } catch (\Throwable) {
            // Deliverable upload/review is best-effort for demo data.
        }
    }
}
