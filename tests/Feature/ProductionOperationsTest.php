<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
    public function test_repository_excludes_server_backup_files(): void
    {
        $this->assertFileDoesNotExist(base_path('.htaccess.bak'));
        $this->assertStringContainsString('*.bak', file_get_contents(base_path('.gitignore')));
    }

    public function test_default_logging_uses_daily_rotation_with_fourteen_day_retention(): void
    {
        $logging = file_get_contents(config_path('logging.php'));
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("env('LOG_STACK', 'daily')", $logging);
        $this->assertStringContainsString("env('LOG_DAILY_DAYS', 14)", $logging);
        $this->assertStringContainsString('LOG_STACK=daily', $environment);
        $this->assertStringContainsString('LOG_DAILY_DAYS=14', $environment);
    }

    public function test_scheduler_prunes_failed_jobs_and_batches_with_bounded_retention(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("Schedule::command('queue:prune-failed --hours=168')", $schedule);
        $this->assertStringContainsString("Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')", $schedule);
        $this->assertStringContainsString("Schedule::command('chatme:prune-message-quota-reservations')", $schedule);
        $this->assertSame(3, substr_count($schedule, '->withoutOverlapping()'));
    }

    public function test_session_payloads_and_ai_usage_guards_are_secure_by_default(): void
    {
        $session = file_get_contents(config_path('session.php'));
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("env('SESSION_ENCRYPT', true)", $session);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $environment);
        $this->assertStringContainsString('CHATME_QUOTA_RESERVATION_TTL_SECONDS=120', $environment);
        $this->assertStringContainsString('CHATME_TESTER_DAILY_AI_LIMIT=20', $environment);
    }
}
