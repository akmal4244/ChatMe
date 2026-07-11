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
        $this->assertSame(2, substr_count($schedule, '->withoutOverlapping()'));
    }
}
