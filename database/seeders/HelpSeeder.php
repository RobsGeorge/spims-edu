<?php

namespace Database\Seeders;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'shared' => 10,
            'student' => 20,
            'instructor' => 30,
            'administrative' => 40,
            'academic' => 50,
            'finance' => 60,
            'superadmin' => 70,
        ];

        $categoryIds = [];
        foreach ($categories as $slug => $sort) {
            $categoryIds[$slug] = HelpCategory::query()->updateOrCreate(
                ['slug' => $slug],
                ['sort_order' => $sort, 'is_published' => true]
            )->id;
        }

        $sort = 0;
        foreach ($this->articles() as $article) {
            $sort += 10;
            $model = HelpArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'category_id' => $categoryIds[$article['category']],
                    'status' => HelpArticleStatus::Published,
                    'sort_order' => $sort,
                    'is_public' => (bool) ($article['is_public'] ?? false),
                    'published_at' => now(),
                ]
            );

            foreach ($article['locales'] as $locale => $payload) {
                HelpArticleLocale::query()->updateOrCreate(
                    [
                        'article_id' => $model->id,
                        'locale' => $locale,
                    ],
                    [
                        'title' => $payload['title'],
                        'summary' => $payload['summary'],
                        'body_markdown' => $payload['body'],
                    ]
                );
            }

            HelpArticleAudience::query()->where('article_id', $model->id)->delete();
            foreach ($article['audiences'] as $role) {
                HelpArticleAudience::query()->create([
                    'article_id' => $model->id,
                    'role' => $role,
                ]);
            }
        }
    }

    /**
     * @return list<array{
     *   slug: string,
     *   category: string,
     *   audiences: list<RoleType>,
     *   is_public?: bool,
     *   locales: array<string, array{title: string, summary: string, body: string}>
     * }>
     */
    private function articles(): array
    {
        return array_merge(
            $this->sharedArticles(),
            $this->studentArticles(),
            $this->instructorArticles(),
            $this->operatorArticles(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function sharedArticles(): array
    {
        return [
            $this->article(
                'hubs-explained',
                'shared',
                [],
                true,
                'Hubs explained',
                'Which hub to open for learning, teaching, academics, admin, and finance.',
                "## Hubs\n\nSPIMS organizes work into hubs: Learning, Teach, Academic, Admin, and Finance.\n\nOpen the hub that matches your task. Dual-role users see more than one hub tile.",
                'شرح المراكز',
                'أي مركز تفتحه للتعلم أو التدريس أو الإدارة أو المالية.',
                "## المراكز\n\nتنظّم SPIMS العمل في مراكز: التعلم، التدريس، الأكاديمي، الإدارة، والمالية.",
                'Les hubs expliqués',
                'Quel hub ouvrir pour apprendre, enseigner, administrer ou gérer les finances.',
                "## Hubs\n\nSPIMS organise le travail en hubs : Apprentissage, Enseignement, Académique, Admin et Finance."
            ),
            $this->article(
                'theme-preference',
                'shared',
                [],
                true,
                'Light, dark, and system theme',
                'How to switch appearance preferences in your profile settings.',
                "## Theme\n\nOpen **Settings** and choose Light, Dark, or System.\n\nSystem follows your device preference.",
                'الوضع الفاتح والداكن والنظام',
                'كيفية تغيير مظهر الواجهة من الإعدادات.',
                "## المظهر\n\nافتح **الإعدادات** واختر فاتح أو داكن أو حسب النظام.",
                'Thème clair, sombre et système',
                'Changer l’apparence dans les paramètres du profil.',
                "## Thème\n\nOuvrez **Paramètres** et choisissez Clair, Sombre ou Système."
            ),
            $this->article(
                'switching-dual-roles',
                'shared',
                [],
                false,
                'Switching dual roles',
                'How SPIMS behaves when your account has more than one role.',
                "## Dual roles\n\nPermissions from all assigned roles apply together.\n\nUse the matching hub for each kind of work (for example Teach vs Learning).",
                'التبديل بين الأدوار المزدوجة',
                'كيف يعمل الحساب عند امتلاك أكثر من دور.',
                "## أدوار مزدوجة\n\nتُجمع صلاحيات كل الأدوار المعينة. استخدم المركز المناسب لكل مهمة.",
                'Basculer entre plusieurs rôles',
                'Comportement du compte avec plusieurs rôles.',
                "## Rôles multiples\n\nLes permissions de tous les rôles s’additionnent. Utilisez le hub adapté à chaque tâche."
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function studentArticles(): array
    {
        $student = [RoleType::Student];

        return [
            $this->article(
                'getting-started',
                'student',
                $student,
                false,
                'Getting started',
                'First steps after you create a SPIMS account.',
                "## Welcome\n\n1. Verify your email and set a password.\n2. Complete your profile and preferred language.\n3. Open the Learning hub to browse the catalog or apply.",
                'البداية',
                'الخطوات الأولى بعد إنشاء حساب SPIMS.',
                "## مرحباً\n\n1. أكّد بريدك وعيّن كلمة مرور.\n2. أكمل ملفك واللغة المفضلة.\n3. افتح مركز التعلم لتصفح الكتالوج أو التقديم.",
                'Premiers pas',
                'Premières étapes après la création d’un compte SPIMS.',
                "## Bienvenue\n\n1. Vérifiez l’e-mail et définissez un mot de passe.\n2. Complétez le profil et la langue.\n3. Ouvrez le hub Apprentissage pour le catalogue ou les candidatures."
            ),
            $this->article(
                'apply-admissions',
                'student',
                $student,
                false,
                'Apply and admissions',
                'How to submit an application and track its status.',
                "## Admissions\n\nOpen **Applications**, choose an open form, and submit required fields and documents.\n\nYou will see pending, accepted, or other statuses on your applications list.",
                'التقديم والقبول',
                'كيفية تقديم طلب ومتابعة حالته.',
                "## القبول\n\nافتح **الطلبات**، اختر نموذجاً مفتوحاً، وأرسل الحقول والمستندات المطلوبة.",
                'Candidature et admissions',
                'Soumettre une candidature et suivre son statut.',
                "## Admissions\n\nOuvrez **Candidatures**, choisissez un formulaire ouvert et envoyez les champs et documents requis."
            ),
            $this->article(
                'browse-catalog-enroll',
                'student',
                $student,
                false,
                'Browse catalog and enroll',
                'Find offerings and register when enrollment is open.',
                "## Catalog & enrollment\n\nUse **Catalog** to browse courses. Flag interest if available.\n\nFrom Learning hub enrollments, register when the offering is open and requirements are met.",
                'تصفح الكتالوج والتسجيل',
                'العثور على العروض والتسجيل عند فتح التسجيل.',
                "## الكتالوج والتسجيل\n\nاستخدم **الكتالوج** لتصفح المقررات. سجّل اهتمامك إن وُجد، ثم سجّل عند فتح العرض.",
                'Catalogue et inscription',
                'Trouver des offres et s’inscrire quand l’inscription est ouverte.',
                "## Catalogue\n\nUtilisez **Catalogue** pour parcourir les cours. Inscrivez-vous depuis le hub Apprentissage quand l’offre est ouverte."
            ),
            $this->article(
                'course-player-progress',
                'student',
                $student,
                false,
                'Course player and progress',
                'Navigate weeks, content items, and completion.',
                "## Learning player\n\nOpen an enrolled offering to see weeks and items.\n\nMark items complete as you go; progress appears on the offering overview.",
                'مشغّل المقرر والتقدّم',
                'التنقل بين الأسابيع والمحتوى وإكمال العناصر.',
                "## مشغّل التعلم\n\nافتح العرض المسجّل لرؤية الأسابيع والعناصر، وأكمل العناصر أثناء التقدّم.",
                'Lecteur de cours et progression',
                'Naviguer dans les semaines, contenus et achèvement.',
                "## Lecteur\n\nOuvrez une offre inscrite pour voir semaines et éléments. Marquez-les comme terminés au fur et à mesure."
            ),
            $this->article(
                'exams-assignments',
                'student',
                $student,
                false,
                'Exams and assignments',
                'Start attempts, submit work, and respect timers.',
                "## Assessments\n\nOpen the assessment or assignment from your course.\n\nSave answers often; submit before the deadline. Focus-loss rules may apply during exams.",
                'الاختبارات والواجبات',
                'بدء المحاولات وتسليم العمل واحترام المؤقتات.',
                "## التقييمات\n\nافتح الاختبار أو الواجب من مقررك. احفظ إجاباتك وسلّم قبل الموعد النهائي.",
                'Examens et devoirs',
                'Démarrer une tentative, remettre un travail et respecter les délais.',
                "## Évaluations\n\nOuvrez l’examen ou le devoir depuis le cours. Enregistrez souvent et soumettez avant l’échéance."
            ),
            $this->article(
                'grades-transcript',
                'student',
                $student,
                false,
                'Grades and transcript',
                'Where to view grades and download academic records.',
                "## Grades\n\nOpen **Grades** for offering results when released.\n\n**Transcript** shows completed academic records for your programs.",
                'الدرجات والسجل الأكاديمي',
                'أين ترى الدرجات والسجلات الأكاديمية.',
                "## الدرجات\n\nافتح **الدرجات** لنتائج العروض عند نشرها، و**السجل** للسجلات المكتملة.",
                'Notes et relevé',
                'Consulter les notes et le relevé académique.',
                "## Notes\n\nOuvrez **Notes** pour les résultats publiés et **Relevé** pour le parcours académique."
            ),
            $this->article(
                'pay-invoices-wallet-donate',
                'student',
                $student,
                false,
                'Pay invoices, wallet, and donate',
                'Checkout invoices, understand wallet balances, and make donations.',
                "## Finance\n\nOpen **Finance** for invoices and payments.\n\nAmounts are stored in minor units (cents/piastres). Use Donate for voluntary gifts when enabled.",
                'دفع الفواتير والمحفظة والتبرع',
                'دفع الفواتير وفهم المحفظة والتبرعات.',
                "## المالية\n\nافتح **المالية** للفواتير والمدفوعات. المبالغ بوحدة صغرى. استخدم التبرع عند تفعيله.",
                'Factures, portefeuille et dons',
                'Payer les factures, comprendre le portefeuille et donner.',
                "## Finance\n\nOuvrez **Finance** pour factures et paiements. Les montants sont en unités mineures. Utilisez Don si activé."
            ),
            $this->article(
                'profile-language',
                'student',
                $student,
                false,
                'Profile and language',
                'Update profile fields and switch en / ar / fr.',
                "## Profile\n\nOpen **Settings** to edit profile details, notification email, and preferred locale.\n\nArabic uses RTL layout automatically.",
                'الملف الشخصي واللغة',
                'تحديث الملف والتبديل بين الإنجليزية والعربية والفرنسية.',
                "## الملف\n\nافتح **الإعدادات** لتعديل البيانات واللغة. العربية تستخدم تخطيط RTL تلقائياً.",
                'Profil et langue',
                'Mettre à jour le profil et changer en / ar / fr.',
                "## Profil\n\nOuvrez **Paramètres** pour le profil et la locale. L’arabe active automatiquement le RTL."
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function instructorArticles(): array
    {
        $roles = [RoleType::Instructor, RoleType::Ta];

        return [
            $this->article(
                'teach-workspace-overview',
                'instructor',
                $roles,
                false,
                'Teach workspace overview',
                'Navigate offerings you staff and common teaching tasks.',
                "## Teach hub\n\nOpen **Teach** to see offerings where you are instructor or TA.\n\nFrom an offering you can manage announcements, content, attendance, assessments, and discussions.",
                'نظرة عامة على مساحة التدريس',
                'التنقل في العروض التي تشرف عليها ومهام التدريس.',
                "## مركز التدريس\n\nافتح **التدريس** لرؤية العروض التي تشرف عليها كمعلّم أو مساعد.",
                'Espace Enseignement',
                'Parcourir les offres dont vous êtes responsable.',
                "## Hub Enseignement\n\nOuvrez **Enseignement** pour vos offres en tant qu’instructeur ou assistant."
            ),
            $this->article(
                'roster-attendance',
                'instructor',
                $roles,
                false,
                'Roster and attendance',
                'View enrolled students and record attendance.',
                "## Roster & attendance\n\nOpen the offering roster to see enrollments.\n\nRecord attendance for live or scheduled sessions from the attendance tools.",
                'القائمة والحضور',
                'عرض الطلاب المسجّلين وتسجيل الحضور.',
                "## القائمة والحضور\n\nافتح قائمة العرض لرؤية التسجيلات وسجّل الحضور للجلسات.",
                'Liste et présence',
                'Voir les inscrits et enregistrer la présence.',
                "## Présence\n\nOuvrez la liste de l’offre pour les inscriptions et enregistrez la présence aux sessions."
            ),
            $this->article(
                'content-live-sessions',
                'instructor',
                $roles,
                false,
                'Content and live sessions',
                'Publish weeks/items and schedule live sessions.',
                "## Content & live\n\nOrganize weeks and content items for learners.\n\nSchedule live sessions and share join links when the session is open.",
                'المحتوى والجلسات المباشرة',
                'نشر الأسابيع والعناصر وجدولة الجلسات المباشرة.',
                "## المحتوى والبث\n\nنظّم الأسابيع والعناصر للمتعلمين، ثم اجدول الجلسات المباشرة.",
                'Contenu et sessions live',
                'Publier le contenu et planifier les sessions live.',
                "## Contenu\n\nOrganisez semaines et éléments, puis planifiez les sessions live et partagez le lien de rejoindre."
            ),
            $this->article(
                'banks-assessments',
                'instructor',
                $roles,
                false,
                'Banks and assessments',
                'Reuse question banks and configure assessments.',
                "## Banks & assessments\n\nBuild or reuse question banks, then attach questions to assessments.\n\nChoose draw or fixed sets according to the assessment mode.",
                'بنوك الأسئلة والتقييمات',
                'إعادة استخدام البنوك وإعداد التقييمات.',
                "## البنوك والتقييمات\n\nابنِ أو أعد استخدام بنوك الأسئلة ثم اربطها بالتقييمات.",
                'Banques et évaluations',
                'Réutiliser les banques et configurer les évaluations.',
                "## Banques\n\nCréez ou réutilisez des banques, puis attachez des questions aux évaluations."
            ),
            $this->article(
                'gradebook-guide',
                'instructor',
                $roles,
                false,
                'Gradebook',
                'Configure components, enter grades, and lock when ready.',
                "## Gradebook\n\nConfigure gradebook components and weights for the offering.\n\nEnter grades, then lock when results are final (reopen requires academic admin).",
                'دفتر الدرجات',
                'تهيئة المكوّنات وإدخال الدرجات والقفل.',
                "## دفتر الدرجات\n\nهيّئ المكوّنات والأوزان، أدخل الدرجات، ثم اقفل عند الاعتماد النهائي.",
                'Carnet de notes',
                'Configurer les composantes, saisir et verrouiller.',
                "## Carnet\n\nConfigurez composantes et pondérations, saisissez les notes, puis verrouillez."
            ),
            $this->article(
                'discussions-guide',
                'instructor',
                $roles,
                false,
                'Discussions',
                'Moderate threads and grade discussion participation.',
                "## Discussions\n\nOpen the offering discussion board to create threads and moderate posts.\n\nGrade participation when discussion grading is enabled.",
                'المناقشات',
                'إدارة المواضيع وتقييم المشاركة.',
                "## المناقشات\n\nافتح لوحة نقاش العرض لإنشاء المواضيع والإشراف على المنشورات.",
                'Discussions',
                'Modérer les fils et noter la participation.',
                "## Discussions\n\nOuvrez le forum de l’offre pour créer des fils, modérer et noter si activé."
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function operatorArticles(): array
    {
        return [
            $this->article(
                'users-roles',
                'administrative',
                [RoleType::AdministrativeAdmin],
                false,
                'Users and roles',
                'Create users, assign roles, and suspend accounts.',
                "## Users & roles\n\nFrom Admin hub, manage users and assign non–super-admin roles.\n\nSuspension blocks sign-in while preserving audit history.",
                'المستخدمون والأدوار',
                'إنشاء المستخدمين وتعيين الأدوار وتعليق الحسابات.',
                "## المستخدمون والأدوار\n\nمن مركز الإدارة أدر المستخدمين وعيّن الأدوار. التعليق يمنع تسجيل الدخول.",
                'Utilisateurs et rôles',
                'Créer des utilisateurs, assigner des rôles et suspendre.',
                "## Utilisateurs\n\nDepuis le hub Admin, gérez les utilisateurs et les rôles (hors super-admin)."
            ),
            $this->article(
                'application-queue',
                'administrative',
                [RoleType::AdministrativeAdmin],
                false,
                'Application queue',
                'Review and decide on student applications.',
                "## Application queue\n\nOpen the admissions queue to review submissions, request changes, accept, or reject.\n\nDecisions are audited.",
                'قائمة طلبات القبول',
                'مراجعة طلبات الطلاب واتخاذ القرار.',
                "## قائمة الطلبات\n\nراجع الطلبات واقبل أو ارفض. القرارات تُسجَّل في التدقيق.",
                'File des candidatures',
                'Examiner et décider sur les candidatures.',
                "## File\n\nExaminez les dossiers, acceptez ou refusez. Les décisions sont auditées."
            ),
            $this->article(
                'theme-branding',
                'administrative',
                [RoleType::AdministrativeAdmin],
                false,
                'Theme and branding',
                'Update site name, logos, and design tokens.',
                "## Theme editor\n\nAdmin → Theme lets you set site name, logos, and Sacred Academic tokens.\n\nOnly one theme is active at a time.",
                'السمة والهوية البصرية',
                'تحديث اسم الموقع والشعارات ورموز التصميم.',
                "## محرر السمة\n\nالإدارة ← السمة لتعديل الاسم والشعارات والرموز. سمة واحدة نشطة فقط.",
                'Thème et identité',
                'Mettre à jour le nom, logos et jetons de design.',
                "## Éditeur de thème\n\nAdmin → Thème pour le nom, logos et jetons. Un seul thème actif."
            ),
            $this->article(
                'semesters-guide',
                'administrative',
                [RoleType::AdministrativeAdmin],
                false,
                'Semesters',
                'Create and manage academic semesters.',
                "## Semesters\n\nDefine academic years and semesters that offerings attach to.\n\nKeep date ranges accurate for enrollment windows.",
                'الفصول الدراسية',
                'إنشاء وإدارة الفصول الأكاديمية.',
                "## الفصول\n\nعرّف السنوات والفصول التي ترتبط بها العروض.",
                'Semestres',
                'Créer et gérer les semestres académiques.',
                "## Semestres\n\nDéfinissez années et semestres auxquels les offres se rattachent."
            ),
            $this->article(
                'programs-courses',
                'academic',
                [RoleType::AcademicAdmin],
                false,
                'Programs and courses',
                'Build programs, courses, and prerequisites.',
                "## Programs & courses\n\nCreate programs and attach courses with requirements.\n\nSet prerequisites so enrollment gating stays accurate.",
                'البرامج والمقررات',
                'بناء البرامج والمقررات والمتطلبات السابقة.',
                "## البرامج والمقررات\n\nأنشئ البرامج واربط المقررات والمتطلبات السابقة.",
                'Programmes et cours',
                'Construire programmes, cours et prérequis.',
                "## Programmes\n\nCréez programmes et cours, et définissez les prérequis."
            ),
            $this->article(
                'offerings-pricing-gating',
                'academic',
                [RoleType::AcademicAdmin],
                false,
                'Offerings, pricing, and gating',
                'Open offerings, set pricing, and control access.',
                "## Offerings\n\nCreate offerings per semester, set mode/status, and configure pricing with finance.\n\nContent gating controls what learners see before payment or progress.",
                'العروض والتسعير والوصول',
                'فتح العروض وضبط التسعير والتحكم في الوصول.',
                "## العروض\n\nأنشئ عروضاً لكل فصل واضبط الحالة والتسعير مع المالية.",
                'Offres, tarifs et accès',
                'Ouvrir des offres, tarifer et contrôler l’accès.',
                "## Offres\n\nCréez des offres par semestre, définissez statut et tarification avec la finance."
            ),
            $this->article(
                'templates-grading-schemes',
                'academic',
                [RoleType::AcademicAdmin],
                false,
                'Templates and grading schemes',
                'Configure assessment templates and letter schemes.',
                "## Templates & schemes\n\nAssessment templates define reusable component sets.\n\nGrading schemes map numeric scores to letter bands.",
                'القوالب وأنظمة التقييم',
                'تهيئة قوالب التقييم وأنظمة الحروف.',
                "## القوالب والأنظمة\n\nالقوالب تعرّف مكوّنات قابلة لإعادة الاستخدام، والأنظمة تربط الدرجات بالحروف.",
                'Modèles et barèmes',
                'Configurer modèles d’évaluation et barèmes.',
                "## Modèles\n\nLes modèles définissent des composantes réutilisables ; les barèmes mappent scores et lettres."
            ),
            $this->article(
                'credentials-translations',
                'academic',
                [RoleType::AcademicAdmin],
                false,
                'Credentials and translations',
                'Issue credentials and manage translation strings.',
                "## Credentials & i18n\n\nIssue certificates or transcripts from the credentials tools.\n\nManage translation entries for course content locales when needed.",
                'الشهادات والترجمات',
                'إصدار الشهادات وإدارة نصوص الترجمة.',
                "## الشهادات والترجمة\n\nأصدر الشهادات من أدوات الاعتماد، وأدر ترجمات المحتوى عند الحاجة.",
                'Attestations et traductions',
                'Émettre des attestations et gérer les traductions.',
                "## Attestations\n\nÉmettez certificats/relevés et gérez les traductions de contenu si besoin."
            ),
            $this->article(
                'invoices-payments',
                'finance',
                [RoleType::FinancialAdmin],
                false,
                'Invoices and payments',
                'Monitor invoices and payment status.',
                "## Invoices & payments\n\nFinance hub lists invoices and payment attempts.\n\nAmounts are integer minor units — never enter decimal currency floats.",
                'الفواتير والمدفوعات',
                'متابعة الفواتير وحالة الدفع.',
                "## الفواتير والمدفوعات\n\nمركز المالية يعرض الفواتير والمحاولات. المبالغ وحدات صغرى صحيحة.",
                'Factures et paiements',
                'Suivre factures et statuts de paiement.',
                "## Factures\n\nLe hub Finance liste factures et tentatives. Montants en unités mineures entières."
            ),
            $this->article(
                'manual-verify',
                'finance',
                [RoleType::FinancialAdmin],
                false,
                'Manual payment verify',
                'Verify offline or manual payments safely.',
                "## Manual verify\n\nUse manual verification only with supporting evidence.\n\nEvery verification writes an audit log entry.",
                'التحقق اليدوي من الدفع',
                'التحقق الآمن من المدفوعات اليدوية.',
                "## التحقق اليدوي\n\nتحقق يدوياً فقط مع إثبات. كل تحقق يُسجَّل في التدقيق.",
                'Vérification manuelle',
                'Valider les paiements manuels en toute sécurité.',
                "## Vérification\n\nVérifiez manuellement uniquement avec preuve. Chaque action est auditée."
            ),
            $this->article(
                'refunds-reports',
                'finance',
                [RoleType::FinancialAdmin],
                false,
                'Refunds and reports',
                'Process refunds and review finance reports.',
                "## Refunds\n\nIssue refunds from the finance tools when policy allows.\n\nWallet and ledger movements stay in minor units.",
                'المبالغ المستردة والتقارير',
                'معالجة الاسترداد ومراجعة تقارير المالية.',
                "## الاسترداد\n\nأصدر الاسترداد وفق السياسة. حركات المحفظة بوحدة صغرى.",
                'Remboursements et rapports',
                'Traiter les remboursements et consulter les rapports.',
                "## Remboursements\n\nÉmettez des remboursements selon la politique. Le ledger reste en unités mineures."
            ),
            $this->article(
                'minor-units-explained',
                'finance',
                [RoleType::FinancialAdmin],
                false,
                'Minor units explained',
                'Why money is stored as integers (cents/piastres).',
                "## Minor units\n\nSPIMS stores money as integer minor units (e.g. 1000 = 10.00).\n\nNever use floating-point amounts in forms or APIs.",
                'شرح الوحدات الصغرى',
                'لماذا تُخزَّن الأموال كأعداد صحيحة.',
                "## الوحدات الصغرى\n\nتخزّن SPIMS الأموال كأعداد صحيحة (مثلاً 1000 = 10.00). لا تستخدم الفاصلة العائمة.",
                'Unités mineures',
                'Pourquoi l’argent est stocké en entiers.',
                "## Unités mineures\n\nSPIMS stocke l’argent en entiers (ex. 1000 = 10,00). Jamais de flottants."
            ),
            $this->article(
                'control-plane',
                'superadmin',
                [RoleType::SuperAdmin],
                false,
                'Control plane',
                'Super Admin overview of platform controls.',
                "## Control plane\n\nSuper Admin tools cover security flush, audit, observability, scheduled tasks, and system tests.\n\nUse with care — several actions are irreversible for sessions.",
                'لوحة التحكم',
                'نظرة عامة لأدوات المشرف الأعلى.',
                "## لوحة التحكم\n\nأدوات المشرف الأعلى تشمل الأمان والتدقيق والمراقبة والمهام والاختبارات.",
                'Plan de contrôle',
                'Vue d’ensemble des contrôles Super Admin.',
                "## Plan de contrôle\n\nOutils Super Admin : sécurité, audit, observabilité, tâches planifiées et tests système."
            ),
            $this->article(
                'roles-hub-permissions',
                'superadmin',
                [RoleType::SuperAdmin],
                false,
                'Roles Hub permissions',
                'Edit the permission matrix for portal roles.',
                "## Roles Hub\n\nAdjust permission levels per role from the Roles Hub.\n\n`help.manage` is limited to Administrative Admin (and Super Admin bypass).",
                'صلاحيات مركز الأدوار',
                'تعديل مصفوفة الصلاحيات لأدوار البوابة.',
                "## مركز الأدوار\n\nعدّل مستويات الصلاحيات لكل دور. `help.manage` للإدارة الإدارية (والمشرف الأعلى).",
                'Permissions du Roles Hub',
                'Modifier la matrice des permissions.',
                "## Roles Hub\n\nAjustez les niveaux par rôle. `help.manage` est réservé à l’admin administratif (et Super Admin)."
            ),
            $this->article(
                'audit-security-flush',
                'superadmin',
                [RoleType::SuperAdmin],
                false,
                'Audit and security flush',
                'Review audit logs and flush sessions when needed.',
                "## Audit & security\n\nBrowse audit logs for sensitive mutations.\n\nSession flush signs out active sessions — communicate before using in production.",
                'التدقيق وتفريغ الجلسات',
                'مراجعة سجلات التدقيق وتفريغ الجلسات عند الحاجة.',
                "## التدقيق والأمان\n\nراجع سجلات التدقيق. تفريغ الجلسات يسجّل خروج الجميع — أخبر المستخدمين أولاً.",
                'Audit et purge de sessions',
                'Consulter l’audit et purger les sessions.',
                "## Audit\n\nParcourez les journaux d’audit. La purge déconnecte les sessions actives."
            ),
            $this->article(
                'observability-scheduled-tasks',
                'superadmin',
                [RoleType::SuperAdmin],
                false,
                'Observability and scheduled tasks',
                'Check health signals and scheduled job status.',
                "## Ops\n\nObservability pages summarize queue/mail/health signals.\n\nScheduled tasks lists expected cron/scheduler entries for the platform.",
                'المراقبة والمهام المجدولة',
                'فحص إشارات الصحة وحالة المهام المجدولة.',
                "## التشغيل\n\nصفحات المراقبة تلخّص الطوابير والبريد والصحة. المهام المجدولة تعرض إدخالات الجدولة.",
                'Observabilité et tâches planifiées',
                'Vérifier santé et tâches planifiées.',
                "## Ops\n\nLes pages d’observabilité résument files, mail et santé. Les tâches listent le scheduler."
            ),
            $this->article(
                'system-tests',
                'superadmin',
                [RoleType::SuperAdmin],
                false,
                'System tests',
                'Run or review platform system test entry points.',
                "## System tests\n\nUse the system tests screen for operator smoke checks.\n\nAutomated CI remains the release gate — this UI is operational convenience.",
                'اختبارات النظام',
                'تشغيل أو مراجعة نقاط اختبار النظام.',
                "## اختبارات النظام\n\nاستخدم شاشة الاختبارات لفحوص تشغيلية. بوابة الإصدار تبقى عبر CI.",
                'Tests système',
                'Lancer ou revoir les points de test système.',
                "## Tests système\n\nÉcran de smoke opérationnel. La gate de release reste le CI."
            ),
        ];
    }

    /**
     * @param  list<RoleType>  $audiences
     * @return array<string, mixed>
     */
    private function article(
        string $slug,
        string $category,
        array $audiences,
        bool $isPublic,
        string $enTitle,
        string $enSummary,
        string $enBody,
        string $arTitle,
        string $arSummary,
        string $arBody,
        string $frTitle,
        string $frSummary,
        string $frBody,
    ): array {
        return [
            'slug' => $slug,
            'category' => $category,
            'audiences' => $audiences,
            'is_public' => $isPublic,
            'locales' => [
                'en' => ['title' => $enTitle, 'summary' => $enSummary, 'body' => $enBody],
                'ar' => ['title' => $arTitle, 'summary' => $arSummary, 'body' => $arBody],
                'fr' => ['title' => $frTitle, 'summary' => $frSummary, 'body' => $frBody],
            ],
        ];
    }
}
