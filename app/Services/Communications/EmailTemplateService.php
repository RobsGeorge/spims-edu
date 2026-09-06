<?php

namespace App\Services\Communications;

use App\Models\CourseOffering;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class EmailTemplateService
{
    /** @var list<string> */
    public const ALLOWED_VARIABLES = [
        'name',
        'first_name',
        'last_name',
        'email',
        'title',
        'body',
        'course',
        'offering',
        'url',
        'locale',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array{subject: string, body: string, source: string}
     */
    public function resolve(string $key, string $locale, ?CourseOffering $offering = null): array
    {
        $courseId = $offering?->course_id;

        $scoped = $this->findTemplate($key, $locale, 'course', $courseId)
            ?? ($offering !== null ? $this->findTemplate($key, $locale, 'offering', $offering->id) : null);

        if ($scoped !== null) {
            return ['subject' => $scoped->subject, 'body' => $scoped->body, 'source' => 'course'];
        }

        $fallbackLocale = $this->fallbackLocale($locale);
        if ($fallbackLocale !== $locale) {
            $scopedFallback = $this->findTemplate($key, $fallbackLocale, 'course', $courseId)
                ?? ($offering !== null ? $this->findTemplate($key, $fallbackLocale, 'offering', $offering->id) : null);
            if ($scopedFallback !== null) {
                return ['subject' => $scopedFallback->subject, 'body' => $scopedFallback->body, 'source' => 'course'];
            }
        }

        $global = $this->findTemplate($key, $locale, null, null)
            ?? ($fallbackLocale !== $locale ? $this->findTemplate($key, $fallbackLocale, null, null) : null);

        if ($global !== null) {
            return ['subject' => $global->subject, 'body' => $global->body, 'source' => 'global'];
        }

        $subject = $this->langLine($key, 'subject', $locale);
        $body = $this->langLine($key, 'body', $locale);

        return ['subject' => $subject, 'body' => $body, 'source' => 'lang'];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, array $variables): array
    {
        $this->assertAllowlist($subject.' '.$body);
        $unknown = array_diff(array_keys($variables), self::ALLOWED_VARIABLES);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'variables' => [__('communications.unknown_variables', ['vars' => implode(', ', $unknown)])],
            ]);
        }

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{{'.$name.'}}'] = (string) $value;
        }

        return [
            'subject' => strtr($subject, $replacements),
            'body' => strtr($body, $replacements),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body: string, source: string}
     */
    public function preview(
        User $actor,
        string $key,
        string $locale,
        array $variables,
        ?CourseOffering $offering = null,
    ): array {
        $this->authorize->authorize($actor, 'email_templates.manage', $offering);

        $resolved = $this->resolve($key, $locale, $offering);
        $rendered = $this->render($resolved['subject'], $resolved['body'], $variables);

        return $rendered + ['source' => $resolved['source']];
    }

    /**
     * @param  array{key: string, locale: string, subject: string, body: string, scope_type?: ?string, scope_id?: ?string}  $data
     */
    public function upsert(User $actor, array $data, ?CourseOffering $offering = null): EmailTemplate
    {
        $scopeType = $data['scope_type'] ?? ($offering !== null ? 'offering' : null);
        $scopeId = $data['scope_id'] ?? $offering?->id;
        $resource = $offering ?? $this->resourceForScope($scopeType, $scopeId);

        $this->authorize->authorize($actor, 'email_templates.manage', $resource);
        $this->assertAllowlist($data['subject'].' '.$data['body']);

        return $this->audit->withAudit($actor, 'email_template.upsert', function () use ($actor, $data, $scopeType, $scopeId) {
            $query = EmailTemplate::query()
                ->where('key', $data['key'])
                ->where('locale', $data['locale']);

            if ($scopeType === null) {
                $query->whereNull('scope_type')->whereNull('scope_id');
            } else {
                $query->where('scope_type', $scopeType)->where('scope_id', $scopeId);
            }

            $template = $query->first();
            $payload = [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'key' => $data['key'],
                'locale' => $data['locale'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'updated_by_id' => $actor->id,
            ];

            if ($template === null) {
                return EmailTemplate::query()->create($payload);
            }

            $template->update($payload);

            return $template->fresh();
        }, EmailTemplate::class);
    }

    private function findTemplate(string $key, string $locale, ?string $scopeType, ?string $scopeId): ?EmailTemplate
    {
        $query = EmailTemplate::query()->where('key', $key)->where('locale', $locale);

        if ($scopeType === null) {
            $query->whereNull('scope_type')->whereNull('scope_id');
        } else {
            if ($scopeId === null) {
                return null;
            }
            $query->where('scope_type', $scopeType)->where('scope_id', $scopeId);
        }

        return $query->first();
    }

    private function fallbackLocale(string $locale): string
    {
        return $locale === 'en' ? 'en' : 'en';
    }

    private function langLine(string $key, string $field, string $locale): string
    {
        $group = trans('communications.templates', [], $locale);
        if (is_array($group) && isset($group[$key][$field]) && is_string($group[$key][$field])) {
            return $group[$key][$field];
        }

        $fallback = trans('communications.templates', [], 'en');
        if (is_array($fallback) && isset($fallback[$key][$field]) && is_string($fallback[$key][$field])) {
            return $fallback[$key][$field];
        }

        return $key.'.'.$field;
    }

    private function assertAllowlist(string $text): void
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);
        $used = array_unique($matches[1] ?? []);
        $unknown = array_diff($used, self::ALLOWED_VARIABLES);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'variables' => [__('communications.unknown_variables', ['vars' => implode(', ', $unknown)])],
            ]);
        }
    }

    private function resourceForScope(?string $scopeType, ?string $scopeId): mixed
    {
        if ($scopeType === 'offering' && $scopeId !== null) {
            return CourseOffering::query()->find($scopeId);
        }

        if ($scopeType === 'course' && $scopeId !== null) {
            return \App\Models\Course::query()->find($scopeId);
        }

        return null;
    }
}
