<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportPopulation;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportMappingProfile;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportStudentFields;
use App\Services\Import\ImportTransformService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function index(): View
    {
        $sources = ImportSource::query()->orderBy('precedence')->get();
        $batches = ImportBatch::query()->with(['source', 'createdBy'])->latest()->paginate(20);

        return view('admin.imports.index', [
            'sources' => $sources,
            'batches' => $batches,
            'stats' => [
                'students_imported' => \App\Models\ImportLink::query()->where('entity_type', 'user')->count(),
                'pending_batches' => ImportBatch::query()->whereIn('status', [ImportBatchStatus::Draft, ImportBatchStatus::Mapped, ImportBatchStatus::Validated])->count(),
                'committed_batches' => ImportBatch::query()->where('status', ImportBatchStatus::Committed)->count(),
                'pending_merges' => \App\Models\ImportMergeCandidate::query()->where('status', \App\Enums\ImportMergeCandidateStatus::Pending)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.imports.create', [
            'sources' => ImportSource::query()->where('active', true)->orderBy('precedence')->get(),
        ]);
    }

    public function store(Request $request, ImportBatchService $imports, AuthorizeService $authorize): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'exists:import_sources,id'],
            'population' => ['required', 'in:ALUMNI,ACTIVE'],
            'sheet_name' => ['nullable', 'string', 'max:120'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:20'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        $population = ImportPopulation::from($data['population']);

        // A batch that will create login-capable accounts is a bigger action than a
        // historical import — require users.manage in addition to import.stage.
        if ($population === ImportPopulation::Active) {
            $authorize->authorize($request->user(), 'users.manage');
        }

        $source = ImportSource::query()->findOrFail($data['source_id']);

        $hash = hash('sha256', file_get_contents($request->file('file')->getRealPath()));
        $duplicate = $imports->findDuplicateHash($source, $hash);

        $batch = $imports->createFromUpload(
            $request->user(),
            $source,
            $request->file('file'),
            $population,
            $data['sheet_name'] ?? null,
            (int) ($data['header_row'] ?? 1),
        );

        $redirect = redirect()->route('admin.imports.map', $batch);
        if ($duplicate !== null) {
            $redirect->with('warning', __('import.duplicate_file_warning', ['batch' => $duplicate->id]));
        }

        return $redirect->with('status', __('import.upload_success', ['count' => $batch->row_count]));
    }

    public function map(ImportBatch $batch): View
    {
        $mapping = $batch->mapping ?? [];

        return view('admin.imports.map', [
            'batch' => $batch,
            'fields' => ImportStudentFields::catalog(),
            'required' => ImportStudentFields::requiredFor($batch->population),
            'transforms' => (new ImportTransformService)->available(),
            'profiles' => ImportMappingProfile::query()
                ->where('source_id', $batch->source_id)
                ->where('entity_type', $batch->entity_type)
                ->orderBy('name')
                ->get(),
            'missingFields' => $this->missingRequiredFields($mapping, $batch),
            'unresolved' => collect($mapping)->contains(fn ($m) => empty($m['target_field']) && empty($m['ignored'])),
        ]);
    }

    public function updateMap(Request $request, ImportBatch $batch, ImportBatchService $imports): RedirectResponse
    {
        $data = $request->validate([
            'columns' => ['required', 'array'],
            'target_field' => ['required', 'array'],
            'transform' => ['required', 'array'],
            'options' => ['sometimes', 'array'],
            'ignored' => ['sometimes', 'array'],
        ]);

        $validTargets = array_keys(ImportStudentFields::catalog());
        $previousConfidence = collect($batch->mapping ?? [])->keyBy('column')->map(fn ($m) => $m['confidence'] ?? 'None');

        $mapping = [];
        foreach ($data['columns'] as $i => $column) {
            $target = $data['target_field'][$i] ?? '';
            $ignored = in_array((string) $i, $data['ignored'] ?? [], true) || in_array($i, $data['ignored'] ?? [], true);

            $mapping[] = [
                'column' => $column,
                'target_field' => ($target !== '' && in_array($target, $validTargets, true)) ? $target : null,
                'transform' => $data['transform'][$i] ?? 'none',
                'options' => $this->decodeOptions($data['options'][$i] ?? null),
                'ignored' => $ignored,
                'confidence' => $previousConfidence->get($column, 'None'),
            ];
        }

        $missing = $this->missingRequiredFields($mapping, $batch);
        $unresolved = collect($mapping)->contains(fn ($m) => empty($m['target_field']) && empty($m['ignored']));

        $imports->updateMapping($batch, $mapping);

        if ($missing !== [] || $unresolved) {
            return redirect()->route('admin.imports.map', $batch);
        }

        $imports->validate($batch->fresh());

        return redirect()->route('admin.imports.show', $batch)->with('status', __('import.validated', ['count' => $batch->row_count]));
    }

    public function applyProfile(Request $request, ImportBatch $batch, ImportBatchService $imports): RedirectResponse
    {
        $data = $request->validate(['profile_id' => ['required', 'exists:import_mapping_profiles,id']]);
        $profile = ImportMappingProfile::query()->findOrFail($data['profile_id']);

        $imports->updateMapping($batch, $profile->mappings ?? []);

        return redirect()->route('admin.imports.map', $batch)->with('status', __('import.profile_loaded', ['name' => $profile->name]));
    }

    public function saveProfile(Request $request, ImportBatch $batch, ImportBatchService $imports): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $imports->saveAsProfile($batch, $request->user(), $data['name']);

        return redirect()->route('admin.imports.map', $batch)->with('status', __('import.profile_saved'));
    }

    public function show(ImportBatch $batch): View
    {
        $batch->load(['source', 'createdBy', 'committedBy', 'rolledBackBy']);

        $issues = ImportRow::query()->where('batch_id', $batch->id)
            ->whereNotNull('messages')
            ->get()
            ->flatMap(fn (ImportRow $row) => collect($row->messages ?? [])->map(fn ($m) => array_merge($m, ['row' => $row->row_number])))
            ->groupBy('code');

        return view('admin.imports.show', [
            'batch' => $batch,
            'issues' => $issues,
            'validCount' => ImportRow::query()->where('batch_id', $batch->id)->whereIn('status', ['VALID', 'WARN'])->count(),
            'errorRows' => ImportRow::query()->where('batch_id', $batch->id)->where('status', 'ERROR')->count(),
            'warnRows' => ImportRow::query()->where('batch_id', $batch->id)->where('status', 'WARN')->count(),
            'validRows' => ImportRow::query()->where('batch_id', $batch->id)->where('status', 'VALID')->count(),
        ]);
    }

    public function dryRunReport(ImportBatch $batch): View
    {
        return view('admin.imports.dry-run', ['batch' => $batch]);
    }

    public function runDryRun(ImportBatch $batch, ImportBatchService $imports): RedirectResponse
    {
        $imports->dryRun($batch->fresh());

        return redirect()->route('admin.imports.dry-run', $batch);
    }

    public function commit(Request $request, ImportBatch $batch, ImportBatchService $imports, AuthorizeService $authorize): RedirectResponse
    {
        if ($batch->population === ImportPopulation::Active) {
            $authorize->authorize($request->user(), 'users.manage');
        }

        try {
            $imports->commit($request->user(), $batch);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.imports.show', $batch)->with('status', __('import.committed'));
    }

    public function rollback(Request $request, ImportBatch $batch, ImportBatchService $imports): RedirectResponse
    {
        try {
            $result = $imports->rollback($request->user(), $batch);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['blocked'] !== []) {
            return redirect()->route('admin.imports.show', $batch)
                ->with('warning', __('import.rollback_partial', ['rolled_back' => $result['rolled_back'], 'blocked' => count($result['blocked'])]))
                ->with('rollback_blocked', $result['blocked']);
        }

        return redirect()->route('admin.imports.show', $batch)->with('status', __('import.rolled_back', ['count' => $result['rolled_back']]));
    }

    /**
     * @param  array<int, array{column: string, target_field: ?string, transform: string, options: array<string, mixed>, ignored: bool}>  $mapping
     * @return array<int, string>
     */
    private function missingRequiredFields(array $mapping, ImportBatch $batch): array
    {
        $produced = collect($mapping)->pluck('target_field')->filter()->unique()->all();
        $required = ImportStudentFields::requiredFor($batch->population);

        return array_values(array_diff($required, $produced));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOptions(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
