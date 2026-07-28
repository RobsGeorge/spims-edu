<?php

namespace App\Services\Admissions;

use App\Enums\FormFieldType;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Program;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;

class ApplicationFormService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function create(User $actor, Program $program, array $data): ApplicationForm
    {
        $this->authorize->authorize($actor, 'admissions.forms');

        return $this->audit->withAudit($actor, 'admissions.form_create', function () use ($program, $data) {
            $form = ApplicationForm::query()->create([
                'program_id' => $program->id,
                'name' => $data['name'],
                'active' => $data['active'] ?? true,
            ]);

            foreach ($data['fields'] ?? [] as $index => $field) {
                ApplicationFormField::query()->create([
                    'form_id' => $form->id,
                    'label' => $field['label'],
                    'type' => FormFieldType::from($field['type']),
                    'required' => (bool) ($field['required'] ?? false),
                    'order' => $field['order'] ?? ($index + 1),
                    'options' => $field['options'] ?? [],
                    'allowed_file_types' => $field['allowed_file_types'] ?? [],
                    'admin_note' => $field['admin_note'] ?? null,
                ]);
            }

            return $form->load('fields');
        }, 'ApplicationForm');
    }
}
