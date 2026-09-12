<?php

namespace Tests\Feature\Import;

use App\Enums\ImportMergeCandidateStatus;
use App\Enums\ImportPopulation;
use App\Enums\RoleType;
use App\Models\ImportMergeCandidate;
use App\Models\ImportSource;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * L2 — the rest of the identity matching ladder (rungs 2, 4, 5) and the human merge
 * queue (rung 6). Rungs 1 and 3 are covered by ImportBatchServiceTest already; this
 * file must never touch that one. See docs/legacy-data-import-plan.md §6, §11.10.
 */
class ImportIdentityLadderTest extends TestCase
{
    use RefreshDatabase;

    private function populi(): ImportSource
    {
        return ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);
    }

    private function canvas(): ImportSource
    {
        return ImportSource::query()->create([
            'code' => 'CANVAS',
            'name' => 'Canvas',
            'kind' => 'LMS',
            'precedence' => 2,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);
    }

    /**
     * A distinctively named actor so it never accidentally becomes the merge queue's
     * "best guess" for an unrelated row in these tests (the ladder's similarity search
     * scans every user, and the actor is one).
     */
    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'first_name' => 'Zzq',
            'last_name' => 'Wvx',
        ]);
    }

    private function csv(string $content, string $name = 'rows.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function identityMapping(): array
    {
        return [
            ['column' => 'ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'first_name', 'transform' => 'name_part_first', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'last_name', 'transform' => 'name_part_last', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Email', 'target_field' => 'email', 'transform' => 'lower', 'options' => [], 'ignored' => false],
        ];
    }

    // --- Rung 2: the Canvas / SIS crosswalk ---------------------------------------

    #[Test]
    public function canvas_links_to_the_populi_created_user_via_the_sis_user_id_crosswalk(): void
    {
        $populi = $this->populi();
        $canvas = $this->canvas();
        $actor = $this->admin();
        $service = $this->service();

        $populiCsv = $this->csv("ID,Name,Email\n5551,\"Iskander, Peter\",peter@example.org\n");
        $populiBatch = $service->createFromUpload($actor, $populi, $populiCsv, ImportPopulation::Alumni);
        $populiBatch = $service->updateMapping($populiBatch, $this->identityMapping());
        $populiBatch = $service->validate($populiBatch);
        $service->commit($actor, $populiBatch->fresh());

        $peter = User::query()->where('email', 'peter@example.org')->firstOrFail();

        // Canvas's "SIS User ID" is mapped to legacy_id, and carries the same Populi
        // person id. A different (stale) Canvas email must not create a second user.
        $canvasCsv = $this->csv("SIS User ID,Name,Email\n5551,\"Iskander, Peter\",peter.canvas@example.org\n");
        $canvasMapping = [
            ['column' => 'SIS User ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'first_name', 'transform' => 'name_part_first', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'last_name', 'transform' => 'name_part_last', 'options' => ['format' => 'last_first'], 'ignored' => false],
        ];
        $canvasBatch = $service->createFromUpload($actor, $canvas, $canvasCsv, ImportPopulation::Alumni);
        $canvasBatch = $service->updateMapping($canvasBatch, $canvasMapping);
        $canvasBatch = $service->validate($canvasBatch);
        $service->commit($actor, $canvasBatch->fresh());

        $this->assertSame(1, User::query()->where('first_name', 'Peter')->count());
        $this->assertDatabaseMissing('users', ['email' => 'peter.canvas@example.org']);
        $this->assertDatabaseHas('import_links', [
            'source_id' => $canvas->id,
            'entity_type' => 'user',
            'legacy_id' => '5551',
            'target_id' => $peter->id,
        ]);

        // A later re-import of the same Canvas file now hits rung 1 directly.
        $canvasBatch2 = $service->createFromUpload($actor, $canvas, $this->csv("SIS User ID,Name,Email\n5551,\"Iskander, Peter\",peter.canvas@example.org\n"), ImportPopulation::Alumni);
        $canvasBatch2 = $service->updateMapping($canvasBatch2, $canvasMapping);
        $canvasBatch2 = $service->validate($canvasBatch2);
        $service->commit($actor, $canvasBatch2->fresh());
        $this->assertSame(1, User::query()->where('first_name', 'Peter')->count());
    }

    #[Test]
    public function a_canvas_batch_is_refused_until_a_populi_student_batch_has_committed(): void
    {
        $canvas = $this->canvas();
        $actor = $this->admin();
        $service = $this->service();

        $mapping = [
            ['column' => 'SIS User ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'first_name', 'transform' => 'name_part_first', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'last_name', 'transform' => 'name_part_last', 'options' => ['format' => 'last_first'], 'ignored' => false],
        ];

        $batch = $service->createFromUpload($actor, $canvas, $this->csv("SIS User ID,Name\n9001,\"Person, Early\"\n"), ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);

        try {
            $service->commit($actor, $batch->fresh());
            $this->fail('Expected a RuntimeException — no Populi STUDENT batch has committed yet.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('import.error_code.E_CANVAS_BEFORE_POPULI'), $e->getMessage());
        }

        $this->assertSame('VALIDATED', $batch->fresh()->status->value);
    }

    // --- Rung 4: legacy student number ---------------------------------------------

    #[Test]
    public function an_unambiguous_student_number_match_links_instead_of_creating_a_duplicate(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $existing = User::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'email' => 'existing.person@example.org',
            'student_number' => 'SN-4471',
        ]);

        $csv = $this->csv("ID,Name,Email,StudentNo\n7001,\"Different, Name\",different@example.org,SN-4471\n");
        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'StudentNo', 'target_field' => 'student_number', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseMissing('users', ['email' => 'different@example.org']);
        $this->assertDatabaseHas('import_links', [
            'source_id' => $source->id,
            'legacy_id' => '7001',
            'target_id' => $existing->id,
        ]);
    }

    #[Test]
    public function an_ambiguous_student_number_does_not_auto_link(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        User::factory()->create(['first_name' => 'One', 'last_name' => 'Holder', 'email' => 'one@example.org', 'student_number' => 'SN-DUP']);
        User::factory()->create(['first_name' => 'Two', 'last_name' => 'Holder', 'email' => 'two@example.org', 'student_number' => 'SN-DUP']);

        $csv = $this->csv("ID,Name,Email,StudentNo\n7101,\"Nomatch, Person\",nomatch.dup@example.org,SN-DUP\n");
        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'StudentNo', 'target_field' => 'student_number', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseMissing('users', ['email' => 'nomatch.dup@example.org']);
        $this->assertNotNull(ImportMergeCandidate::query()->where('legacy_id', '7101')->first());
    }

    // --- Rung 5: normalised (first, last, date_of_birth) triple --------------------

    #[Test]
    public function a_unique_dob_and_name_triple_auto_links(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $existing = User::factory()->create([
            'first_name' => 'Mina',
            'last_name' => 'Boutros',
            'email' => 'mina.existing@example.org',
            'date_of_birth' => '1994-03-14',
        ]);

        $csv = $this->csv("ID,Name,Email,DOB\n8001,\"Boutros, Mina\",mina.new@example.org,14/03/1994\n");
        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'DOB', 'target_field' => 'date_of_birth', 'transform' => 'date', 'options' => ['format' => 'd/m/Y'], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseMissing('users', ['email' => 'mina.new@example.org']);
        $this->assertDatabaseHas('import_links', [
            'source_id' => $source->id,
            'legacy_id' => '8001',
            'target_id' => $existing->id,
        ]);
    }

    #[Test]
    public function an_ambiguous_dob_and_name_triple_does_not_auto_link_and_is_queued_instead(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $twinA = User::factory()->create(['first_name' => 'Mina', 'last_name' => 'Boutros', 'email' => 'twinA@example.org', 'date_of_birth' => '1994-03-14']);
        $twinB = User::factory()->create(['first_name' => 'Mina', 'last_name' => 'Boutros', 'email' => 'twinB@example.org', 'date_of_birth' => '1994-03-14']);

        $csv = $this->csv("ID,Name,Email,DOB\n8101,\"Boutros, Mina\",mina.twin@example.org,14/03/1994\n");
        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'DOB', 'target_field' => 'date_of_birth', 'transform' => 'date', 'options' => ['format' => 'd/m/Y'], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseMissing('users', ['email' => 'mina.twin@example.org']);

        $candidate = ImportMergeCandidate::query()->where('legacy_id', '8101')->firstOrFail();
        $this->assertSame(ImportMergeCandidateStatus::Pending, $candidate->status);
        $this->assertContains($candidate->candidate_user_id, [$twinA->id, $twinB->id]);
        $this->assertEqualsCanonicalizing(['name', 'date_of_birth'], $candidate->matched_on);
        $this->assertSame(1.0, $candidate->score);
    }

    // --- Rung 6: the merge queue ----------------------------------------------------

    #[Test]
    public function an_exact_name_match_without_a_dob_is_queued_with_a_best_guess_candidate(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $existing = User::factory()->create(['first_name' => 'Salib', 'last_name' => 'Adel', 'email' => 'salib.existing@example.org']);

        $csv = $this->csv("ID,Name,Email\n9101,\"Adel, Salib\",salib.new@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseMissing('users', ['email' => 'salib.new@example.org']);

        $candidate = ImportMergeCandidate::query()->where('legacy_id', '9101')->firstOrFail();
        $this->assertSame($existing->id, $candidate->candidate_user_id);
        $this->assertSame(['name'], $candidate->matched_on);
        $this->assertSame(ImportMergeCandidateStatus::Pending, $candidate->status);
    }

    /**
     * The ordinary case for a first-time migration: a person nobody in SPIMS has any
     * record of at all. Rungs 1-5 cannot apply (there is nothing to match against) and
     * the looser rung-6 search finds no resemblance either, so the row is created
     * directly — exactly as it was before L2. Only genuine ambiguity (an escalation
     * from rung 4/5, or an actual name match/similarity hit) reaches the merge queue;
     * queuing every unmatched row would make the queue the main event for every import,
     * not the exception the plan's own worked example (17 of 1,284 rows) describes.
     */
    #[Test]
    public function a_row_with_no_resemblance_to_anyone_is_created_directly_not_queued(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n9401,\"Nomatch, Person\",nomatch@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseHas('users', ['email' => 'nomatch@example.org']);
        $this->assertNull(ImportMergeCandidate::query()->where('legacy_id', '9401')->first());

        $row = \App\Models\ImportRow::query()->where('batch_id', $batch->id)->where('natural_key', '9401')->firstOrFail();
        $this->assertSame('CREATE', $row->action->value);
        $this->assertSame('APPLIED', $row->status->value);
    }

    /**
     * Builds a merge-candidate row with no plausible candidate at all — the "no
     * candidate — create new?" case §11.10 and the L2 task both call out — by
     * validating (never committing) a batch and inserting the queue row directly, since
     * the ladder itself only ever escalates a row that carries a real signal (see the
     * test above). Exercises the merge screen's graceful null-candidate handling
     * independently of how such a row would realistically reach the queue (e.g. a
     * genuine ambiguity elsewhere in the same batch, or a future rung).
     */
    private function queuedCandidateWithNoMatch(ImportSource $source, User $actor, ImportBatchService $service, string $legacyId, string $email): ImportMergeCandidate
    {
        $csv = $this->csv("ID,Name,Email\n{$legacyId},\"Nomatch, Person\",{$email}\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $row = \App\Models\ImportRow::query()->where('batch_id', $batch->id)->where('natural_key', $legacyId)->firstOrFail();

        return ImportMergeCandidate::query()->create([
            'source_id' => $source->id,
            'legacy_id' => $legacyId,
            'batch_id' => $batch->id,
            'candidate_user_id' => null,
            'score' => 0,
            'matched_on' => [],
            'payload_preview' => $row->payload,
            'status' => ImportMergeCandidateStatus::Pending,
        ]);
    }

    #[Test]
    public function merging_a_candidate_links_to_the_existing_person_and_is_audited(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $existing = User::factory()->create(['first_name' => 'Salib', 'last_name' => 'Adel', 'email' => 'salib.existing2@example.org']);

        $csv = $this->csv("ID,Name,Email\n9501,\"Adel, Salib\",salib.new2@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $candidate = ImportMergeCandidate::query()->where('legacy_id', '9501')->firstOrFail();

        $resolved = $service->resolveMergeCandidate($actor, $candidate, 'merge');

        $this->assertSame(ImportMergeCandidateStatus::Merged, $resolved->status);
        $this->assertSame($actor->id, $resolved->resolved_by_id);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertDatabaseMissing('users', ['email' => 'salib.new2@example.org']);
        $this->assertDatabaseHas('import_links', [
            'source_id' => $source->id,
            'legacy_id' => '9501',
            'target_id' => $existing->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'import.merge_resolve']);

        $row = \App\Models\ImportRow::query()->where('batch_id', $batch->id)->where('natural_key', '9501')->firstOrFail();
        $this->assertSame('LINK', $row->action->value);
        $this->assertSame('APPLIED', $row->status->value);
    }

    #[Test]
    public function rejecting_a_candidate_creates_a_brand_new_person_and_is_audited(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $candidate = $this->queuedCandidateWithNoMatch($source, $actor, $service, '9601', 'nomatch.reject@example.org');
        $this->assertNull($candidate->candidate_user_id);

        $resolved = $service->resolveMergeCandidate($actor, $candidate, 'reject');

        $this->assertSame(ImportMergeCandidateStatus::NewUser, $resolved->status);
        $newUser = User::query()->where('email', 'nomatch.reject@example.org')->firstOrFail();
        $this->assertDatabaseHas('import_links', [
            'source_id' => $source->id,
            'legacy_id' => '9601',
            'target_id' => $newUser->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'import.merge_resolve']);
    }

    #[Test]
    public function skipping_a_candidate_leaves_it_pending_but_still_audits_the_decision(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $candidate = $this->queuedCandidateWithNoMatch($source, $actor, $service, '9701', 'nomatch.skip@example.org');

        $resolved = $service->resolveMergeCandidate($actor, $candidate, 'skip');

        $this->assertSame(ImportMergeCandidateStatus::Pending, $resolved->status);
        $this->assertNull($resolved->resolved_at);
        $this->assertDatabaseMissing('users', ['email' => 'nomatch.skip@example.org']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'import.merge_resolve']);
    }

    #[Test]
    public function merging_without_a_stored_candidate_is_refused(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $candidate = $this->queuedCandidateWithNoMatch($source, $actor, $service, '9801', 'nomatch.nocandidate@example.org');
        $this->assertNull($candidate->candidate_user_id);

        $this->expectException(RuntimeException::class);
        $service->resolveMergeCandidate($actor, $candidate, 'merge');
    }

    #[Test]
    public function resolving_an_already_resolved_candidate_is_refused(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $candidate = $this->queuedCandidateWithNoMatch($source, $actor, $service, '9901', 'nomatch.twice@example.org');
        $service->resolveMergeCandidate($actor, $candidate, 'skip');
        $resolved = $service->resolveMergeCandidate($actor, $candidate->fresh(), 'reject');
        $this->assertSame(ImportMergeCandidateStatus::NewUser, $resolved->status);

        $this->expectException(RuntimeException::class);
        $service->resolveMergeCandidate($actor, $resolved->fresh(), 'skip');
    }
}
