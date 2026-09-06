<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Services\Admissions\ApplicationService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly StudentRecordGuard $guard,
    ) {}

    public function form(ApplicationForm $applicationForm): JsonResponse
    {
        if (! $applicationForm->active) {
            abort(404);
        }

        $applicationForm->load(['fields', 'program']);

        return response()->json([
            'data' => [
                'id' => $applicationForm->id,
                'name' => $applicationForm->name,
                'program_id' => $applicationForm->program_id,
                'program_code' => $applicationForm->program?->code,
                'fields' => $applicationForm->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'required' => $field->required,
                    'order' => $field->order,
                    'options' => $field->options,
                    'allowed_file_types' => $field->allowed_file_types,
                ])->values(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = Application::query()
            ->where('applicant_id', $request->user()->id)
            ->with('program')
            ->latest()
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (Application $application) => $this->payload($application, false)));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_id' => 'required|exists:application_forms,id',
            'answers' => 'nullable|array',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $form = ApplicationForm::query()->with('fields', 'program')->findOrFail($data['form_id']);
        if (! $form->active) {
            abort(404);
        }

        $application = $this->applications->start($request->user(), $form);

        if (! empty($data['answers']) || $request->hasFile('files')) {
            $this->applications->saveAnswers(
                $request->user(),
                $application,
                $data['answers'] ?? [],
                $request->file('files', []) ?? []
            );
        }

        return response()->json(['data' => $this->payload($application->fresh(['program', 'values.field', 'form.fields']), true)], 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->guard->ownRead($request->user(), $application->applicant_id);

        return response()->json([
            'data' => $this->payload($application->load(['program', 'values.field', 'form.fields']), true),
        ]);
    }

    public function submit(Request $request, Application $application): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $application->applicant_id);
        $submitted = $this->applications->submit($request->user(), $application->load('form.fields', 'values'));

        return response()->json(['data' => $this->payload($submitted->load(['program', 'values.field']), true)]);
    }

    /** @return array<string, mixed> */
    private function payload(Application $application, bool $detail): array
    {
        $data = [
            'id' => $application->id,
            'program_id' => $application->program_id,
            'program_code' => $application->program?->code,
            'form_id' => $application->form_id,
            'status' => $application->status->value,
            'submitted_at' => StudentPayload::iso($application->submitted_at),
            'decided_at' => StudentPayload::iso($application->decided_at),
            'decision_note' => $application->decision_note,
        ];

        if ($detail) {
            $data['values'] = $application->values->map(fn ($value) => [
                'field_id' => $value->field_id,
                'label' => $value->field?->label,
                'value' => $value->value,
            ])->values();
        }

        return $data;
    }
}
