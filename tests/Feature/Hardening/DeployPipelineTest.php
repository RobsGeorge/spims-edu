<?php

namespace Tests\Feature\Hardening;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DeployPipelineTest extends TestCase
{
    #[Test]
    public function required_pipeline_files_exist(): void
    {
        foreach ([
            'scripts/vps-release.sh',
            'scripts/vps-sync.sh',
            'scripts/verify-pipelines.sh',
            'deploy/first-boot.sh',
            'deploy/nginx/spims.conf',
            'deploy/systemd/spims-queue.service',
            'deploy/systemd/spims-queue-staging.service',
            '.github/workflows/ci.yml',
            '.github/workflows/deploy.yml',
            '.github/workflows/deploy-staging.yml',
            '.github/workflows/rollback.yml',
            'docs/owner-actions.md',
            'docs/mobile-api-runtime.md',
            'docs/vps-setup.md',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative.' must be in the repo');
        }
    }

    #[Test]
    public function shell_scripts_pass_bash_syntax_check(): void
    {
        $code = 0;
        $output = [];
        exec('bash '.escapeshellarg(base_path('scripts/verify-pipelines.sh')).' 2>&1', $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));
    }

    #[Test]
    public function ci_workflow_is_the_deploy_gate(): void
    {
        $ci = Yaml::parseFile(base_path('.github/workflows/ci.yml'));
        $this->assertArrayHasKey('lint', $ci['jobs']);
        $this->assertArrayHasKey('sqlite-suite', $ci['jobs']);
        $this->assertArrayHasKey('pgsql-migrate-fresh', $ci['jobs']);
        $this->assertArrayHasKey('pgsql-full-suite', $ci['jobs']);

        $this->assertSame('pgsql', $ci['jobs']['pgsql-full-suite']['env']['DB_CONNECTION']);

        $prod = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));
        $this->assertSame('./.github/workflows/ci.yml', $prod['jobs']['test']['uses']);
        $this->assertFalse($prod['concurrency']['cancel-in-progress']);

        $staging = Yaml::parseFile(base_path('.github/workflows/deploy-staging.yml'));
        $this->assertSame('./.github/workflows/ci.yml', $staging['jobs']['test']['uses']);

        $rollback = Yaml::parseFile(base_path('.github/workflows/rollback.yml'));
        $this->assertArrayHasKey('workflow_dispatch', $rollback['on']);
    }

    #[Test]
    public function nginx_template_fronts_laravel_including_the_api(): void
    {
        $conf = file_get_contents(base_path('deploy/nginx/spims.conf'));

        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $conf);
        $this->assertStringContainsString('php8.2-fpm.sock', $conf);
        $this->assertStringContainsString('__SERVER_NAME__', $conf);
        $this->assertStringContainsString('__ROOT__', $conf);
        $this->assertStringContainsString('deny all', $conf);
    }

    #[Test]
    public function scheduler_ensure_cron_prints_the_line_without_writing(): void
    {
        $this->artisan('scheduler:ensure-cron', ['--php' => 'php8.2'])
            ->expectsOutputToContain('artisan schedule:run')
            ->assertSuccessful();
    }
}
