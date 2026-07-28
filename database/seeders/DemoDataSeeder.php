<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\FormFieldType;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\OfferingStaff;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Week;
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
}
