<?php

declare(strict_types=1);

namespace ChatMe\Ops;

final class BackupCreator
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{backup: string, files: int, release_sha: string}
     */
    public function create(array $options): array
    {
        $projectRoot = Path::canonicalExisting(option($options, 'project-root', dirname(__DIR__, 3)) ?? dirname(__DIR__, 3));
        $backupRootOption = option($options, 'backup-root');
        if ($backupRootOption === null) {
            throw new OpsException('--backup-root is required and must be outside the web root.');
        }

        $backupRoot = Path::canonicalForCreation($backupRootOption);
        $publicRoot = Path::canonicalForCreation($projectRoot.'/public');
        $additionalWebRoot = option($options, 'web-root');

        if (
            Path::isWithin($backupRoot, $projectRoot)
            || Path::isWithin($backupRoot, $publicRoot)
            || ($additionalWebRoot !== null && Path::isWithin($backupRoot, Path::canonicalForCreation($additionalWebRoot)))
        ) {
            throw new OpsException('Backup root must be outside the project and public web roots.');
        }

        SecureFilesystem::makeDirectory($backupRoot);
        $testMode = array_key_exists('disposable-test-mode', $options);
        $identifier = gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
        $working = $backupRoot.'/.partial-'.$identifier;
        $final = $backupRoot.'/chatme-'.$identifier;
        SecureFilesystem::makeDirectory($working);

        try {
            $database = DatabaseConfiguration::resolve($projectRoot, $options, $testMode);
            $databaseFile = DatabaseArtifact::export($database, $working);
            $releaseSha = $this->writeReleaseSha($working, $projectRoot, $options, $testMode);
            $this->writeMigrationState($working, $projectRoot, $options, $testMode);

            $privateSource = $testMode && option($options, 'storage-private') !== null
                ? Path::absolute((string) option($options, 'storage-private'), $projectRoot)
                : $projectRoot.'/storage/app/private';
            $publicSource = $testMode && option($options, 'storage-public') !== null
                ? Path::absolute((string) option($options, 'storage-public'), $projectRoot)
                : $projectRoot.'/storage/app/public';
            $privateCount = SecureFilesystem::copyTree($privateSource, $working.'/storage/app/private');
            $publicCount = SecureFilesystem::copyTree($publicSource, $working.'/storage/app/public');

            $inventory = $this->inventory($working);
            $manifest = [
                'format_version' => 1,
                'application' => 'ChatMe',
                'created_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'release_sha' => $releaseSha,
                'database' => [
                    'driver' => (string) $database['driver'],
                    'artifact' => $databaseFile,
                ],
                'migration_state' => 'metadata/migrations.txt',
                'storage' => [
                    'private' => ['path' => 'storage/app/private', 'files' => $privateCount],
                    'public' => ['path' => 'storage/app/public', 'files' => $publicCount],
                ],
                'files' => $inventory,
            ];
            SecureFilesystem::write(
                $working.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            );
            $this->writeChecksums($working);

            if (! rename($working, $final)) {
                throw new OpsException('Unable to atomically finalize the backup.');
            }
            @chmod($final, 0700);

            return [
                'backup' => str_replace('\\', '/', $final),
                'files' => count(SecureFilesystem::files($final, true)),
                'release_sha' => $releaseSha,
            ];
        } catch (\Throwable $exception) {
            SecureFilesystem::deleteTree($working);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function writeReleaseSha(string $working, string $projectRoot, array $options, bool $testMode): string
    {
        $releaseSha = $testMode ? option($options, 'release-sha') : null;
        if ($releaseSha === null) {
            $releaseSha = ProcessRunner::capture(['git', '-C', $projectRoot, 'rev-parse', 'HEAD']);
        }

        $releaseSha = strtolower(trim($releaseSha));
        if (preg_match('/^[a-f0-9]{40}$/', $releaseSha) !== 1) {
            throw new OpsException('Release SHA must be a full 40-character Git commit hash.');
        }

        SecureFilesystem::write($working.'/metadata/release-sha.txt', $releaseSha."\n");

        return $releaseSha;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function writeMigrationState(string $working, string $projectRoot, array $options, bool $testMode): void
    {
        $fixture = $testMode ? option($options, 'migration-state-file') : null;
        if ($fixture !== null) {
            SecureFilesystem::copyFile(Path::absolute($fixture, $projectRoot), $working.'/metadata/migrations.txt');

            return;
        }

        if (! is_file($projectRoot.'/artisan')) {
            throw new OpsException('Laravel artisan is required to capture migration state.');
        }

        ProcessRunner::toFile(
            [PHP_BINARY, $projectRoot.'/artisan', 'migrate:status', '--no-ansi'],
            $working.'/metadata/migrations.txt',
        );
    }

    /**
     * @return list<array{path: string, size: int, sha256: string}>
     */
    private function inventory(string $working): array
    {
        $inventory = [];
        foreach (SecureFilesystem::files($working) as $relative) {
            $file = $working.'/'.$relative;
            $size = filesize($file);
            $hash = hash_file('sha256', $file);
            if ($size === false || $hash === false) {
                throw new OpsException('Unable to inventory a backup artifact.');
            }

            $inventory[] = [
                'path' => $relative,
                'size' => $size,
                'sha256' => $hash,
            ];
        }

        return $inventory;
    }

    private function writeChecksums(string $working): void
    {
        $lines = [];
        foreach (SecureFilesystem::files($working, true) as $relative) {
            $hash = hash_file('sha256', $working.'/'.$relative);
            if ($hash === false) {
                throw new OpsException('Unable to checksum a backup artifact.');
            }

            $lines[] = $hash.'  '.$relative;
        }

        SecureFilesystem::write($working.'/SHA256SUMS', implode("\n", $lines)."\n");
    }
}
