<?php

namespace Tests\Feature\Audit;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function with_audit_writes_log_on_mutation(): void
    {
        $user = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $this->actingAs($user);

        $response = $this->postJson(route('foundation.demo'), ['note' => 'audit test']);

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'foundation.demo.mutate',
            'actor_id' => $user->id,
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    #[Test]
    public function audit_writer_stores_request_metadata(): void
    {
        $user = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->withHeader('X-Request-Id', 'test-request-123')
            ->actingAs($user)
            ->postJson(route('foundation.demo'), ['note' => 'meta']);

        $log = AuditLog::query()->first();
        $this->assertSame('test-request-123', $log->request_id);
        $this->assertNotNull($log->ip);
    }
}
