<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupRestoreToolTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chatme-ops-tests-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    #[Test]
    public function sqlite_backup_is_complete_verifiable_and_secret_free(): void
    {
        $fixture = $this->createProjectFixture();
        $backup = $this->createBackup($fixture, 'sqlite');

        $this->assertFileExists($backup.'/database/database.sqlite');
        $this->assertFileExists($backup.'/storage/app/private/tenant-1/private.txt');
        $this->assertFileExists($backup.'/storage/app/public/tenant-1/public.txt');
        $this->assertFileExists($backup.'/metadata/release-sha.txt');
        $this->assertFileExists($backup.'/metadata/migrations.txt');
        $this->assertFileExists($backup.'/manifest.json');
        $this->assertFileExists($backup.'/SHA256SUMS');

        $manifest = file_get_contents($backup.'/manifest.json');
        $this->assertIsString($manifest);
        $this->assertStringContainsString(str_repeat('a', 40), $manifest);
        $this->assertStringNotContainsString('fixture-password-must-not-leak', $manifest);
        $this->assertStringNotContainsString('fixture-token-must-not-leak', $manifest);

        $verify = $this->runScript('scripts/ops/verify.php', [
            '--backup='.$backup,
        ]);

        $this->assertSame(0, $verify->getExitCode(), $verify->getErrorOutput());
        $this->assertSame('verified', $this->decodeOutput($verify)['status']);

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0700, fileperms($backup) & 0777);
            $this->assertSame(0600, fileperms($backup.'/manifest.json') & 0777);
        }
    }

    #[Test]
    public function backup_refuses_a_destination_inside_the_public_web_root(): void
    {
        $fixture = $this->createProjectFixture();
        $process = $this->runScript('scripts/ops/backup.php', $this->backupArguments(
            $fixture,
            $fixture['project'].'/public/backups',
            'sqlite',
        ));

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('outside the project and public web roots', $process->getErrorOutput());
        $this->assertDirectoryDoesNotExist($fixture['project'].'/public/backups');
    }

    #[Test]
    public function verification_detects_tampering_without_printing_file_contents(): void
    {
        $fixture = $this->createProjectFixture();
        $backup = $this->createBackup($fixture, 'sqlite');
        file_put_contents(
            $backup.'/storage/app/private/tenant-1/private.txt',
            'fixture-password-must-not-leak',
        );

        $verify = $this->runScript('scripts/ops/verify.php', [
            '--backup='.$backup,
        ]);

        $combinedOutput = $verify->getOutput().$verify->getErrorOutput();
        $this->assertNotSame(0, $verify->getExitCode());
        $this->assertStringContainsString('checksum mismatch', strtolower($combinedOutput));
        $this->assertStringNotContainsString('fixture-password-must-not-leak', $combinedOutput);
    }

    #[Test]
    public function restore_drill_requires_explicit_confirmation_and_a_new_disposable_target(): void
    {
        $fixture = $this->createProjectFixture();
        $backup = $this->createBackup($fixture, 'sqlite');
        $target = $this->temporaryRoot.'/chatme-restore-drill-success';

        $refused = $this->runScript('scripts/ops/restore-drill.php', [
            '--backup='.$backup,
            '--project-root='.$fixture['project'],
            '--target-root='.$target,
        ]);

        $this->assertNotSame(0, $refused->getExitCode());
        $this->assertStringContainsString('RESTORE-DRILL', $refused->getErrorOutput());
        $this->assertDirectoryDoesNotExist($target);

        $restored = $this->runScript('scripts/ops/restore-drill.php', [
            '--backup='.$backup,
            '--project-root='.$fixture['project'],
            '--target-root='.$target,
            '--confirm=RESTORE-DRILL',
        ]);

        $this->assertSame(0, $restored->getExitCode(), $restored->getErrorOutput());
        $this->assertSame('restored', $this->decodeOutput($restored)['status']);
        $this->assertFileEquals(
            $backup.'/storage/app/private/tenant-1/private.txt',
            $target.'/storage/app/private/tenant-1/private.txt',
        );
        $this->assertFileEquals(
            $backup.'/storage/app/public/tenant-1/public.txt',
            $target.'/storage/app/public/tenant-1/public.txt',
        );

        $database = new PDO('sqlite:'.$target.'/database/database.sqlite');
        $this->assertSame('backup fixture', $database->query('SELECT name FROM fixtures')->fetchColumn());

        $secondAttempt = $this->runScript('scripts/ops/restore-drill.php', [
            '--backup='.$backup,
            '--project-root='.$fixture['project'],
            '--target-root='.$target,
            '--confirm=RESTORE-DRILL',
        ]);

        $this->assertNotSame(0, $secondAttempt->getExitCode());
        $this->assertStringContainsString('must not already exist', $secondAttempt->getErrorOutput());

        $insideProject = $fixture['project'].'/chatme-restore-drill-danger';
        $unsafeTarget = $this->runScript('scripts/ops/restore-drill.php', [
            '--backup='.$backup,
            '--project-root='.$fixture['project'],
            '--target-root='.$insideProject,
            '--confirm=RESTORE-DRILL',
        ]);

        $this->assertNotSame(0, $unsafeTarget->getExitCode());
        $this->assertStringContainsString('outside the project and backup roots', $unsafeTarget->getErrorOutput());
        $this->assertDirectoryDoesNotExist($insideProject);
    }

    #[Test]
    public function mysql_backup_uses_mysql_pwd_instead_of_exposing_the_password_in_arguments_or_artifacts(): void
    {
        $fixture = $this->createProjectFixture();
        $fakeDump = $this->createFakeMysqlDump();
        $environment = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'chatme_fixture',
            'DB_USERNAME' => 'fixture_user',
            'DB_PASSWORD' => 'fixture-password-must-not-leak',
            'CHATME_OPS_MYSQLDUMP_BINARY' => $fakeDump,
        ];

        $process = $this->runScript(
            'scripts/ops/backup.php',
            $this->backupArguments($fixture, $this->temporaryRoot.'/mysql-backups', 'mysql'),
            $environment,
        );

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $backup = $this->decodeOutput($process)['backup'];
        $artifacts = file_get_contents($backup.'/database/database.sql')
            .file_get_contents($backup.'/manifest.json')
            .file_get_contents($backup.'/SHA256SUMS');

        $this->assertStringContainsString('CREATE TABLE fixture', $artifacts);
        $this->assertStringNotContainsString('fixture-password-must-not-leak', $artifacts);
        $this->assertStringNotContainsString('--password', $artifacts);

        $configuredDatabase = 'chatme_restore_drill';
        $restoreTarget = $this->temporaryRoot.'/mysql-restore-drill-danger';
        $refusedRestore = $this->runScript('scripts/ops/restore-drill.php', [
            '--backup='.$backup,
            '--project-root='.base_path(),
            '--target-root='.$restoreTarget,
            '--database='.$configuredDatabase,
            '--confirm=RESTORE-DRILL',
        ], [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => $configuredDatabase,
            'DB_USERNAME' => 'fixture_user',
            'DB_PASSWORD' => 'fixture-password-must-not-leak',
        ]);

        $combinedOutput = $refusedRestore->getOutput().$refusedRestore->getErrorOutput();
        $this->assertNotSame(0, $refusedRestore->getExitCode());
        $this->assertStringContainsString('refuses to target the configured production database', $combinedOutput);
        $this->assertStringNotContainsString('fixture-password-must-not-leak', $combinedOutput);
        $this->assertDirectoryDoesNotExist($restoreTarget);
    }

    /**
     * @return array{project: string, database: string, private: string, public: string, migrations: string}
     */
    private function createProjectFixture(): array
    {
        $project = $this->temporaryRoot.'/project';
        $private = $project.'/storage/app/private';
        $public = $project.'/storage/app/public';
        $database = $project.'/database/source.sqlite';
        $migrations = $project.'/migration-status.txt';

        mkdir($private.'/tenant-1', 0700, true);
        mkdir($public.'/tenant-1', 0700, true);
        mkdir(dirname($database), 0700, true);
        mkdir($project.'/public', 0700, true);
        file_put_contents($private.'/tenant-1/private.txt', 'private fixture');
        file_put_contents($public.'/tenant-1/public.txt', 'public fixture');
        file_put_contents($migrations, "Migration name ................................................ Batch / Status\nfixture ....................................................... [1] Ran\n");

        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE fixtures (name TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO fixtures (name) VALUES (?)');
        $statement->execute(['backup fixture']);
        unset($pdo);

        return compact('project', 'database', 'private', 'public', 'migrations');
    }

    /**
     * @param  array{project: string, database: string, private: string, public: string, migrations: string}  $fixture
     * @return list<string>
     */
    private function backupArguments(array $fixture, string $backupRoot, string $driver): array
    {
        return [
            '--project-root='.$fixture['project'],
            '--backup-root='.$backupRoot,
            '--database-driver='.$driver,
            '--disposable-test-mode',
            '--sqlite-database='.$fixture['database'],
            '--storage-private='.$fixture['private'],
            '--storage-public='.$fixture['public'],
            '--release-sha='.str_repeat('a', 40),
            '--migration-state-file='.$fixture['migrations'],
        ];
    }

    /**
     * @param  array{project: string, database: string, private: string, public: string, migrations: string}  $fixture
     */
    private function createBackup(array $fixture, string $driver): string
    {
        $process = $this->runScript(
            'scripts/ops/backup.php',
            $this->backupArguments($fixture, $this->temporaryRoot.'/backups', $driver),
        );

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return $this->decodeOutput($process)['backup'];
    }

    private function createFakeMysqlDump(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = $this->temporaryRoot.'/fake-mysqldump.cmd';
            file_put_contents($path, <<<'BAT'
@echo off
if "%MYSQL_PWD%"=="" exit /b 41
echo -- fake MySQL dump
echo CREATE TABLE fixture ^(id INT^);
BAT);

            return $path;
        }

        $path = $this->temporaryRoot.'/fake-mysqldump';
        file_put_contents($path, <<<'SH'
#!/usr/bin/env sh
[ -n "$MYSQL_PWD" ] || exit 41
printf '%s\n' '-- fake MySQL dump' 'CREATE TABLE fixture (id INT);'
SH);
        chmod($path, 0700);

        return $path;
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
            30,
        );
        $process->run();

        return $process;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOutput(Process $process): array
    {
        $decoded = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
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
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
