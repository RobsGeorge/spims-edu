<?php

namespace App\Services\Credentials;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Credential;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;

/**
 * Resolves and renders certificate templates for issued credentials.
 *
 * Placeholder syntax mirrors EmailTemplateService's `{{name}}` convention:
 * `{{student_name}}`, `{{course_title}}`, `{{issued_at}}`, `{{serial}}`.
 */
class CertificateTemplateService
{
    /** @var list<string> */
    public const PLACEHOLDERS = [
        'student_name',
        'course_title',
        'issued_at',
        'serial',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * Course-specific template for the locale, else the global template for
     * the locale, else null — caller falls back to a hardcoded default.
     */
    public function resolve(?string $courseId, string $locale): ?CertificateTemplate
    {
        if ($courseId !== null) {
            $scoped = CertificateTemplate::query()
                ->where('course_id', $courseId)
                ->where('locale', $locale)
                ->first();

            if ($scoped !== null) {
                return $scoped;
            }
        }

        return CertificateTemplate::query()
            ->whereNull('course_id')
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Resolve a template (course beats global) for the credential's locale,
     * falling back to English and then to a hardcoded default, substitute its
     * placeholders, and return standalone HTML ready either to display directly
     * or to feed into DomPDF's loadHTML().
     */
    public function render(Credential $credential): string
    {
        $credential->loadMissing(['student', 'offering.course', 'program']);

        $locale = in_array($credential->language, ['ar', 'en', 'fr'], true) ? $credential->language : 'en';
        $courseId = $credential->offering?->course_id;

        $template = $this->resolve($courseId, $locale);
        if ($template === null && $locale !== 'en') {
            $template = $this->resolve($courseId, 'en');
        }

        $variables = [
            'student_name' => trim(($credential->student->first_name ?? '').' '.($credential->student->last_name ?? '')),
            'course_title' => $credential->offering?->course?->title ?? $credential->program?->name ?? '',
            'issued_at' => $credential->issued_at?->toDateString() ?? now()->toDateString(),
            'serial' => $credential->serial,
        ];

        $title = $this->substitute(
            $template?->title ?? __('credentials.certificate_default_title', [], $locale),
            $variables
        );
        $body = $this->substitute(
            $template?->body ?? __('credentials.certificate_default_body', [], $locale),
            $variables
        );

        return view('credentials.certificate-document', [
            'title' => $title,
            'body' => $body,
            'credential' => $credential,
            'template' => $template,
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
        ])->render();
    }

    /**
     * A sample rendering with placeholder text, so an admin can preview a
     * template's layout without issuing a real credential first.
     */
    public function previewRender(?string $courseId, string $locale): string
    {
        $template = $this->resolve($courseId, $locale);
        if ($template === null && $locale !== 'en') {
            $template = $this->resolve($courseId, 'en');
        }

        $variables = [
            'student_name' => __('credentials.student'),
            'course_title' => $courseId !== null ? ((string) (Course::query()->find($courseId)?->title)) : '',
            'issued_at' => now()->toDateString(),
            'serial' => 'SPIMS-CRED-PREVIEW-00000',
        ];

        $title = $this->substitute(
            $template?->title ?? __('credentials.certificate_default_title', [], $locale),
            $variables
        );
        $body = $this->substitute(
            $template?->body ?? __('credentials.certificate_default_body', [], $locale),
            $variables
        );

        return view('credentials.certificate-document', [
            'title' => $title,
            'body' => $body,
            'credential' => null,
            'template' => $template,
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'previewSerial' => $variables['serial'],
            'previewIssuedAt' => $variables['issued_at'],
        ])->render();
    }

    /**
     * @param  array{course_id?: ?string, locale: string, title: string, body: string, background_path?: ?string, signature_path?: ?string}  $data
     */
    public function upsert(User $actor, array $data): CertificateTemplate
    {
        $courseId = $data['course_id'] ?? null;
        $resource = $courseId !== null ? Course::query()->find($courseId) : null;

        $this->authorize->authorize($actor, 'certificate_templates.manage', $resource);

        return $this->audit->withAudit($actor, 'certificate_template.upsert', function () use ($data, $courseId) {
            $query = CertificateTemplate::query()->where('locale', $data['locale']);
            $query = $courseId === null ? $query->whereNull('course_id') : $query->where('course_id', $courseId);
            $template = $query->first();

            $payload = [
                'course_id' => $courseId,
                'locale' => $data['locale'],
                'title' => $data['title'],
                'body' => $data['body'],
                'background_path' => $data['background_path'] ?? null,
                'signature_path' => $data['signature_path'] ?? null,
            ];

            if ($template === null) {
                return CertificateTemplate::query()->create($payload);
            }

            $template->update($payload);

            return $template->fresh();
        }, CertificateTemplate::class);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function substitute(string $text, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($text, $replacements);
    }
}
