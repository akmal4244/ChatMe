<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentRollbackToolTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chatme-deploy-tests-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    #[Test]
    public function preflight_rejects_a_non_exact_target_sha_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();

        $process = $this->runDeploy($fixture, ['--target-sha='.substr($fixture['target'], 0, 12)]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('full lowercase 40-character', $process->getErrorOutput());
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_state_inside_the_web_root_before_creating_it(): void
    {
        $fixture = $this->createDeploymentFixture();
        $unsafeState = $fixture['project'].'/public/deploy-state';

        $process = $this->runDeploy($fixture, ['--state-root='.$unsafeState]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('outside the project and web roots', $process->getErrorOutput());
        $this->assertDirectoryDoesNotExist($unsafeState);
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_a_dirty_working_tree_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        file_put_contents($fixture['project'].'/operator-note.txt', 'untracked');

        $process = $this->runDeploy($fixture);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('working tree must be clean', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_a_backup_for_a_different_release_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        $mismatchBackup = $this->createBackup($fixture, str_repeat('f', 40), 'mismatch-backups');

        $process = $this->runDeploy($fixture, ['--backup='.$mismatchBackup]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('backup release sha does not match current head', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_a_target_not_reachable_from_the_approved_remote_ref(): void
    {
        $fixture = $this->createDeploymentFixture();

        $process = $this->runDeploy($fixture, ['--target-sha='.$fixture['unapproved']]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('approved remote release ref', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_a_downgrade_through_normal_deployment(): void
    {
        $fixture = $this->createDeploymentFixture();
        $this->git($fixture['project'], ['switch', '--detach', $fixture['target']]);
        $backup = $this->createBackup($fixture, $fixture['target'], 'target-backups');

        $process = $this->runDeploy($fixture, [
            '--target-sha='.$fixture['previous'],
            '--backup='.$backup,
        ]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('forward descendant', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function preflight_rejects_an_active_nonblocking_lock_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        mkdir($fixture['state'], 0700, true);
        $handle = fopen($fixture['state'].'/.chatme-deploy.lock', 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            $process = $this->runDeploy($fixture);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('another deployment operation is active', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function successful_deployment_switches_to_the_exact_sha_and_records_every_guarded_phase(): void
    {
        $fixture = $this->createDeploymentFixture();

        $process = $this->runDeploy($fixture);

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput()."\nFixture: {$this->temporaryRoot}\nCommands:\n".$this->normalizedCommands($fixture),
        );
        $result = $this->decodeOutput($process);
        $this->assertSame('deployed', $result['status']);
        $this->assertSame($fixture['previous'], $result['previous_sha']);
        $this->assertSame($fixture['target'], $result['target_sha']);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z-[a-f0-9]{8}$/', $result['deployment_id']);
        $this->assertSame($fixture['target'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));

        $commands = $this->normalizedCommands($fixture);
        $ordered = [
            'artisan down --retry=30 --refresh=15',
            'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader',
            'npm ci',
            'npm run build',
            'artisan optimize:clear',
            'artisan migrate --force --no-interaction',
            'artisan config:cache',
            'artisan route:cache',
            'artisan view:cache',
            'artisan migrate:status --no-ansi',
            'php -r',
            'artisan up',
        ];
        $offset = 0;
        foreach ($ordered as $expected) {
            $position = strpos($commands, $expected, $offset);
            $this->assertNotFalse($position, 'Missing or out-of-order command: '.$expected."\n".$commands);
            $offset = $position + strlen($expected);
        }
        $this->assertProhibitedCommandsAreAbsent($commands);

        $metadata = $this->deploymentMetadata($fixture, $result['deployment_id']);
        $this->assertSame($fixture['previous'], $metadata['previous_sha']);
        $this->assertSame($fixture['target'], $metadata['target_sha']);
        $this->assertSame(str_replace('\\', '/', $fixture['backup']), $metadata['backup_path']);
        $this->assertSame('fixture-operator', $metadata['actor']);
        $events = $this->deploymentEvents($fixture, $result['deployment_id']);
        $this->assertSame('deployment', $events[array_key_last($events)]['operation']);
        $this->assertSame('complete', $events[array_key_last($events)]['phase']);
        $this->assertSame('ok', $events[array_key_last($events)]['status']);

        if (PHP_OS_FAMILY !== 'Windows') {
            $recordRoot = $fixture['state'].'/deployments/'.$result['deployment_id'];
            $this->assertSame(0700, fileperms($recordRoot) & 0777);
            $this->assertSame(0600, fileperms($recordRoot.'/metadata.json') & 0777);
            $this->assertSame(0600, fileperms($recordRoot.'/events.jsonl') & 0777);
        }
    }

    /** @return array<string, array{string}> */
    public static function deploymentFailurePhases(): array
    {
        return [
            'maintenance down' => ['down'],
            'composer' => ['composer'],
            'build' => ['build'],
            'migration' => ['migration'],
            'cache' => ['cache'],
        ];
    }

    #[Test]
    #[DataProvider('deploymentFailurePhases')]
    public function deployment_failure_recovers_previous_code_calls_up_and_never_leaks_command_output(string $phase): void
    {
        $fixture = $this->createDeploymentFixture();

        $process = $this->runDeploy($fixture, environment: [
            'CHATME_OPS_FAIL_PHASE' => $phase,
        ]);

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Deployment command failed during', $combinedOutput);
        $this->assertStringNotContainsString('fixture-secret-must-not-leak', $combinedOutput);
        $this->assertSame($fixture['previous'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));

        $commands = $this->normalizedCommands($fixture);
        $this->assertStringContainsString('artisan down --retry=30 --refresh=15', $commands);
        $this->assertStringContainsString('artisan up', $commands);
        $this->assertProhibitedCommandsAreAbsent($commands);

        $deploymentIds = $this->deploymentIds($fixture);
        $this->assertCount(1, $deploymentIds);
        $events = $this->deploymentEvents($fixture, $deploymentIds[0]);
        $last = $events[array_key_last($events)];
        $this->assertSame('deployment', $last['operation']);
        $this->assertSame('complete', $last['phase']);
        $this->assertSame('failed', $last['status']);
        $stateContents = $this->deploymentStateContents($fixture, $deploymentIds[0]);
        $this->assertStringNotContainsString('fixture-secret-must-not-leak', $stateContents);
    }

    #[Test]
    public function rollback_accepts_only_a_recorded_deployment_identifier_and_never_a_sha(): void
    {
        $fixture = $this->createDeploymentFixture();

        $process = $this->runRollback($fixture, $fixture['target']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('recorded deployment identifier', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function rollback_rejects_tampered_state_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        $deployment = $this->successfulDeployment($fixture);
        $metadataFile = $fixture['state'].'/deployments/'.$deployment['deployment_id'].'/metadata.json';
        $metadata = json_decode((string) file_get_contents($metadataFile), true, flags: JSON_THROW_ON_ERROR);
        $metadata['payload']['target_sha'] = str_repeat('a', 40);
        file_put_contents($metadataFile, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        file_put_contents($fixture['log'], '');

        $process = $this->runRollback($fixture, $deployment['deployment_id']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('integrity verification', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function rollback_rejects_failed_deployment_state_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        $failed = $this->runDeploy($fixture, environment: ['CHATME_OPS_FAIL_PHASE' => 'migration']);
        $this->assertNotSame(0, $failed->getExitCode());
        $deploymentId = $this->deploymentIds($fixture)[0];
        file_put_contents($fixture['log'], '');

        $process = $this->runRollback($fixture, $deploymentId);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('not completed successfully', strtolower($process->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function rollback_rejects_a_dirty_tree_or_unverified_backup_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        $deployment = $this->successfulDeployment($fixture);
        file_put_contents($fixture['project'].'/operator-note.txt', 'untracked');
        file_put_contents($fixture['log'], '');

        $dirty = $this->runRollback($fixture, $deployment['deployment_id']);

        $this->assertNotSame(0, $dirty->getExitCode());
        $this->assertStringContainsString('working tree must be clean', strtolower($dirty->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);

        unlink($fixture['project'].'/operator-note.txt');
        file_put_contents($fixture['backup'].'/metadata/release-sha.txt', str_repeat('f', 40)."\n");
        $invalidBackup = $this->runRollback($fixture, $deployment['deployment_id']);

        $this->assertNotSame(0, $invalidBackup->getExitCode());
        $this->assertStringContainsString('checksum mismatch', strtolower($invalidBackup->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function rollback_rejects_mismatched_current_head_and_an_active_lock_before_maintenance(): void
    {
        $fixture = $this->createDeploymentFixture();
        $deployment = $this->successfulDeployment($fixture);
        $this->git($fixture['project'], ['switch', '--detach', $fixture['previous']]);
        file_put_contents($fixture['log'], '');

        $mismatch = $this->runRollback($fixture, $deployment['deployment_id']);

        $this->assertNotSame(0, $mismatch->getExitCode());
        $this->assertStringContainsString('current head must match', strtolower($mismatch->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);

        $this->git($fixture['project'], ['switch', '--detach', $fixture['target']]);
        $handle = fopen($fixture['state'].'/.chatme-deploy.lock', 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        try {
            $locked = $this->runRollback($fixture, $deployment['deployment_id']);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertNotSame(0, $locked->getExitCode());
        $this->assertStringContainsString('another deployment operation is active', strtolower($locked->getErrorOutput()));
        $this->assertMaintenanceWasNeverEntered($fixture);
    }

    #[Test]
    public function successful_rollback_uses_recorded_previous_sha_and_never_reverses_database_changes(): void
    {
        $fixture = $this->createDeploymentFixture();
        $deployment = $this->successfulDeployment($fixture);
        file_put_contents($fixture['log'], '');

        $process = $this->runRollback($fixture, $deployment['deployment_id']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $result = $this->decodeOutput($process);
        $this->assertSame('rolled_back', $result['status']);
        $this->assertSame($deployment['deployment_id'], $result['deployment_id']);
        $this->assertSame($fixture['previous'], $result['previous_sha']);
        $this->assertSame($fixture['target'], $result['target_sha']);
        $this->assertSame($fixture['previous'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));

        $commands = $this->normalizedCommands($fixture);
        $ordered = [
            'artisan down --retry=30 --refresh=15',
            'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader',
            'npm ci',
            'npm run build',
            'artisan optimize:clear',
            'artisan config:cache',
            'artisan route:cache',
            'artisan view:cache',
            'artisan migrate:status --no-ansi',
            'php -r',
            'artisan up',
        ];
        $offset = 0;
        foreach ($ordered as $expected) {
            $position = strpos($commands, $expected, $offset);
            $this->assertNotFalse($position, 'Missing or out-of-order rollback command: '.$expected."\n".$commands);
            $offset = $position + strlen($expected);
        }
        $this->assertStringNotContainsString('artisan migrate --force', $commands);
        $this->assertProhibitedCommandsAreAbsent($commands);

        $events = $this->deploymentEvents($fixture, $deployment['deployment_id']);
        $last = $events[array_key_last($events)];
        $this->assertSame('rollback', $last['operation']);
        $this->assertSame('complete', $last['phase']);
        $this->assertSame('ok', $last['status']);
        $this->assertSame('fixture-operator', $last['actor']);
    }

    #[Test]
    public function rollback_failure_restores_target_calls_up_and_never_leaks_command_output(): void
    {
        $fixture = $this->createDeploymentFixture();
        $deployment = $this->successfulDeployment($fixture);
        file_put_contents($fixture['log'], '');

        $process = $this->runRollback(
            $fixture,
            $deployment['deployment_id'],
            ['CHATME_OPS_FAIL_PHASE' => 'cache'],
        );

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Rollback command failed during', $combinedOutput);
        $this->assertStringNotContainsString('fixture-secret-must-not-leak', $combinedOutput);
        $this->assertSame($fixture['target'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));
        $commands = $this->normalizedCommands($fixture);
        $this->assertStringContainsString('artisan down --retry=30 --refresh=15', $commands);
        $this->assertStringContainsString('artisan up', $commands);
        $this->assertStringNotContainsString('artisan migrate --force', $commands);
        $this->assertProhibitedCommandsAreAbsent($commands);
        $events = $this->deploymentEvents($fixture, $deployment['deployment_id']);
        $last = $events[array_key_last($events)];
        $this->assertSame('rollback', $last['operation']);
        $this->assertSame('complete', $last['phase']);
        $this->assertSame('failed', $last['status']);
        $this->assertStringNotContainsString(
            'fixture-secret-must-not-leak',
            $this->deploymentStateContents($fixture, $deployment['deployment_id']),
        );
    }

    #[Test]
    public function state_event_io_failure_after_down_cannot_skip_artisan_up_for_deploy_or_rollback(): void
    {
        $deployFixture = $this->createDeploymentFixture('deploy-state-io');
        $deploy = $this->runDeploy($deployFixture, environment: [
            'CHATME_OPS_FAIL_PHASE' => 'state_after_down',
        ]);

        $this->assertNotSame(0, $deploy->getExitCode());
        $this->assertStringContainsString('artisan up', $this->normalizedCommands($deployFixture));
        $this->assertSame($deployFixture['previous'], $this->git($deployFixture['project'], ['rev-parse', 'HEAD']));

        $rollbackFixture = $this->createDeploymentFixture('rollback-state-io');
        $deployment = $this->successfulDeployment($rollbackFixture);
        file_put_contents($rollbackFixture['log'], '');
        $rollback = $this->runRollback(
            $rollbackFixture,
            $deployment['deployment_id'],
            ['CHATME_OPS_FAIL_PHASE' => 'state_after_down'],
        );

        $this->assertNotSame(0, $rollback->getExitCode());
        $this->assertStringContainsString('artisan up', $this->normalizedCommands($rollbackFixture));
        $this->assertSame($rollbackFixture['target'], $this->git($rollbackFixture['project'], ['rev-parse', 'HEAD']));
    }

    #[Test]
    public function a_timed_out_command_recovers_code_calls_up_and_releases_the_lock(): void
    {
        $fixture = $this->createDeploymentFixture('command-timeout');

        $process = $this->runDeploy(
            $fixture,
            ['--command-timeout=3'],
            ['CHATME_OPS_FAIL_PHASE' => 'timeout'],
        );

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('command timed out during', strtolower($process->getErrorOutput()));
        $this->assertSame($fixture['previous'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));
        $deploymentId = $this->deploymentIds($fixture)[0];
        $maintenanceEvents = array_values(array_filter(
            $this->deploymentEvents($fixture, $deploymentId),
            fn (array $event): bool => $event['phase'] === 'maintenance_up',
        ));
        $this->assertNotEmpty($maintenanceEvents);
        $this->assertSame('started', $maintenanceEvents[0]['status']);

        $handle = fopen($fixture['state'].'/.chatme-deploy.lock', 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    #[Test]
    public function maintenance_up_failure_takes_priority_over_an_earlier_phase_failure(): void
    {
        $fixture = $this->createDeploymentFixture('maintenance-up-failure');

        $process = $this->runDeploy($fixture, environment: [
            'CHATME_OPS_FAIL_PHASE' => 'composer_and_up',
        ]);

        $combinedOutput = $process->getOutput().$process->getErrorOutput();
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString(
            'leaving Laravel maintenance mode',
            $combinedOutput,
            $this->normalizedCommands($fixture),
        );
        $this->assertStringNotContainsString('fixture-secret-must-not-leak', $combinedOutput);
        $this->assertStringContainsString('artisan up', $this->normalizedCommands($fixture));
    }

    #[Test]
    public function incompatible_previous_code_after_forward_migration_is_not_brought_online(): void
    {
        $fixture = $this->createDeploymentFixture('forward-schema-compatibility');

        $process = $this->runDeploy($fixture, environment: [
            'CHATME_OPS_FAIL_PHASE' => 'target_then_previous_health',
        ]);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame($fixture['target'], $this->git($fixture['project'], ['rev-parse', 'HEAD']));
        $commands = $this->normalizedCommands($fixture);
        $this->assertSame(1, substr_count($commands, 'artisan migrate --force --no-interaction'));
        $this->assertGreaterThanOrEqual(3, substr_count($commands, 'php -r'));
        $this->assertStringContainsString('artisan up', $commands);
        $this->assertProhibitedCommandsAreAbsent($commands);
    }

    /**
     * @return array{
     *     project: string,
     *     previous: string,
     *     target: string,
     *     unapproved: string,
     *     backup: string,
     *     state: string,
     *     log: string,
     *     database: string,
     *     migrations: string,
     *     private: string,
     *     public: string,
     *     environment: array<string, string>
     * }
     */
    private function createDeploymentFixture(string $fixtureName = ''): array
    {
        $fixtureRoot = $fixtureName === '' ? $this->temporaryRoot : $this->temporaryRoot.'/'.$fixtureName;
        if (! is_dir($fixtureRoot)) {
            mkdir($fixtureRoot, 0700, true);
        }
        $project = $fixtureRoot.'/project';
        $remote = $fixtureRoot.'/remote.git';
        mkdir($project, 0700, true);
        $this->git($this->temporaryRoot, ['init', '--bare', '--initial-branch=release', $remote]);
        $this->git($project, ['init', '--initial-branch=release']);
        $this->git($project, ['config', 'user.email', 'fixture@example.test']);
        $this->git($project, ['config', 'user.name', 'Fixture Operator']);
        $this->git($project, ['remote', 'add', 'origin', $remote]);

        mkdir($project.'/public', 0700, true);
        file_put_contents($project.'/artisan', "<?php\n");
        file_put_contents($project.'/composer.json', "{}\n");
        file_put_contents($project.'/package.json', "{}\n");
        file_put_contents($project.'/package-lock.json', "{}\n");
        file_put_contents($project.'/.gitignore', "/database/source.sqlite\n/migration-status.txt\n/storage/\n");
        file_put_contents($project.'/release.txt', "previous\n");
        $this->git($project, ['add', '.']);
        $this->git($project, ['commit', '-m', 'previous release']);
        $previous = $this->git($project, ['rev-parse', 'HEAD']);
        $this->git($project, ['push', '-u', 'origin', 'release']);

        file_put_contents($project.'/release.txt', "target\n");
        $this->git($project, ['add', 'release.txt']);
        $this->git($project, ['commit', '-m', 'approved target']);
        $target = $this->git($project, ['rev-parse', 'HEAD']);
        $this->git($project, ['push', 'origin', 'release']);

        file_put_contents($project.'/release.txt', "unapproved\n");
        $this->git($project, ['add', 'release.txt']);
        $this->git($project, ['commit', '-m', 'unapproved local target']);
        $unapproved = $this->git($project, ['rev-parse', 'HEAD']);
        $this->git($project, ['switch', '--detach', $previous]);

        $private = $project.'/storage/app/private';
        $public = $project.'/storage/app/public';
        $database = $project.'/database/source.sqlite';
        $migrations = $project.'/migration-status.txt';
        mkdir($private, 0700, true);
        mkdir($public, 0700, true);
        mkdir(dirname($database), 0700, true);
        file_put_contents($private.'/private.txt', 'private fixture');
        file_put_contents($public.'/public.txt', 'public fixture');
        file_put_contents($migrations, "fixture migration [1] Ran\n");
        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE fixtures (name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO fixtures (name) VALUES ('deployment fixture')");
        unset($pdo);

        $log = $fixtureRoot.'/commands.log';
        $environment = $this->createFakeCommandEnvironment($log);
        $fixture = [
            'project' => $project,
            'previous' => $previous,
            'target' => $target,
            'unapproved' => $unapproved,
            'backup' => '',
            'state' => $fixtureRoot.'/deploy-state',
            'log' => $log,
            'database' => $database,
            'migrations' => $migrations,
            'private' => $private,
            'public' => $public,
            'environment' => $environment,
        ];
        $fixture['environment']['CHATME_OPS_STATE_ROOT'] = $fixture['state'];
        $fixture['environment']['CHATME_OPS_TIMEOUT_MARKER'] = $fixtureRoot.'/timeout.marker';
        $fixture['environment']['CHATME_OPS_HEALTH_MARKER'] = $fixtureRoot.'/health.marker';
        $fixture['backup'] = $this->createBackup($fixture, $previous, 'backups');

        return $fixture;
    }

    /**
     * @param  array{project:string,database:string,migrations:string,private:string,public:string}  $fixture
     */
    private function createBackup(array $fixture, string $releaseSha, string $directory): string
    {
        $process = $this->runScript('scripts/ops/backup.php', [
            '--project-root='.$fixture['project'],
            '--backup-root='.dirname($fixture['project']).'/'.$directory,
            '--database-driver=sqlite',
            '--disposable-test-mode',
            '--sqlite-database='.$fixture['database'],
            '--storage-private='.$fixture['private'],
            '--storage-public='.$fixture['public'],
            '--release-sha='.$releaseSha,
            '--migration-state-file='.$fixture['migrations'],
        ]);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return $this->decodeOutput($process)['backup'];
    }

    /**
     * @param  array{project:string,target:string,backup:string,state:string,environment:array<string,string>}  $fixture
     * @param  list<string>  $overrides
     */
    private function runDeploy(array $fixture, array $overrides = [], array $environment = []): Process
    {
        $arguments = [
            '--target-sha='.$fixture['target'],
            '--project-root='.$fixture['project'],
            '--state-root='.$fixture['state'],
            '--backup='.$fixture['backup'],
            '--web-root='.$fixture['project'],
            '--remote=origin',
            '--release-ref=release',
            '--actor=fixture-operator',
            '--max-processes=80',
            '--command-timeout=30',
        ];

        foreach ($overrides as $override) {
            $name = strstr($override, '=', true) ?: $override;
            $arguments = array_values(array_filter(
                $arguments,
                fn (string $argument): bool => ! str_starts_with($argument, $name.'='),
            ));
            $arguments[] = $override;
        }

        return $this->runScript(
            'scripts/ops/deploy.php',
            $arguments,
            array_merge($fixture['environment'], $environment),
        );
    }

    /**
     * @param  array{project:string,state:string,environment:array<string,string>}  $fixture
     * @param  array<string, string>  $environment
     */
    private function runRollback(array $fixture, string $deploymentId, array $environment = []): Process
    {
        return $this->runScript('scripts/ops/rollback.php', [
            '--deployment-id='.$deploymentId,
            '--project-root='.$fixture['project'],
            '--state-root='.$fixture['state'],
            '--web-root='.$fixture['project'],
            '--actor=fixture-operator',
            '--max-processes=80',
            '--command-timeout=30',
        ], array_merge($fixture['environment'], $environment));
    }

    /**
     * @param  array{target:string}  $fixture
     * @return array{deployment_id:string,previous_sha:string,target_sha:string,status:string}
     */
    private function successfulDeployment(array $fixture): array
    {
        $process = $this->runDeploy($fixture);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $deployment = $this->decodeOutput($process);
        $this->assertSame($fixture['target'], $deployment['target_sha']);

        return $deployment;
    }

    /** @return array<string, string> */
    private function createFakeCommandEnvironment(string $log): array
    {
        $binaryRoot = $this->temporaryRoot.'/bin';
        if (! is_dir($binaryRoot)) {
            mkdir($binaryRoot, 0700, true);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $php = $binaryRoot.'/php.cmd';
            $composer = $binaryRoot.'/composer.cmd';
            $npm = $binaryRoot.'/npm.cmd';
            $ps = $binaryRoot.'/ps.cmd';
            file_put_contents($php, <<<'BAT'
@echo off
set "CHATME_RELEASE="
if exist "%CD%\release.txt" for /f "usebackq delims=" %%R in ("%CD%\release.txt") do set "CHATME_RELEASE=%%R"
if "%~1"=="-r" echo php -r>>"%CHATME_OPS_COMMAND_LOG%"
if "%CHATME_OPS_FAIL_PHASE%"=="target_then_previous_health" if "%~1"=="-r" if "%CHATME_RELEASE%"=="previous" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 56
)
if "%CHATME_OPS_FAIL_PHASE%"=="target_then_previous_health" if "%~1"=="-r" (
if not exist "%CHATME_OPS_HEALTH_MARKER%" (
type nul >"%CHATME_OPS_HEALTH_MARKER%"
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 57
)
)
if "%CHATME_OPS_FAIL_PHASE%"=="health" if "%~1"=="-r" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 54
)
if "%~1"=="-r" exit /b 0
echo php %~1 %~2 %~3 %~4 %~5>>"%CHATME_OPS_COMMAND_LOG%"
if "%CHATME_OPS_FAIL_PHASE%"=="composer_and_up" if "%~2"=="up" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 58
)
if "%~2"=="up" exit /b 0
if "%CHATME_OPS_FAIL_PHASE%"=="state_after_down" if "%~2"=="down" (
for /d %%D in ("%CHATME_OPS_STATE_ROOT%\deployments\*") do (
del /q "%%~fD\events.jsonl" >nul 2>&1
mkdir "%%~fD\events.jsonl" >nul 2>&1
)
)
if "%CHATME_OPS_FAIL_PHASE%"=="down" if "%~2"=="down" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 50
)
if "%CHATME_OPS_FAIL_PHASE%"=="migration" if "%~2"=="migrate" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 52
)
if "%CHATME_OPS_FAIL_PHASE%"=="cache" if "%~2"=="route:cache" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 53
)
exit /b 0
BAT);
            file_put_contents($composer, <<<'BAT'
@echo off
echo composer %~1 %~2 %~3 %~4 %~5>>"%CHATME_OPS_COMMAND_LOG%"
if "%CHATME_OPS_FAIL_PHASE%"=="timeout" if not exist "%CHATME_OPS_TIMEOUT_MARKER%" (
type nul >"%CHATME_OPS_TIMEOUT_MARKER%"
call :CHATME_HANG_FOREVER
)
if "%CHATME_OPS_FAIL_PHASE%"=="composer" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 51
)
if "%CHATME_OPS_FAIL_PHASE%"=="composer_and_up" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 51
)
exit /b 0
:CHATME_HANG_FOREVER
goto CHATME_HANG_FOREVER
BAT);
            file_put_contents($npm, <<<'BAT'
@echo off
echo npm %~1 %~2 %~3>>"%CHATME_OPS_COMMAND_LOG%"
if "%CHATME_OPS_FAIL_PHASE%"=="build" if "%~1"=="run" if "%~2"=="build" (
echo %CHATME_SECRET_SENTINEL% 1>&2
exit /b 55
)
exit /b 0
BAT);
            file_put_contents($ps, "@echo off\necho 101\necho 102\necho 103\n");
        } else {
            $php = $binaryRoot.'/php';
            $composer = $binaryRoot.'/composer';
            $npm = $binaryRoot.'/npm';
            $ps = $binaryRoot.'/ps';
            file_put_contents($php, <<<'SH'
#!/usr/bin/env sh
printf 'php %s\n' "$*" >> "$CHATME_OPS_COMMAND_LOG"
if [ "${1:-}" = -r ]; then
    if [ "${CHATME_OPS_FAIL_PHASE:-}" = target_then_previous_health ]; then
        if grep -q '^previous' "$PWD/release.txt"; then
            printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2
            exit 56
        fi
        if [ ! -e "$CHATME_OPS_HEALTH_MARKER" ]; then
            : > "$CHATME_OPS_HEALTH_MARKER"
            printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2
            exit 57
        fi
    fi
    [ "${CHATME_OPS_FAIL_PHASE:-}" = health ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 54
    exit 0
fi
if [ "${2:-}" = up ]; then
    [ "${CHATME_OPS_FAIL_PHASE:-}" = composer_and_up ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 58
    exit 0
fi
if [ "${CHATME_OPS_FAIL_PHASE:-}" = state_after_down ] && [ "${2:-}" = down ]; then
    for directory in "$CHATME_OPS_STATE_ROOT"/deployments/*; do
        [ -d "$directory" ] || continue
        rm -f "$directory/events.jsonl"
        mkdir "$directory/events.jsonl"
    done
fi
[ "${CHATME_OPS_FAIL_PHASE:-}" = down ] && [ "${2:-}" = down ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 50
[ "${CHATME_OPS_FAIL_PHASE:-}" = migration ] && [ "${2:-}" = migrate ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 52
[ "${CHATME_OPS_FAIL_PHASE:-}" = cache ] && [ "${2:-}" = route:cache ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 53
exit 0
SH);
            file_put_contents($composer, <<<'SH'
#!/usr/bin/env sh
printf 'composer %s\n' "$*" >> "$CHATME_OPS_COMMAND_LOG"
if [ "${CHATME_OPS_FAIL_PHASE:-}" = timeout ] && [ ! -e "$CHATME_OPS_TIMEOUT_MARKER" ]; then
    : > "$CHATME_OPS_TIMEOUT_MARKER"
    while :; do :; done
fi
[ "${CHATME_OPS_FAIL_PHASE:-}" = composer ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 51
[ "${CHATME_OPS_FAIL_PHASE:-}" = composer_and_up ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 51
exit 0
SH);
            file_put_contents($npm, <<<'SH'
#!/usr/bin/env sh
printf 'npm %s\n' "$*" >> "$CHATME_OPS_COMMAND_LOG"
[ "${CHATME_OPS_FAIL_PHASE:-}" = build ] && [ "${1:-}" = run ] && [ "${2:-}" = build ] && printf '%s\n' "$CHATME_SECRET_SENTINEL" >&2 && exit 55
exit 0
SH);
            file_put_contents($ps, "#!/usr/bin/env sh\nprintf '101\\n102\\n103\\n'\n");
            chmod($php, 0700);
            chmod($composer, 0700);
            chmod($npm, 0700);
            chmod($ps, 0700);
        }

        return [
            'CHATME_OPS_PHP_BINARY' => $php,
            'CHATME_OPS_COMPOSER_BINARY' => $composer,
            'CHATME_OPS_NPM_BINARY' => $npm,
            'CHATME_OPS_PS_BINARY' => $ps,
            'CHATME_OPS_COMMAND_LOG' => $log,
            'CHATME_SECRET_SENTINEL' => 'fixture-secret-must-not-leak',
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function runScript(string $script, array $arguments, array $environment = []): Process
    {
        $process = new Process(
            array_merge([PHP_BINARY, base_path($script)], $arguments),
            base_path(),
            $environment,
            null,
            60,
        );
        $process->run();

        return $process;
    }

    /** @param list<string> $arguments */
    private function git(string $directory, array $arguments): string
    {
        $process = new Process(array_merge(['git'], $arguments), $directory, timeout: 30);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return trim($process->getOutput());
    }

    /** @return array<string, mixed> */
    private function decodeOutput(Process $process): array
    {
        $decoded = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /** @param array{log:string} $fixture */
    private function assertMaintenanceWasNeverEntered(array $fixture): void
    {
        $commands = is_file($fixture['log']) ? (string) file_get_contents($fixture['log']) : '';
        $this->assertStringNotContainsString('artisan down', $commands);
    }

    /** @param array{log:string} $fixture */
    private function normalizedCommands(array $fixture): string
    {
        return str_replace(
            ['\\', '"'],
            ['/', ''],
            is_file($fixture['log']) ? (string) file_get_contents($fixture['log']) : '',
        );
    }

    private function assertProhibitedCommandsAreAbsent(string $commands): void
    {
        $this->assertStringNotContainsString('db:seed', $commands);
        $this->assertStringNotContainsString('homepage', strtolower($commands));
        $this->assertStringNotContainsString('migrate:rollback', $commands);
        $this->assertStringNotContainsString('reset --hard', $commands);
        $this->assertStringNotContainsString('database restore', strtolower($commands));
    }

    /** @param array{state:string} $fixture @return list<string> */
    private function deploymentIds(array $fixture): array
    {
        $directories = glob($fixture['state'].'/deployments/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        $ids = array_map(
            fn (string $directory): string => basename(str_replace('\\', '/', $directory)),
            $directories,
        );
        sort($ids, SORT_STRING);

        return $ids;
    }

    /** @param array{state:string} $fixture @return array<string, mixed> */
    private function deploymentMetadata(array $fixture, string $deploymentId): array
    {
        $envelope = json_decode(
            (string) file_get_contents($fixture['state'].'/deployments/'.$deploymentId.'/metadata.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertIsArray($envelope);
        $this->assertIsArray($envelope['payload'] ?? null);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($envelope['hmac_sha256'] ?? ''));

        return $envelope['payload'];
    }

    /** @param array{state:string} $fixture @return list<array<string, mixed>> */
    private function deploymentEvents(array $fixture, string $deploymentId): array
    {
        $lines = file(
            $fixture['state'].'/deployments/'.$deploymentId.'/events.jsonl',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES,
        );
        $this->assertIsArray($lines);
        $events = [];
        foreach ($lines as $line) {
            $envelope = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($envelope);
            $this->assertIsArray($envelope['payload'] ?? null);
            $events[] = $envelope['payload'];
        }

        return $events;
    }

    /** @param array{state:string} $fixture */
    private function deploymentStateContents(array $fixture, string $deploymentId): string
    {
        $directory = $fixture['state'].'/deployments/'.$deploymentId;

        return (string) file_get_contents($directory.'/metadata.json')
            .(string) file_get_contents($directory.'/events.jsonl');
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (PHP_OS_FAMILY === 'Windows') {
                @chmod($item->getPathname(), $item->isDir() ? 0777 : 0666);
            }

            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
