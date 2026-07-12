<?php

declare(strict_types=1);

namespace ChatMe\Ops;

use JsonException;

final class DeploymentOptions
{
    private function __construct(
        public readonly string $projectRoot,
        public readonly string $stateRoot,
        public readonly string $webRoot,
        public readonly ?string $backup,
        public readonly ?string $targetSha,
        public readonly ?string $deploymentId,
        public readonly string $remote,
        public readonly ?string $releaseRef,
        public readonly string $actor,
        public readonly int $maxProcesses,
        public readonly int $commandTimeout,
    ) {}

    /** @param array<string, mixed> $options */
    public static function deployment(array $options): self
    {
        $targetSha = self::sha(option($options, 'target-sha'));
        $backup = option($options, 'backup');
        $releaseRef = self::gitName(option($options, 'release-ref'), '--release-ref');

        if ($backup === null) {
            throw new OpsException('--backup is required.');
        }

        return self::common(
            $options,
            Path::canonicalExisting($backup),
            $targetSha,
            null,
            $releaseRef,
        );
    }

    /** @param array<string, mixed> $options */
    public static function rollback(array $options): self
    {
        $deploymentId = option($options, 'deployment-id');
        if ($deploymentId === null || preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{8}$/', $deploymentId) !== 1) {
            throw new OpsException('--deployment-id must be a recorded deployment identifier.');
        }

        return self::common($options, null, null, $deploymentId, null);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function common(
        array $options,
        ?string $backup,
        ?string $targetSha,
        ?string $deploymentId,
        ?string $releaseRef,
    ): self {
        $projectRoot = Path::canonicalExisting(
            option($options, 'project-root', dirname(__DIR__, 3)) ?? dirname(__DIR__, 3),
        );
        $webRoot = Path::canonicalForCreation(option($options, 'web-root', $projectRoot) ?? $projectRoot);
        $stateOption = option($options, 'state-root');
        if ($stateOption === null) {
            throw new OpsException('--state-root is required and must be outside the web root.');
        }
        $stateRoot = Path::canonicalForCreation($stateOption);

        if (Path::isWithin($stateRoot, $projectRoot) || Path::isWithin($stateRoot, $webRoot)) {
            throw new OpsException('Deployment state must be outside the project and web roots.');
        }
        if ($backup !== null && (Path::isWithin($backup, $projectRoot) || Path::isWithin($backup, $webRoot))) {
            throw new OpsException('Deployment backup must be outside the project and web roots.');
        }

        $remote = self::gitName(option($options, 'remote', 'origin'), '--remote');
        $actor = option($options, 'actor', get_current_user()) ?? get_current_user();
        if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $actor) !== 1) {
            throw new OpsException('--actor must be a short operating-system account identifier.');
        }

        $maxProcessesValue = option($options, 'max-processes', '80') ?? '80';
        if (preg_match('/^[1-9]\d{0,4}$/', $maxProcessesValue) !== 1) {
            throw new OpsException('--max-processes must be a positive integer.');
        }
        $commandTimeoutValue = option($options, 'command-timeout', '900') ?? '900';
        if (preg_match('/^[1-9]\d{0,3}$/', $commandTimeoutValue) !== 1 || (int) $commandTimeoutValue > 3600) {
            throw new OpsException('--command-timeout must be between 1 and 3600 seconds.');
        }

        return new self(
            $projectRoot,
            $stateRoot,
            $webRoot,
            $backup,
            $targetSha,
            $deploymentId,
            $remote,
            $releaseRef,
            $actor,
            (int) $maxProcessesValue,
            (int) $commandTimeoutValue,
        );
    }

    private static function sha(?string $value): string
    {
        if ($value === null || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
            throw new OpsException('--target-sha must be a full lowercase 40-character Git SHA.');
        }

        return $value;
    }

    private static function gitName(?string $value, string $optionName): string
    {
        if (
            $value === null
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,127}$/', $value) !== 1
            || str_contains($value, '..')
            || str_ends_with($value, '/')
        ) {
            throw new OpsException($optionName.' contains unsupported Git reference characters.');
        }

        return $value;
    }
}

final class DeploymentLock
{
    /** @param resource $handle */
    private function __construct(private $handle) {}

    public static function acquire(string $stateRoot): self
    {
        SecureFilesystem::makeDirectory($stateRoot);
        $file = $stateRoot.'/.chatme-deploy.lock';
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new OpsException('Unable to open the deployment lock.');
        }
        @chmod($file, 0600);

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new OpsException('Another deployment operation is active.');
        }

        return new self($handle);
    }

    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}

final class DeploymentCommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
    ) {}
}

final class DeploymentCommandRunner
{
    public function __construct(
        private readonly string $operation = 'Deployment',
        private readonly int $commandTimeout = 900,
    ) {
        if (! in_array($this->operation, ['Deployment', 'Rollback'], true)) {
            throw new OpsException('Unsupported deployment operation label.');
        }
        if ($this->commandTimeout < 1 || $this->commandTimeout > 3600) {
            throw new OpsException('Deployment command timeout must be between 1 and 3600 seconds.');
        }
    }

    /** @param list<string> $command */
    public function run(array $command, string $cwd, string $phase, bool $allowFailure = false): DeploymentCommandResult
    {
        $timeoutSeconds = $this->commandTimeout;
        $stdoutHandle = tmpfile();
        if ($stdoutHandle === false) {
            throw new OpsException('Unable to allocate secure temporary command output.');
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => $stdoutHandle,
            2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'ab'],
        ];
        $process = proc_open(
            $this->platformCommand($command),
            $descriptors,
            $pipes,
            $cwd,
            $this->environment(),
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            fclose($stdoutHandle);
            throw new OpsException('Unable to start deployment command during '.$phase.'.');
        }

        fclose($pipes[0]);
        $startedAt = microtime(true);
        $timedOut = false;
        $knownExitCode = null;

        while (true) {
            $status = proc_get_status($process);
            if (! is_array($status) || $status['running'] === false) {
                $knownExitCode = is_array($status) ? (int) $status['exitcode'] : null;

                break;
            }

            if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process);

                $terminationDeadline = microtime(true) + 2.0;
                do {
                    usleep(50_000);
                    $status = proc_get_status($process);
                } while (is_array($status) && $status['running'] === true && microtime(true) < $terminationDeadline);

                if (is_array($status) && $status['running'] === true) {
                    proc_terminate($process, 9);
                }

                break;
            }

            usleep(50_000);
        }

        $exitCode = proc_close($process);
        if ($knownExitCode !== null && $knownExitCode >= 0) {
            $exitCode = $knownExitCode;
        }
        if ($timedOut) {
            fclose($stdoutHandle);
            throw new OpsException($this->operation.' command timed out during '.$phase.'.');
        }
        rewind($stdoutHandle);
        $stdout = stream_get_contents($stdoutHandle);
        fclose($stdoutHandle);
        $result = new DeploymentCommandResult($exitCode, trim($stdout === false ? '' : $stdout));

        if (! $allowFailure && $exitCode !== 0) {
            throw new OpsException($this->operation.' command failed during '.$phase.'.');
        }

        return $result;
    }

    /** @param list<string> $command @return list<string>|string */
    private function platformCommand(array $command): array|string
    {
        if (
            PHP_OS_FAMILY === 'Windows'
            && isset($command[0])
            && preg_match('/\.(?:cmd|bat)$/i', $command[0]) === 1
        ) {
            return array_merge(['cmd.exe', '/D', '/S', '/C'], $command);
        }

        return $command;
    }

    /** @return array<string, string> */
    private function environment(): array
    {
        $environment = getenv();

        return is_array($environment) ? $environment : [];
    }
}

final class GitReleaseRepository
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly DeploymentCommandRunner $runner,
    ) {}

    public function head(): string
    {
        $head = $this->runner->run(
            ['git', 'rev-parse', '--verify', 'HEAD'],
            $this->projectRoot,
            'reading current Git SHA',
        )->output;

        if (preg_match('/^[a-f0-9]{40}$/', $head) !== 1) {
            throw new OpsException('Current Git HEAD is not an exact commit SHA.');
        }

        return $head;
    }

    public function assertClean(): void
    {
        $status = $this->runner->run(
            ['git', 'status', '--porcelain=v1', '--untracked-files=normal'],
            $this->projectRoot,
            'checking the working tree',
        )->output;

        if ($status !== '') {
            throw new OpsException('Git working tree must be clean before deployment.');
        }
    }

    public function fetch(string $remote): void
    {
        $this->runner->run(
            ['git', 'fetch', '--prune', $remote],
            $this->projectRoot,
            'fetching the approved remote',
        );
    }

    public function assertApprovedTarget(string $targetSha, string $remote, string $releaseRef): void
    {
        $remoteRef = $remote.'/'.$releaseRef;
        $resolved = $this->runner->run(
            ['git', 'rev-parse', '--verify', $remoteRef.'^{commit}'],
            $this->projectRoot,
            'resolving the approved remote release ref',
            true,
        );
        $reachable = $this->runner->run(
            ['git', 'merge-base', '--is-ancestor', $targetSha, $remoteRef],
            $this->projectRoot,
            'checking target reachability',
            true,
        );

        if ($resolved->exitCode !== 0 || $reachable->exitCode !== 0) {
            throw new OpsException('Target SHA is not reachable from the approved remote release ref.');
        }
    }

    public function assertForwardDeployment(string $previousSha, string $targetSha): void
    {
        $forward = $this->runner->run(
            ['git', 'merge-base', '--is-ancestor', $previousSha, $targetSha],
            $this->projectRoot,
            'checking forward deployment ancestry',
            true,
        );

        if ($previousSha === $targetSha || $forward->exitCode !== 0) {
            throw new OpsException('Normal deployment target must be a forward descendant of current HEAD.');
        }
    }

    public function switchDetached(string $sha): void
    {
        $this->runner->run(
            ['git', 'switch', '--detach', $sha],
            $this->projectRoot,
            'switching exact Git release',
        );

        if ($this->head() !== $sha) {
            throw new OpsException('Git did not switch to the requested exact SHA.');
        }
    }
}

final class DeploymentCapacityGuard
{
    public function __construct(private readonly DeploymentCommandRunner $runner) {}

    public function assertAvailable(string $projectRoot, int $maxProcesses): void
    {
        $binary = (string) (getenv('CHATME_OPS_PS_BINARY') ?: 'ps');
        $output = $this->runner->run(
            [$binary, '-u', get_current_user(), '-o', 'pid='],
            $projectRoot,
            'checking process capacity',
        )->output;
        $processes = $output === '' ? 0 : count(preg_split('/\R/', $output) ?: []);

        if ($processes >= $maxProcesses) {
            throw new OpsException('Process count is at or above the deployment safety threshold.');
        }
    }
}

final class DeploymentStateStore
{
    private readonly string $key;

    public function __construct(private readonly string $stateRoot)
    {
        SecureFilesystem::makeDirectory($stateRoot);
        SecureFilesystem::makeDirectory($stateRoot.'/deployments');
        $keyFile = $stateRoot.'/signing.key';

        if (! file_exists($keyFile)) {
            SecureFilesystem::write($keyFile, bin2hex(random_bytes(32))."\n");
        }
        if (! is_file($keyFile) || is_link($keyFile)) {
            throw new OpsException('Deployment state signing key is invalid.');
        }

        $encoded = trim((string) file_get_contents($keyFile));
        $key = preg_match('/^[a-f0-9]{64}$/', $encoded) === 1 ? hex2bin($encoded) : false;
        if ($key === false) {
            throw new OpsException('Deployment state signing key is invalid.');
        }
        $this->key = $key;
    }

    /** @param array<string, scalar|null> $metadata */
    public function create(array $metadata): string
    {
        $deploymentId = gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
        $directory = $this->stateRoot.'/deployments/'.$deploymentId;
        SecureFilesystem::makeDirectory($directory);
        $payload = ['format_version' => 1, 'deployment_id' => $deploymentId] + $metadata;
        $envelope = ['payload' => $payload, 'hmac_sha256' => $this->sign($payload)];
        SecureFilesystem::write($directory.'/metadata.json', $this->json($envelope)."\n");

        return $deploymentId;
    }

    /** @return array<string, mixed> */
    public function read(string $deploymentId): array
    {
        $this->assertDeploymentId($deploymentId);
        $file = $this->stateRoot.'/deployments/'.$deploymentId.'/metadata.json';
        $envelope = $this->decodeFile($file);
        $payload = $envelope['payload'] ?? null;
        $hmac = $envelope['hmac_sha256'] ?? null;

        if (! is_array($payload) || ! is_string($hmac) || ! hash_equals($this->sign($payload), $hmac)) {
            throw new OpsException('Deployment state metadata failed integrity verification.');
        }
        if (($payload['deployment_id'] ?? null) !== $deploymentId) {
            throw new OpsException('Deployment state identifier does not match its record.');
        }

        $this->readEvents($deploymentId);

        return $payload;
    }

    public function appendEvent(
        string $deploymentId,
        string $operation,
        string $phase,
        string $status,
        ?string $actor = null,
    ): void {
        $this->assertDeploymentId($deploymentId);
        if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $operation) !== 1
            || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $phase) !== 1
            || ! in_array($status, ['started', 'ok', 'failed'], true)) {
            throw new OpsException('Deployment state event is invalid.');
        }
        if ($actor !== null && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $actor) !== 1) {
            throw new OpsException('Deployment state event actor is invalid.');
        }

        $events = $this->readEvents($deploymentId);
        $previousHmac = $events === [] ? str_repeat('0', 64) : $events[array_key_last($events)]['hmac_sha256'];
        $payload = [
            'sequence' => count($events) + 1,
            'at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'operation' => $operation,
            'phase' => $phase,
            'status' => $status,
            'previous_hmac' => $previousHmac,
        ];
        if ($actor !== null) {
            $payload['actor'] = $actor;
        }
        $envelope = ['payload' => $payload, 'hmac_sha256' => $this->sign($payload)];
        $file = $this->stateRoot.'/deployments/'.$deploymentId.'/events.jsonl';
        if (file_put_contents($file, $this->json($envelope)."\n", FILE_APPEND | LOCK_EX) === false) {
            throw new OpsException('Unable to append deployment state event.');
        }
        @chmod($file, 0600);
    }

    /** @return list<array<string, mixed>> */
    public function events(string $deploymentId): array
    {
        return $this->readEvents($deploymentId);
    }

    /** @return list<array<string, mixed>> */
    private function readEvents(string $deploymentId): array
    {
        $file = $this->stateRoot.'/deployments/'.$deploymentId.'/events.jsonl';
        if (! file_exists($file)) {
            return [];
        }
        if (! is_file($file) || is_link($file)) {
            throw new OpsException('Deployment state event log is invalid.');
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new OpsException('Deployment state event log is unreadable.');
        }

        $events = [];
        $previousHmac = str_repeat('0', 64);
        foreach ($lines as $index => $line) {
            try {
                $envelope = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new OpsException('Deployment state event log contains invalid JSON.');
            }
            $payload = is_array($envelope) ? ($envelope['payload'] ?? null) : null;
            $hmac = is_array($envelope) ? ($envelope['hmac_sha256'] ?? null) : null;
            if (
                ! is_array($payload)
                || ! is_string($hmac)
                || ($payload['sequence'] ?? null) !== $index + 1
                || ($payload['previous_hmac'] ?? null) !== $previousHmac
                || ! hash_equals($this->sign($payload), $hmac)
            ) {
                throw new OpsException('Deployment state event log failed integrity verification.');
            }
            $events[] = ['payload' => $payload, 'hmac_sha256' => $hmac];
            $previousHmac = $hmac;
        }

        return $events;
    }

    /** @param array<mixed> $payload */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->json($payload), $this->key);
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decodeFile(string $file): array
    {
        if (! is_file($file) || is_link($file)) {
            throw new OpsException('Recorded deployment does not exist.');
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OpsException('Deployment state metadata contains invalid JSON.');
        }
        if (! is_array($decoded)) {
            throw new OpsException('Deployment state metadata is invalid.');
        }

        return $decoded;
    }

    private function assertDeploymentId(string $deploymentId): void
    {
        if (preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{8}$/', $deploymentId) !== 1) {
            throw new OpsException('Deployment identifier is invalid.');
        }
    }
}
