<?php

namespace App\Services\Academics;

use App\Models\GradeBand;
use App\Models\GradingScheme;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;

class GradingSchemeService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function create(User $actor, array $data): GradingScheme
    {
        $this->authorize->authorize($actor, 'grading_schemes.manage');

        return $this->audit->withAudit($actor, 'grading_schemes.create', function () use ($data) {
            if (! empty($data['is_default'])) {
                GradingScheme::query()->update(['is_default' => false]);
            }

            $scheme = GradingScheme::query()->create([
                'name' => $data['name'],
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            foreach ($data['bands'] ?? [] as $band) {
                GradeBand::query()->create([
                    'scheme_id' => $scheme->id,
                    'letter' => $band['letter'],
                    'min_percent' => $band['min_percent'],
                    'max_percent' => $band['max_percent'],
                    'gpa_points' => $band['gpa_points'],
                    'is_passing' => (bool) ($band['is_passing'] ?? false),
                ]);
            }

            return $scheme->load('bands');
        }, 'GradingScheme');
    }

    public function updateBands(User $actor, GradingScheme $scheme, array $data): GradingScheme
    {
        $this->authorize->authorize($actor, 'grading_schemes.manage');

        return $this->audit->withAudit($actor, 'grading_schemes.update', function () use ($scheme, $data) {
            if (array_key_exists('name', $data) && $data['name'] !== null) {
                $scheme->name = $data['name'];
            }

            if (array_key_exists('is_default', $data)) {
                if (! empty($data['is_default'])) {
                    GradingScheme::query()->where('id', '!=', $scheme->id)->update(['is_default' => false]);
                }
                $scheme->is_default = (bool) $data['is_default'];
            }

            $scheme->save();

            if (isset($data['bands']) && is_array($data['bands'])) {
                $scheme->bands()->delete();
                foreach ($data['bands'] as $band) {
                    GradeBand::query()->create([
                        'scheme_id' => $scheme->id,
                        'letter' => $band['letter'],
                        'min_percent' => $band['min_percent'],
                        'max_percent' => $band['max_percent'],
                        'gpa_points' => $band['gpa_points'],
                        'is_passing' => (bool) ($band['is_passing'] ?? false),
                    ]);
                }
            }

            return $scheme->fresh('bands');
        }, 'GradingScheme');
    }
}
