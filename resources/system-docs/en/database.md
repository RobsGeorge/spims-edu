## Conventions

- PostgreSQL via Eloquent models under `app/Models/`.
- Migrations are **additive only**.
- Money columns store **integer minor units** (cents / piastres) with a `Currency` enum (`EGP`, `USD`). Never floats; never silent FX conversion.
- Soft deletes / timestamps follow existing table patterns in each domain.

---

## Major model groups

### User / RBAC

| Model | Purpose |
|---|---|
| `User` | Account, profile, preferences |
| `UserRole` | Multi-role assignment (`RoleType` values) |
| `RolePermission` | DB overrides for the permission matrix (Roles hub) |
| `OtpToken` | Email verification / password reset codes |
| `Session` | Web sessions (security flush targets) |
| `Notification` / `NotificationPreference` / `NotificationReminder` | In-app + reminder scheduling |

### Setting / theme

| Model | Purpose |
|---|---|
| `Setting` | Key/value school configuration |
| `Theme` | Branding tokens, logos, favicon |
| `Language` | Locale metadata |
| `Translation` | Academic content translations + verification |

### Program / course / offering / week / content

| Model | Purpose |
|---|---|
| `Program` / `ProgramCourse` / `ProgramRequirementFulfillment` | Curriculum structure |
| `Course` / `CoursePrerequisite` / `CourseInterestFlag` | Catalog + prereqs + interest |
| `AcademicYear` / `Semester` | Calendar and windows |
| `CourseOffering` / `OfferingStaff` | Term instances + instructor/TA |
| `Week` / `ContentItem` | Course player structure |
| `AssessmentTemplate` / `AssessmentTemplateComponent` | Gradebook blueprints |
| `GradingScheme` / `GradeBand` | Letter / GPA bands |
| `CompletionCriterion` / `CompletionResult` | Completion rules / outcomes |
| `OfferingClosing` | Close offering workflow |
| `EmailTemplate` / `CertificateTemplate` | Comms and cert layouts |

### Applications

| Model | Purpose |
|---|---|
| `ApplicationForm` / `ApplicationFormField` | Dynamic admissions forms |
| `Application` / `ApplicationFieldValue` | Submissions and field answers |

### Enrollment / advising

| Model | Purpose |
|---|---|
| `Enrollment` | Student ↔ offering |
| `StudentProgram` | Program membership / matriculation |
| `EnrollmentWeekCompletion` / `EnrollmentItemCompletion` | Progress |
| `AdvisorAssignment` / `AdvisingHold` | Advising and holds |
| `StudentNote` | Staff notes on students |
| `AcademicRecord` | Posted transcript lines |

### Invoice / payment / wallet

| Model | Purpose |
|---|---|
| `Invoice` / `InvoiceLine` | Amounts in minor units + currency |
| `Payment` / `Refund` | Gateway and manual payments |
| `PaymentPlan` / `PaymentPlanInstallment` | Installment plans |
| `WalletAccount` / `WalletTransaction` | Four-bucket wallet ledger |
| `Donation` | Designated giving |

### Assessment / assignment / gradebook

| Model | Purpose |
|---|---|
| `QuestionBank` / `Question` / `QuestionOption` | Banks |
| `Assessment` / `AssessmentQuestion` | Quizzes / exams |
| `AssessmentAttempt` / `AttemptAnswer` | Runner state |
| `ProctorEvent` | Soft integrity events |
| `AssessmentResultAnnouncement` | Release of results |
| `Assignment` / `AssignmentSubmission` / `AssignmentSubmissionVersion` | Coursework |
| `GradebookComponent` | Weighted components |
| `ModuleStudentAssessment` | Module-level assessment links |

### Live / attendance

| Model | Purpose |
|---|---|
| `LiveSession` / `ClassSession` / `SessionRecurrence` | Scheduled sessions |
| `SessionNotificationTarget` | Reminder targeting |
| `AttendancePolicy` / `AttendanceRecord` / `AttendanceEntry` | Attendance |
| `AttendanceCheckInCode` | Self check-in codes |

### Discussion / announcement

| Model | Purpose |
|---|---|
| `DiscussionBoard` / `DiscussionThread` / `DiscussionPost` / `DiscussionGrade` | Boards |
| `Announcement` / `AnnouncementTarget` / `AnnouncementDelivery` / `AnnouncementRevision` | Course/school announcements |
| `CommunicationLog` | Outbound communication audit |

### Credentials / completion

| Model | Purpose |
|---|---|
| `Credential` | Issued transcript / certificate records + public verify token |

### Help CMS

| Model | Purpose |
|---|---|
| `HelpCategory` / `HelpArticle` | Structure |
| `HelpArticleLocale` / `HelpArticleAudience` | Localized bodies + who can see |
| `HelpMedia` | Embedded media |

### Audit

| Model | Purpose |
|---|---|
| `AuditLog` | Who / what / when for audited mutations |

### S6E — feedback, events, live quiz, projects

| Area | Models |
|---|---|
| Feedback | `FeedbackSurvey`, `FeedbackQuestion`, `FeedbackSubmission`, `FeedbackAnswer`, `FeedbackSubmissionIdentity`, `FeedbackIdentityRevealRequest` |
| Events | `Event`, `EventReservation`, `EventReservationException`, `EventCheckIn`, `EventAdmin` |
| Live quiz | `LiveQuiz`, `LiveQuizSession`, `LiveQuizQuestion`, `LiveQuizOption`, `LiveQuizParticipant`, `LiveQuizAnswer` |
| Projects | `Project`, `ProjectPhase`, `ProjectMembership`, `ProjectMembershipEvent`, `ProjectDeliverable`, `ProjectDeliverableSubmission`, `ProjectSubmissionFile`, `ProjectAssessment`, `ProjectGrade`, `ProjectGradeCriterion`, `ProjectPeerEvaluation`, `ProjectChangeRequest` |

---

## Money reminder

| Rule | Detail |
|---|---|
| Storage | Integer minors |
| Currencies | `Currency::Egp`, `Currency::Usd` |
| Display | `x-money` / formatters — never divide floats in Blade |
| Wallet | Separate EGP money, USD money, EGP points, USD points |

Related: [backend.md](backend.md), [feature-flows.md](feature-flows.md).
