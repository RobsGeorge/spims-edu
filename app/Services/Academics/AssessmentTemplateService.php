<?php

namespace App\Services\Academics;

use App\Enums\ComponentKind;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateComponent;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class AssessmentTemplateService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function create(User $actor, array $data): AssessmentTemplate
    {
        $this->authorize->authorize($actor, 'assessment_templates.manage');

        $components = $data['components'] ?? [];
        $totalWeight = collect($components)->sum(fn ($c) => (float) ($c['weight_percent'] ?? 0));

        if ($components !== [] && abs($totalWeight - 100) > 0.01) {
            throw ValidationException::withMessages([
                'components' => [__('academics.weights_must_sum_100')],
            ]);
        }

        return $this->audit->withAudit($actor, 'assessment_templates.create', function () use ($data, $components) {
            if (! empty($data['is_default'])) {
                AssessmentTemplate::query()->update(['is_default' => false]);
            }

            $template = AssessmentTemplate::query()->create([
                'name' => $data['name'],
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            foreach ($components as $component) {
                AssessmentTemplateComponent::query()->create([
                    'template_id' => $template->id,
                    'name' => $component['name'],
                    'weight_percent' => $component['weight_percent'],
                    'kind' => ComponentKind::from($component['kind']),
                ]);
            }

            return $template->load('components');
        }, 'AssessmentTemplate');
    }
}
