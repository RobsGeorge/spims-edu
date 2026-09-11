<?php

namespace App\Services\Import;

use App\Models\ImportGradeMapping;
use App\Models\ImportSource;
use App\Models\User;
use App\Support\AuditLogWriter;
use Illuminate\Support\Collection;

class ImportSourceService
{
    public function __construct(private readonly AuditLogWriter $audit) {}

    /**
     * @return Collection<int, ImportSource>
     */
    public function all(): Collection
    {
        return ImportSource::query()->orderBy('precedence')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(User $actor, ?ImportSource $source, array $data): ImportSource
    {
        return $this->audit->withAudit($actor, $source ? 'import.source_update' : 'import.source_create', function () use ($source, $data) {
            if ($source) {
                $source->update($data);

                return $source->fresh();
            }

            return ImportSource::query()->create($data);
        }, entityType: ImportSource::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertGradeMapping(User $actor, ImportSource $source, ?ImportGradeMapping $mapping, array $data): ImportGradeMapping
    {
        $data['source_id'] = $source->id;

        return $this->audit->withAudit($actor, $mapping ? 'import.grade_mapping_update' : 'import.grade_mapping_create', function () use ($mapping, $data) {
            if ($mapping) {
                $mapping->update($data);

                return $mapping->fresh();
            }

            return ImportGradeMapping::query()->create($data);
        }, entityType: ImportGradeMapping::class);
    }

    public function deleteGradeMapping(User $actor, ImportGradeMapping $mapping): void
    {
        $this->audit->withAudit($actor, 'import.grade_mapping_delete', function () use ($mapping) {
            $data = $mapping->toArray();
            $mapping->delete();

            return $data;
        });
    }
}
