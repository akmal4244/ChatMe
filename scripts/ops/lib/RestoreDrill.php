<?php

declare(strict_types=1);

namespace ChatMe\Ops;

final class RestoreDrill
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{target: string, driver: string, files: int, release_sha: string}
     */
    public function restore(array $options): array
    {
        if (option($options, 'confirm') !== 'RESTORE-DRILL') {
            throw new OpsException('Restore drill requires --confirm=RESTORE-DRILL.');
        }

        $backupOption = option($options, 'backup');
        $targetOption = option($options, 'target-root');
        if ($backupOption === null || $targetOption === null) {
            throw new OpsException('--backup and --target-root are required.');
        }

        $projectRoot = Path::canonicalExisting(option($options, 'project-root', dirname(__DIR__, 3)) ?? dirname(__DIR__, 3));
        $backup = (new BackupVerifier)->verify($backupOption);
        $target = Path::canonicalForCreation($targetOption);

        if (file_exists($target) || is_link($target)) {
            throw new OpsException('Restore drill target must not already exist.');
        }
        if (stripos(basename(str_replace('/', DIRECTORY_SEPARATOR, $target)), 'restore-drill') === false) {
            throw new OpsException('Restore drill target name must contain restore-drill.');
        }
        if (Path::isWithin($target, $projectRoot) || Path::isWithin($target, $backup['root'])) {
            throw new OpsException('Restore drill target must be outside the project and backup roots.');
        }

        $manifest = $backup['manifest'];
        /** @var array{driver: string, artifact: string} $database */
        $database = $manifest['database'];
        $driver = $database['driver'];
        $databaseArtifact = $backup['root'].'/'.$database['artifact'];
        SecureFilesystem::makeDirectory($target);

        try {
            if ($driver === 'sqlite') {
                SecureFilesystem::copyFile($databaseArtifact, $target.'/database/database.sqlite');
            } else {
                $targetDatabase = option($options, 'database');
                if (
                    $targetDatabase === null
                    || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_$.-]*$/', $targetDatabase) !== 1
                    || preg_match('/(?:^restore_drill_|_restore_drill(?:_|$))/i', $targetDatabase) !== 1
                ) {
                    throw new OpsException('MySQL/MariaDB restore drill database name must contain _restore_drill.');
                }

                $connection = DatabaseConfiguration::resolve($projectRoot, [], false);
                if (! in_array($connection['driver'], ['mysql', 'mariadb'], true)) {
                    throw new OpsException('Laravel must use MySQL/MariaDB for this restore drill.');
                }
                if (strcasecmp((string) $connection['database'], $targetDatabase) === 0) {
                    throw new OpsException('Restore drill refuses to target the configured production database.');
                }

                DatabaseArtifact::importMysql($connection, $targetDatabase, $databaseArtifact);
            }

            $privateCount = SecureFilesystem::copyTree(
                $backup['root'].'/storage/app/private',
                $target.'/storage/app/private',
            );
            $publicCount = SecureFilesystem::copyTree(
                $backup['root'].'/storage/app/public',
                $target.'/storage/app/public',
            );
            $this->verifyRestoredFiles($backup['root'], $target, $manifest, $driver);

            $report = [
                'format_version' => 1,
                'status' => 'restored',
                'completed_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'release_sha' => (string) $manifest['release_sha'],
                'database_driver' => $driver,
                'storage_files_restored' => $privateCount + $publicCount,
            ];
            SecureFilesystem::write(
                $target.'/restore-report.json',
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            );

            return [
                'target' => str_replace('\\', '/', $target),
                'driver' => $driver,
                'files' => $privateCount + $publicCount + 1,
                'release_sha' => (string) $manifest['release_sha'],
            ];
        } catch (\Throwable $exception) {
            SecureFilesystem::deleteTree($target);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function verifyRestoredFiles(string $backup, string $target, array $manifest, string $driver): void
    {
        /** @var list<array{path: string, size: int, sha256: string}> $files */
        $files = $manifest['files'];
        foreach ($files as $entry) {
            $sourcePath = $entry['path'];
            if (str_starts_with($sourcePath, 'storage/app/')) {
                $restored = $target.'/'.$sourcePath;
            } elseif ($driver === 'sqlite' && $sourcePath === 'database/database.sqlite') {
                $restored = $target.'/database/database.sqlite';
            } else {
                continue;
            }

            $hash = is_file($restored) ? hash_file('sha256', $restored) : false;
            if ($hash === false || ! hash_equals($entry['sha256'], $hash)) {
                throw new OpsException('Restored artifact failed checksum verification.');
            }

            $sourceHash = hash_file('sha256', $backup.'/'.$sourcePath);
            if ($sourceHash === false || ! hash_equals($sourceHash, $hash)) {
                throw new OpsException('Restored artifact does not match the verified backup.');
            }
        }
    }
}
