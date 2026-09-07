<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\SuperAdmin\SchoolReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchoolReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hub_lists_required_reports_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.reports'))
            ->assertOk()
            ->assertSee(__('school_reports.page_lead'))
            ->assertSee(__('school_reports.page_help'))
            ->assertSee(__('school_reports.danger_title'))
            ->assertSee(__('school_reports.money_title'))
            ->assertSee(__('school_reports.range_help'))
            ->assertSee(__('school_reports.reports.census.title'))
            ->assertSee(__('school_reports.reports.finance.title'))
            ->assertSee(__('school_reports.reports.audit.title'))
            ->assertSee(__('school_reports.reports.census.hint'))
            ->assertSee(__('school_reports.snapshot_badge'))
            ->assertSee(__('school_reports.mixed_badge'))
            ->assertSee(route('superadmin.reports.show', 'census'), false)
            ->assertSee(route('superadmin.reports.csv', 'finance'), false);

        $this->actingAs($student)->get(route('superadmin.reports'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.reports'))->assertForbidden();
        $this->actingAs($student)->get(route('superadmin.reports.show', 'census'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.reports.csv', 'finance'))->assertForbidden();
    }

    #[Test]
    public function census_counts_match_factory_users(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        User::factory()->count(3)->withRole(RoleType::Student)->create([
            'preferred_locale' => 'ar',
            'status' => UserStatus::Active,
        ]);
        User::factory()->count(2)->withRole(RoleType::Instructor)->create([
            'preferred_locale' => 'fr',
            'status' => UserStatus::Active,
        ]);

        $people = User::query()->whereNull('deleted_at')->count();
        $arStudents = (int) User::query()
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->where('user_roles.role', RoleType::Student)
            ->where('users.status', UserStatus::Active)
            ->where('users.preferred_locale', 'ar')
            ->whereNull('users.deleted_at')
            ->selectRaw('COUNT(DISTINCT users.id) as total')
            ->value('total');

        $this->actingAs($sa)->get(route('superadmin.reports.show', 'census'))
            ->assertOk()
            ->assertSee(__('school_reports.census_people'))
            ->assertSee(__('school_reports.census_note_snapshot'))
            ->assertSee((string) $people)
            ->assertSeeInOrder([
                RoleType::Student->value,
                UserStatus::Active->value,
                'ar',
                (string) $arStudents,
            ])
            ->assertSeeInOrder([
                RoleType::Instructor->value,
                UserStatus::Active->value,
                'fr',
                '2',
            ]);
    }

    #[Test]
    public function finance_totals_are_integers_and_date_range_filters_payments(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 9011,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->addWeek(),
        ]);

        $paidInvoice = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 20000,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->addWeek(),
        ]);

        $inRange = Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $paidInvoice->id,
            'currency' => Currency::Usd,
            'amount_minor' => 8022,
            'method' => PaymentMethod::ManualTransfer,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'R-SA5-IN',
        ]);
        $inRange->forceFill(['created_at' => Carbon::parse('2026-09-05 12:00:00')])->save();

        $outOfRange = Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $paidInvoice->id,
            'currency' => Currency::Usd,
            'amount_minor' => 7033,
            'method' => PaymentMethod::ManualCash,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'R-SA5-OUT',
        ]);
        $outOfRange->forceFill(['created_at' => Carbon::parse('2026-01-15 12:00:00')])->save();

        $this->actingAs($sa)
            ->get(route('superadmin.reports.show', [
                'report' => 'finance',
                'from' => '2026-09-01',
                'to' => '2026-09-07',
            ]))
            ->assertOk()
            ->assertSee(__('school_reports.finance_note_integers'))
            ->assertSee(__('school_reports.col_outstanding_minor'))
            ->assertSee('USD 139.56', false)
            ->assertSee('USD 80.22', false)
            ->assertSee('13956')
            ->assertSee('8022')
            ->assertDontSee('7033')
            ->assertDontSee('USD 70.33', false);

        $service = app(SchoolReportService::class);
        $range = $service->resolveRange('2026-09-01', '2026-09-07');
        $payload = $service->payload('finance', $range['from'], $range['to']);
        $minors = [];
        foreach ($payload['summary'] as $tile) {
            $this->assertArrayHasKey('minor', $tile);
            $this->assertIsInt($tile['minor']);
            $minors[] = $tile['minor'];
        }
        $this->assertContains(9011 + (20000 - 8022 - 7033), $minors);
        $this->assertContains(8022, $minors);
        $this->assertNotContains(7033, $minors);
    }

    #[Test]
    public function csv_starts_with_utf8_bom_and_is_audited(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $csv = $this->actingAs($sa)
            ->get(route('superadmin.reports.csv', 'census'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(__('school_reports.col_role'), $csv);
        $this->assertStringContainsString(__('school_reports.col_count'), $csv);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reports.school.export',
            'actor_id' => $sa->id,
            'entity_id' => null,
        ]);
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'reports.school.export')
                ->where('actor_id', $sa->id)
                ->whereNull('entity_id')
                ->exists()
        );

        $sa->update(['preferred_locale' => 'ar']);
        $arCsv = $this->actingAs($sa)
            ->get(route('superadmin.reports.csv', 'census'))
            ->assertOk()
            ->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $arCsv);
        $this->assertStringContainsString(__('school_reports.col_count', [], 'ar'), $arCsv);
    }

    #[Test]
    public function unknown_report_is_404_and_bad_range_is_validated(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('superadmin.reports.show', 'not-a-report'))
            ->assertNotFound();
        $this->actingAs($sa)->get(route('superadmin.reports.csv', 'not-a-report'))
            ->assertNotFound();

        $this->actingAs($sa)
            ->from(route('superadmin.reports'))
            ->get(route('superadmin.reports', ['from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertRedirect()
            ->assertSessionHasErrors('from');

        $this->actingAs($sa)
            ->from(route('superadmin.reports'))
            ->get(route('superadmin.reports', ['from' => 'not-a-date', 'to' => '2026-09-07']))
            ->assertRedirect()
            ->assertSessionHasErrors('from');

        $this->actingAs($sa)
            ->from(route('superadmin.reports'))
            ->get(route('superadmin.reports', ['from' => '2020-01-01', 'to' => '2026-09-07']))
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }

    #[Test]
    public function entrances_are_visible_from_existing_pages(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('school_reports.dashboard_tile'))
            ->assertSee(__('school_reports.dashboard_tile_hint'))
            ->assertSee(route('superadmin.reports'), false);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('school_reports.dashboard_tile'));

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_reports'))
            ->assertSee(__('superadmin.tile_reports_hint'))
            ->assertSee(__('superadmin.roadmap_sa5_done'))
            ->assertSee(route('superadmin.reports'), false);

        $this->actingAs($sa)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee(__('school_reports.entrance_title'))
            ->assertSee(__('school_reports.entrance_from_reports'))
            ->assertSee(route('superadmin.reports'), false);

        $this->actingAs($sa)->get(route('admin.finance.reports'))
            ->assertOk()
            ->assertSee(__('school_reports.entrance_title'))
            ->assertSee(__('school_reports.entrance_from_finance'));
    }
}
