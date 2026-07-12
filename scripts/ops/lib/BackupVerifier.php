<?php

declare(strict_types=1);

namespace ChatMe\Ops;

use JsonException;

final class BackupVerifier
{
    /**
     * @return array{root: string, manifest: array<string, mixed>, files: int}
     */
    public function verify(string $backup): array
    {
        $root = Path::canonicalExisting($backup);
        if (! is_dir($root) || is_link($root)) {
            throw new OpsException('Backup must be a regular directory.');
        }

        $checksumsFile = $root.'/SHA256SUMS';
        if (! is_file($checksumsFile) || is_link($checksumsFile)) {
            throw new OpsException('Backup is missing SHA256SUMS.');
        }

        $checksums = $this->parseChecksums($checksumsFile);
        $actualFiles = SecureFilesystem::files($root, true);
        $expectedFiles = array_keys($checksums);
        sort($expectedFiles, SORT_STRING);

        if ($actualFiles !== $expectedFiles) {
            throw new OpsException('Backup file inventory does not match SHA256SUMS.');
        }

        foreach ($checksums as $relative => $expected) {
            $actual = hash_file('sha256', $root.'/'.$relative);
            if ($actual === false || ! hash_equals($expected, $actual)) {
                throw new OpsException('Checksum mismatch for '.$relative.'.');
            }
        }

        $manifest = $this->readManifest($root.'/manifest.json');
        $this->validateManifest($manifest, $checksums);

        return [
            'root' => $root,
            'manifest' => $manifest,
            'files' => count($actualFiles),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseChecksums(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lines === []) {
            throw new OpsException('SHA256SUMS is empty or unreadable.');
        }

        $checksums = [];
        foreach ($lines as $line) {
            if (preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $match) !== 1) {
                throw new OpsException('SHA256SUMS contains an invalid entry.');
            }

            $relative = $match[2];
            Path::assertSafeRelative($relative);
            if ($relative === 'SHA256SUMS' || isset($checksums[$relative])) {
                throw new OpsException('SHA256SUMS contains a duplicate or recursive entry.');
            }

            $checksums[$relative] = $match[1];
        }

        ksort($checksums, SORT_STRING);

        return $checksums;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $file): array
    {
        if (! is_file($file) || is_link($file)) {
            throw new OpsException('Backup is missing manifest.json.');
        }

        try {
            $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OpsException('Backup manifest is invalid JSON.');
        }

        if (! is_array($manifest)) {
            throw new OpsException('Backup manifest must be a JSON object.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $checksums
     */
    private function validateManifest(array $manifest, array $checksums): void
    {
        if (($manifest['format_version'] ?? null) !== 1 || ($manifest['application'] ?? null) !== 'ChatMe') {
            throw new OpsException('Backup manifest format is unsupported.');
        }

        if (preg_match('/^[a-f0-9]{40}$/', (string) ($manifest['release_sha'] ?? '')) !== 1) {
            throw new OpsException('Backup manifest release SHA is invalid.');
        }

        $database = $manifest['database'] ?? null;
        if (! is_array($database) || ! in_array($database['driver'] ?? null, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new OpsException('Backup manifest database section is invalid.');
        }
        $databaseArtifact = (string) ($database['artifact'] ?? '');
        Path::assertSafeRelative($databaseArtifact);
        if (! isset($checksums[$databaseArtifact])) {
            throw new OpsException('Backup manifest database artifact is missing.');
        }

        $migrationState = (string) ($manifest['migration_state'] ?? '');
        Path::assertSafeRelative($migrationState);
        if (! isset($checksums[$migrationState]) || ! isset($checksums['metadata/release-sha.txt'])) {
            throw new OpsException('Backup manifest metadata artifacts are missing.');
        }

        $files = $manifest['files'] ?? null;
        if (! is_array($files)) {
            throw new OpsException('Backup manifest file inventory is invalid.');
        }

        $inventory = [];
        foreach ($files as $entry) {
            if (! is_array($entry)) {
                throw new OpsException('Backup manifest contains an invalid file entry.');
            }

            $relative = (string) ($entry['path'] ?? '');
            Path::assertSafeRelative($relative);
            if ($relative === 'manifest.json' || isset($inventory[$relative])) {
                throw new OpsException('Backup manifest contains a duplicate file entry.');
            }
            if (! isset($checksums[$relative])) {
                throw new OpsException('Backup manifest references an unverified file.');
            }
            if (
                ! is_int($entry['size'] ?? null)
                || ($entry['size'] ?? -1) < 0
                || preg_match('/^[a-f0-9]{64}$/', (string) ($entry['sha256'] ?? '')) !== 1
                || ! hash_equals($checksums[$relative], (string) $entry['sha256'])
            ) {
                throw new OpsException('Backup manifest file metadata is invalid.');
            }

            $inventory[$relative] = true;
        }

        $payload = array_diff(array_keys($checksums), ['manifest.json']);
        sort($payload, SORT_STRING);
        $inventoryPaths = array_keys($inventory);
        sort($inventoryPaths, SORT_STRING);
        if ($payload !== $inventoryPaths) {
            throw new OpsException('Backup manifest file inventory is incomplete.');
        }

        $this->assertNoSensitiveKeys($manifest);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function assertNoSensitiveKeys(array $value): void
    {
        foreach ($value as $key => $child) {
            if (is_string($key) && preg_match('/(?:password|secret|token|api[_-]?key|app[_-]?key)/i', $key) === 1) {
                throw new OpsException('Backup manifest contains a prohibited sensitive field.');
            }

            if (is_array($child)) {
                $this->assertNoSensitiveKeys($child);
            }
        }
    }
}
