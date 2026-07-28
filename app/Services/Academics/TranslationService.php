<?php

namespace App\Services\Academics;

use App\Enums\TranslationSource;
use App\Models\Translation;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function upsert(User $actor, string $entityType, string $entityId, string $field, string $locale, string $value, bool $verified = false): Translation
    {
        $this->authorize->authorize($actor, 'translations.manage');

        $translation = Translation::query()->updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field' => $field,
                'locale' => $locale,
            ],
            [
                'value' => $value,
                'source' => TranslationSource::Human,
                'verified' => $verified,
                'updated_by_id' => $actor->id,
            ]
        );

        $this->audit->write($actor, 'translations.upsert', 'Translation', $translation->id);

        return $translation;
    }

    /**
     * AI translation stub — degrades gracefully when GOOGLE_API_KEY is missing.
     */
    public function requestAiTranslation(User $actor, string $entityType, string $entityId, string $field, string $sourceLocale, string $targetLocale, string $sourceText): ?Translation
    {
        $this->authorize->authorize($actor, 'translations.manage');

        if (empty(config('services.gemini.key'))) {
            Log::info('AI translation skipped — GOOGLE_API_KEY not configured', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field' => $field,
                'target' => $targetLocale,
            ]);

            return null;
        }

        // Placeholder: real Gemini call lands in a later hardening pass.
        $translated = "[AI:{$targetLocale}] ".$sourceText;

        return Translation::query()->updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field' => $field,
                'locale' => $targetLocale,
            ],
            [
                'value' => $translated,
                'source' => TranslationSource::Ai,
                'verified' => false,
                'updated_by_id' => $actor->id,
            ]
        );
    }

    public function verify(User $actor, Translation $translation): Translation
    {
        $this->authorize->authorize($actor, 'translations.manage');
        $translation->update(['verified' => true, 'updated_by_id' => $actor->id]);
        $this->audit->write($actor, 'translations.verify', 'Translation', $translation->id);

        return $translation->fresh();
    }
}
