<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use App\Services\SuperAdmin\IntegrationConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_save_mail_identities_and_gateways_without_leaking_secrets(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $secret = 'portal-paypal-secret-9f3a';

        $response = $this->actingAs($sa)
            ->from(route('superadmin.integrations'))
            ->put(route('superadmin.integrations.update'), [
                'safe' => [
                    'integrations.mail.transactional.from_address' => 'noreply@spims-edu.com',
                    'integrations.mail.transactional.from_name' => 'SPIMS OTP',
                    'integrations.mail.notifications.from_address' => 'notify@spims-edu.com',
                    'integrations.mail.notifications.from_name' => 'SPIMS School',
                    'integrations.paypal.enabled' => '1',
                    'integrations.paypal.mode' => 'sandbox',
                    'integrations.paypal.client_id' => 'paypal-public-client',
                    'integrations.paymob.enabled' => '1',
                    'integrations.paymob.integration_id' => '123456',
                    'integrations.cashier.enabled' => '1',
                ],
                'secrets' => [
                    'integrations.paypal.secret' => $secret,
                ],
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $config = app(IntegrationConfigService::class);
        $config->refresh();
        $this->assertSame('noreply@spims-edu.com', $config->fromAddress('transactional'));
        $this->assertSame('notify@spims-edu.com', $config->fromAddress('notifications'));
        $this->assertSame('paypal-public-client', $config->paypalClientId());
        $this->assertTrue($config->hasStoredSecret('integrations.paypal.secret'));
        $this->assertSame($secret, $config->secretValue('integrations.paypal.secret'));

        $row = Setting::query()->find('integrations.paypal.secret');
        $this->assertNotNull($row);
        $this->assertNotSame($secret, json_encode($row->value));
        $this->assertSame($secret, Crypt::decryptString($row->value['encrypted']));

        $html = $this->actingAs($sa)->get(route('superadmin.integrations'))
            ->assertOk()
            ->assertSee(__('integrations.page_lead'))
            ->assertSee(__('integrations.danger_title'))
            ->assertSee(__('integrations.never_title'))
            ->assertSee('noreply@spims-edu.com', false)
            ->assertSee('notify@spims-edu.com', false)
            ->assertSee('paypal-public-client', false)
            ->assertSee('name="secrets[integrations.paypal.secret]"', false)
            ->assertSee('type="password"', false)
            ->assertDontSee('name="MAIL_PASSWORD"', false)
            ->assertDontSee('name="PAYPAL_SECRET"', false)
            ->assertDontSee($secret, false)
            ->assertDontSee((string) env('SUPERADMIN_PASSWORD'), false)
            ->getContent();

        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString((string) $row->value['encrypted'], $html);

        $log = AuditLog::query()->where('action', 'integrations.update')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('changed', $log->after['integrations.paypal.secret'] ?? null);
        $this->assertSame('notify@spims-edu.com', $log->after['integrations.mail.notifications.from_address'] ?? null);
        $this->assertStringNotContainsString($secret, json_encode($log->after));
        $this->assertStringNotContainsString($secret, json_encode($log->before));
    }

    #[Test]
    public function unknown_integration_key_is_rejected_and_outsiders_are_forbidden(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)
            ->putJson(route('superadmin.integrations.update'), [
                'safe' => [
                    'integrations.stripe.secret' => 'nope',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->actingAs($student)->get(route('superadmin.integrations'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.integrations'))->assertForbidden();
        $this->actingAs($student)
            ->put(route('superadmin.integrations.update'), [
                'safe' => ['integrations.paypal.enabled' => '0'],
            ])
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('superadmin.integrations.test-mail'), ['identity' => 'transactional'])
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('superadmin.integrations.test-payment'), [
                'gateway' => 'PAYPAL',
                'confirm' => '1',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function test_mail_uses_the_chosen_identity_and_is_audited(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $transport = app('mailer')->getSymfonyTransport();
        $this->assertTrue(method_exists($transport, 'flush'));
        $transport->flush();

        $this->actingAs($sa)->put(route('superadmin.integrations.update'), [
            'safe' => [
                'integrations.mail.transactional.from_address' => 'noreply@spims-edu.com',
                'integrations.mail.notifications.from_address' => 'notify@spims-edu.com',
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($sa)
            ->from(route('superadmin.integrations'))
            ->post(route('superadmin.integrations.test-mail'), [
                'identity' => 'notifications',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $email = $messages->last()->getOriginalMessage();
        $from = $email->getFrom()[0]->getAddress();
        $this->assertSame('notify@spims-edu.com', $from);
        $this->assertSame($sa->email, $email->getTo()[0]->getAddress());
        $this->assertStringNotContainsString('Your verification code is:', $email->getTextBody());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'integrations.test_mail',
            'actor_id' => $sa->id,
        ]);
        $log = AuditLog::query()->where('action', 'integrations.test_mail')->latest('id')->first();
        $this->assertSame('notifications', $log->after['identity'] ?? null);
        $this->assertSame($sa->email, $log->after['to'] ?? null);
        $this->assertTrue((bool) ($log->after['ok'] ?? false));
        $this->assertStringNotContainsString('Your verification code is:', json_encode($log->after));
    }

    #[Test]
    public function test_payment_is_simulated_in_testing_and_requires_confirm(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        Http::fake();
        Http::preventStrayRequests();

        $this->actingAs($sa)
            ->from(route('superadmin.integrations'))
            ->post(route('superadmin.integrations.test-payment'), [
                'gateway' => 'PAYPAL',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirm');

        $response = $this->actingAs($sa)
            ->from(route('superadmin.integrations'))
            ->post(route('superadmin.integrations.test-payment'), [
                'gateway' => 'PAYPAL',
                'confirm' => '1',
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        Http::assertNothingSent();

        $log = AuditLog::query()->where('action', 'integrations.test_payment')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('PAYPAL', $log->after['gateway'] ?? null);
        $this->assertTrue((bool) ($log->after['simulated'] ?? false));
        $this->assertSame(1, (int) ($log->after['amount_minor'] ?? 0));
        $this->assertStringStartsWith('PAYPAL-', (string) ($log->after['reference'] ?? ''));
    }

    #[Test]
    public function disabled_gateway_cannot_run_a_test_payment(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->put(route('superadmin.integrations.update'), [
            'safe' => [
                'integrations.paypal.enabled' => '0',
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($sa)
            ->from(route('superadmin.integrations'))
            ->post(route('superadmin.integrations.test-payment'), [
                'gateway' => 'PAYPAL',
                'confirm' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('gateway');
    }

    #[Test]
    public function transactional_mailer_sets_from_per_identity(): void
    {
        $this->seed();
        $config = app(IntegrationConfigService::class);
        Setting::query()->updateOrCreate(
            ['key' => 'integrations.mail.transactional.from_address'],
            ['value' => ['value' => 'noreply@spims-edu.com']]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'integrations.mail.notifications.from_address'],
            ['value' => ['value' => 'notify@spims-edu.com']]
        );
        $config->refresh();

        $transport = app('mailer')->getSymfonyTransport();
        $this->assertTrue(method_exists($transport, 'flush'));
        $transport->flush();

        $ok = app(TransactionalMailer::class)->send(
            'student@example.com',
            'Hello',
            'Body without otp digits',
            IntegrationConfigService::IDENTITY_NOTIFICATIONS
        );
        $this->assertTrue($ok);
        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $email = $messages->last()->getOriginalMessage();
        $this->assertSame('notify@spims-edu.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('student@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Hello', $email->getSubject());
        $this->assertSame('Body without otp digits', $email->getTextBody());
    }

    #[Test]
    public function dashboard_hub_and_related_pages_link_to_integrations(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.integrations'))
            ->assertOk()
            ->assertSee(__('integrations.group_paypal'))
            ->assertSee(__('integrations.group_paymob'))
            ->assertSee(__('integrations.group_cashier'))
            ->assertSee(__('integrations.test_mail_title'))
            ->assertSee(__('integrations.test_payment_title'))
            ->assertDontSee('Stripe');

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('integrations.dashboard_tile'))
            ->assertSee(__('integrations.dashboard_tile_hint'))
            ->assertSee(route('superadmin.integrations'), false);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('integrations.dashboard_tile'));

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_integrations'))
            ->assertSee(__('superadmin.roadmap_sa9_done'))
            ->assertSee(route('superadmin.integrations'), false);

        $this->actingAs($sa)->get(route('superadmin.config'))
            ->assertOk()
            ->assertSee(__('integrations.entrance_from_config'));

        $this->actingAs($sa)->get(route('superadmin.status'))
            ->assertOk()
            ->assertSee(__('integrations.entrance_from_status'));

        $this->actingAs($sa)->get(route('hubs.finance'))
            ->assertOk()
            ->assertSee(__('integrations.entrance_from_finance'));
    }
}
