<?php

declare(strict_types=1);

namespace ChatMe\Ops;

final class DeploymentManager
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{deployment_id:string,previous_sha:string,target_sha:string,status:string}
     */
    public function deploy(array $options): array
    {
        $deployment = DeploymentOptions::deployment($options);
        $lock = DeploymentLock::acquire($deployment->stateRoot);
        $deploymentId = null;
        $maintenanceAttempted = false;
        $switched = false;
        $error = null;
        $previousSha = '';

        try {
            $runner = new DeploymentCommandRunner('Deployment', $deployment->commandTimeout);
            $repository = new GitReleaseRepository($deployment->projectRoot, $runner);
            $repository->assertClean();
            $previousSha = $repository->head();
            $backup = (new BackupVerifier)->verify((string) $deployment->backup);

            if (($backup['manifest']['release_sha'] ?? null) !== $previousSha) {
                throw new OpsException('Backup release SHA does not match current HEAD.');
            }

            $repository->fetch($deployment->remote);
            $repository->assertApprovedTarget(
                (string) $deployment->targetSha,
                $deployment->remote,
                (string) $deployment->releaseRef,
            );
            $repository->assertForwardDeployment($previousSha, (string) $deployment->targetSha);
            (new DeploymentCapacityGuard($runner))->assertAvailable(
                $deployment->projectRoot,
                $deployment->maxProcesses,
            );

            $state = new DeploymentStateStore($deployment->stateRoot);
            $deploymentId = $state->create([
                'operation' => 'deployment',
                'created_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'previous_sha' => $previousSha,
                'target_sha' => (string) $deployment->targetSha,
                'backup_path' => str_replace('\\', '/', $backup['root']),
                'remote' => $deployment->remote,
                'release_ref' => (string) $deployment->releaseRef,
                'actor' => $deployment->actor,
            ]);
            $state->appendEvent($deploymentId, 'deployment', 'preflight', 'ok');

            try {
                $maintenanceAttempted = true;
                $this->phase($state, $deploymentId, 'maintenance_down', function () use ($deployment, $runner): void {
                    $runner->run(
                        $this->artisan($deployment, ['down', '--retry=30', '--refresh=15']),
                        $deployment->projectRoot,
                        'entering Laravel maintenance mode',
                    );
                });
                $this->phase($state, $deploymentId, 'checkout', function () use ($deployment, $repository, &$switched): void {
                    $switched = true;
                    $repository->switchDetached((string) $deployment->targetSha);
                });
                $this->installDependencies($deployment, $runner, $state, $deploymentId);
                $this->phase($state, $deploymentId, 'cache_clear', function () use ($deployment, $runner): void {
                    $runner->run(
                        $this->artisan($deployment, ['optimize:clear']),
                        $deployment->projectRoot,
                        'clearing stale Laravel caches',
                    );
                });
                $this->phase($state, $deploymentId, 'migration', function () use ($deployment, $runner): void {
                    $runner->run(
                        $this->artisan($deployment, ['migrate', '--force', '--no-interaction']),
                        $deployment->projectRoot,
                        'running forward database migrations',
                    );
                });
                $this->buildCaches($deployment, $runner, $state, $deploymentId);
                $this->verifyRelease(
                    $deployment,
                    $runner,
                    $repository,
                    $state,
                    $deploymentId,
                    (string) $deployment->targetSha,
                );
            } catch (\Throwable $exception) {
                $error = $exception;
                if ($switched) {
                    try {
                        $this->safetyPhase($state, $deploymentId, 'automatic_recovery', function () use (
                            $deployment,
                            $runner,
                            $repository,
                            $state,
                            $deploymentId,
                            $previousSha,
                        ): void {
                            $this->recoverDeployment(
                                $deployment,
                                $runner,
                                $repository,
                                $state,
                                $deploymentId,
                                $previousSha,
                                (string) $deployment->targetSha,
                            );
                        });
                    } catch (\Throwable) {
                        // The recovery phase records its own failure. Preserve the original sanitized error.
                    }
                }
            } finally {
                if ($maintenanceAttempted) {
                    try {
                        $this->safetyPhase($state, $deploymentId, 'maintenance_up', function () use ($deployment, $runner): void {
                            $runner->run(
                                $this->artisan($deployment, ['up']),
                                $deployment->projectRoot,
                                'leaving Laravel maintenance mode',
                            );
                        });
                    } catch (\Throwable $exception) {
                        // A failed `up` or unrecordable maintenance exit is more urgent than the earlier phase.
                        $error = $exception;
                    }
                }
            }

            $state->appendEvent(
                $deploymentId,
                'deployment',
                'complete',
                $error === null ? 'ok' : 'failed',
            );
            if ($error !== null) {
                throw $error;
            }

            return [
                'deployment_id' => $deploymentId,
                'previous_sha' => $previousSha,
                'target_sha' => (string) $deployment->targetSha,
                'status' => 'deployed',
            ];
        } finally {
            $lock->release();
        }
    }

    private function installDependencies(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
        bool $recordPhases = true,
    ): void {
        $composer = function () use ($deployment, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_COMPOSER_BINARY') ?: 'composer'),
                'install',
                '--no-dev',
                '--prefer-dist',
                '--no-interaction',
                '--optimize-autoloader',
            ], $deployment->projectRoot, 'installing production Composer dependencies');
        };
        $recordPhases ? $this->phase($state, $deploymentId, 'composer', $composer) : $composer();

        if (! is_file($deployment->projectRoot.'/package-lock.json')) {
            return;
        }

        $npmCi = function () use ($deployment, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_NPM_BINARY') ?: 'npm'),
                'ci',
            ], $deployment->projectRoot, 'installing locked frontend dependencies');
        };
        $build = function () use ($deployment, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_NPM_BINARY') ?: 'npm'),
                'run',
                'build',
            ], $deployment->projectRoot, 'building production frontend assets');
        };
        if ($recordPhases) {
            $this->phase($state, $deploymentId, 'npm_ci', $npmCi);
            $this->phase($state, $deploymentId, 'build', $build);
        } else {
            $npmCi();
            $build();
        }
    }

    private function buildCaches(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
        bool $recordPhase = true,
    ): void {
        $build = function () use ($deployment, $runner): void {
            foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
                $runner->run(
                    $this->artisan($deployment, [$command]),
                    $deployment->projectRoot,
                    'building Laravel '.$command,
                );
            }
        };
        $recordPhase ? $this->phase($state, $deploymentId, 'cache_build', $build) : $build();
    }

    private function verifyRelease(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        GitReleaseRepository $repository,
        DeploymentStateStore $state,
        string $deploymentId,
        string $expectedSha,
    ): void {
        $this->phase($state, $deploymentId, 'verification', function () use (
            $deployment,
            $runner,
            $repository,
            $expectedSha,
        ): void {
            $this->assertReleaseHealthy($deployment, $runner, $repository, $expectedSha);
        });
    }

    private function recoverDeployment(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        GitReleaseRepository $repository,
        DeploymentStateStore $state,
        string $deploymentId,
        string $previousSha,
        string $targetSha,
    ): void {
        $repository->switchDetached($previousSha);
        $this->restoreReleaseRuntime($deployment, $runner, $state, $deploymentId);

        try {
            $this->assertReleaseHealthy($deployment, $runner, $repository, $previousSha);
        } catch (\Throwable) {
            // Forward migrations are never reversed automatically. If old code is incompatible,
            // restore and verify the recorded target code before leaving maintenance mode.
            $repository->switchDetached($targetSha);
            $this->restoreReleaseRuntime($deployment, $runner, $state, $deploymentId);
            $this->assertReleaseHealthy($deployment, $runner, $repository, $targetSha);

            throw new OpsException('Previous release failed forward-schema compatibility; target code was restored.');
        }
    }

    private function restoreReleaseRuntime(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
    ): void {
        $this->installDependencies($deployment, $runner, $state, $deploymentId, false);
        $runner->run(
            $this->artisan($deployment, ['optimize:clear']),
            $deployment->projectRoot,
            'clearing caches during automatic recovery',
        );
        $this->buildCaches($deployment, $runner, $state, $deploymentId, false);
    }

    private function assertReleaseHealthy(
        DeploymentOptions $deployment,
        DeploymentCommandRunner $runner,
        GitReleaseRepository $repository,
        string $expectedSha,
    ): void {
        if ($repository->head() !== $expectedSha) {
            throw new OpsException('Deployment verification found an unexpected Git SHA.');
        }
        $repository->assertClean();
        $runner->run(
            $this->artisan($deployment, ['migrate:status', '--no-ansi']),
            $deployment->projectRoot,
            'checking migration status',
        );
        $runner->run(
            [$this->phpBinary(), '-r', $this->healthCheckCode(), $deployment->projectRoot],
            $deployment->projectRoot,
            'checking the health controller internally',
        );
    }

    /** @param list<string> $arguments @return list<string> */
    private function artisan(DeploymentOptions $deployment, array $arguments): array
    {
        return array_merge([$this->phpBinary(), $deployment->projectRoot.'/artisan'], $arguments);
    }

    private function phpBinary(): string
    {
        return (string) (getenv('CHATME_OPS_PHP_BINARY') ?: PHP_BINARY);
    }

    private function healthCheckCode(): string
    {
        return <<<'PHP'
$root = $argv[1];
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$response = $app->make(\App\Http\Controllers\HealthController::class)();
exit(method_exists($response, 'getStatusCode') && $response->getStatusCode() === 200 ? 0 : 1);
PHP;
    }

    private function phase(
        DeploymentStateStore $state,
        string $deploymentId,
        string $phase,
        callable $operation,
    ): void {
        $state->appendEvent($deploymentId, 'deployment', $phase, 'started');

        try {
            $operation();
            $state->appendEvent($deploymentId, 'deployment', $phase, 'ok');
        } catch (\Throwable $exception) {
            try {
                $state->appendEvent($deploymentId, 'deployment', $phase, 'failed');
            } catch (\Throwable) {
                // Preserve the operational failure; safety cleanup is handled by the outer finally.
            }
            throw $exception;
        }
    }

    private function safetyPhase(
        DeploymentStateStore $state,
        string $deploymentId,
        string $phase,
        callable $operation,
    ): void {
        $stateError = null;
        try {
            $state->appendEvent($deploymentId, 'deployment', $phase, 'started');
        } catch (\Throwable $exception) {
            $stateError = $exception;
        }

        $operationError = null;
        try {
            $operation();
        } catch (\Throwable $exception) {
            $operationError = $exception;
        }

        try {
            $state->appendEvent(
                $deploymentId,
                'deployment',
                $phase,
                $operationError === null ? 'ok' : 'failed',
            );
        } catch (\Throwable $exception) {
            $stateError ??= $exception;
        }

        if ($operationError !== null) {
            throw $operationError;
        }
        if ($stateError !== null) {
            throw $stateError;
        }
    }
}

final class RollbackManager
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{deployment_id:string,previous_sha:string,target_sha:string,status:string}
     */
    public function rollback(array $options): array
    {
        $rollback = DeploymentOptions::rollback($options);
        $lock = DeploymentLock::acquire($rollback->stateRoot);
        $maintenanceAttempted = false;
        $switched = false;
        $error = null;

        try {
            $runner = new DeploymentCommandRunner('Rollback', $rollback->commandTimeout);
            $repository = new GitReleaseRepository($rollback->projectRoot, $runner);
            $state = new DeploymentStateStore($rollback->stateRoot);
            $deploymentId = (string) $rollback->deploymentId;
            $record = $state->read($deploymentId);
            $this->assertSuccessfulDeploymentRecord($record, $state->events($deploymentId));

            $previousSha = $this->recordedSha($record, 'previous_sha');
            $targetSha = $this->recordedSha($record, 'target_sha');
            $backupPath = $this->recordedBackup($record, $rollback);

            $repository->assertClean();
            if ($repository->head() !== $targetSha) {
                throw new OpsException('Rollback current HEAD must match the recorded deployment target SHA.');
            }

            $backup = (new BackupVerifier)->verify($backupPath);
            if (($backup['manifest']['release_sha'] ?? null) !== $previousSha) {
                throw new OpsException('Rollback backup release SHA does not match the recorded previous SHA.');
            }

            (new DeploymentCapacityGuard($runner))->assertAvailable(
                $rollback->projectRoot,
                $rollback->maxProcesses,
            );
            $state->appendEvent($deploymentId, 'rollback', 'preflight', 'ok', $rollback->actor);

            try {
                $maintenanceAttempted = true;
                $this->phase($state, $deploymentId, $rollback->actor, 'maintenance_down', function () use ($rollback, $runner): void {
                    $runner->run(
                        $this->artisan($rollback, ['down', '--retry=30', '--refresh=15']),
                        $rollback->projectRoot,
                        'entering Laravel maintenance mode',
                    );
                });
                $this->phase($state, $deploymentId, $rollback->actor, 'checkout', function () use (
                    $repository,
                    $previousSha,
                    &$switched,
                ): void {
                    $switched = true;
                    $repository->switchDetached($previousSha);
                });
                $this->installDependencies($rollback, $runner, $state, $deploymentId);
                $this->phase($state, $deploymentId, $rollback->actor, 'cache_clear', function () use ($rollback, $runner): void {
                    $runner->run(
                        $this->artisan($rollback, ['optimize:clear']),
                        $rollback->projectRoot,
                        'clearing stale Laravel caches',
                    );
                });
                $this->buildCaches($rollback, $runner, $state, $deploymentId);
                $this->verifyRelease(
                    $rollback,
                    $runner,
                    $repository,
                    $state,
                    $deploymentId,
                    $previousSha,
                );
            } catch (\Throwable $exception) {
                $error = $exception;
                if ($switched) {
                    try {
                        $this->safetyPhase($state, $deploymentId, $rollback->actor, 'automatic_recovery', function () use (
                            $rollback,
                            $runner,
                            $repository,
                            $state,
                            $deploymentId,
                            $targetSha,
                        ): void {
                            $repository->switchDetached($targetSha);
                            $this->restoreReleaseRuntime($rollback, $runner, $state, $deploymentId);
                            $this->assertReleaseHealthy($rollback, $runner, $repository, $targetSha);
                        });
                    } catch (\Throwable) {
                        // The recovery phase records its failure. Preserve the original sanitized error.
                    }
                }
            } finally {
                if ($maintenanceAttempted) {
                    try {
                        $this->safetyPhase($state, $deploymentId, $rollback->actor, 'maintenance_up', function () use ($rollback, $runner): void {
                            $runner->run(
                                $this->artisan($rollback, ['up']),
                                $rollback->projectRoot,
                                'leaving Laravel maintenance mode',
                            );
                        });
                    } catch (\Throwable $exception) {
                        // A failed `up` or unrecordable maintenance exit is more urgent than the earlier phase.
                        $error = $exception;
                    }
                }
            }

            $state->appendEvent(
                $deploymentId,
                'rollback',
                'complete',
                $error === null ? 'ok' : 'failed',
                $rollback->actor,
            );
            if ($error !== null) {
                throw $error;
            }

            return [
                'deployment_id' => $deploymentId,
                'previous_sha' => $previousSha,
                'target_sha' => $targetSha,
                'status' => 'rolled_back',
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<array<string, mixed>>  $events
     */
    private function assertSuccessfulDeploymentRecord(array $record, array $events): void
    {
        if (($record['operation'] ?? null) !== 'deployment') {
            throw new OpsException('Recorded state is not a deployment operation.');
        }

        $deploymentSucceeded = false;
        foreach ($events as $event) {
            $payload = $event['payload'] ?? null;
            if (! is_array($payload)) {
                throw new OpsException('Deployment state event log is invalid.');
            }
            if (($payload['operation'] ?? null) === 'deployment' && ($payload['phase'] ?? null) === 'complete') {
                $deploymentSucceeded = ($payload['status'] ?? null) === 'ok';
            }
            if (
                ($payload['operation'] ?? null) === 'rollback'
                && ($payload['phase'] ?? null) === 'complete'
                && ($payload['status'] ?? null) === 'ok'
            ) {
                throw new OpsException('Recorded deployment has already been rolled back successfully.');
            }
        }

        if (! $deploymentSucceeded) {
            throw new OpsException('Recorded deployment was not completed successfully.');
        }
    }

    /** @param array<string, mixed> $record */
    private function recordedSha(array $record, string $field): string
    {
        $sha = $record[$field] ?? null;
        if (! is_string($sha) || preg_match('/^[a-f0-9]{40}$/', $sha) !== 1) {
            throw new OpsException('Recorded deployment contains an invalid exact SHA.');
        }

        return $sha;
    }

    /** @param array<string, mixed> $record */
    private function recordedBackup(array $record, DeploymentOptions $rollback): string
    {
        $path = $record['backup_path'] ?? null;
        if (! is_string($path) || $path === '') {
            throw new OpsException('Recorded deployment backup path is invalid.');
        }
        $backup = Path::canonicalExisting($path);
        if (Path::isWithin($backup, $rollback->projectRoot) || Path::isWithin($backup, $rollback->webRoot)) {
            throw new OpsException('Rollback backup must be outside the project and web roots.');
        }

        return $backup;
    }

    private function installDependencies(
        DeploymentOptions $rollback,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
        bool $recordPhases = true,
    ): void {
        $composer = function () use ($rollback, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_COMPOSER_BINARY') ?: 'composer'),
                'install',
                '--no-dev',
                '--prefer-dist',
                '--no-interaction',
                '--optimize-autoloader',
            ], $rollback->projectRoot, 'installing production Composer dependencies');
        };
        $recordPhases
            ? $this->phase($state, $deploymentId, $rollback->actor, 'composer', $composer)
            : $composer();

        if (! is_file($rollback->projectRoot.'/package-lock.json')) {
            return;
        }

        $npmCi = function () use ($rollback, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_NPM_BINARY') ?: 'npm'),
                'ci',
            ], $rollback->projectRoot, 'installing locked frontend dependencies');
        };
        $build = function () use ($rollback, $runner): void {
            $runner->run([
                (string) (getenv('CHATME_OPS_NPM_BINARY') ?: 'npm'),
                'run',
                'build',
            ], $rollback->projectRoot, 'building production frontend assets');
        };
        if ($recordPhases) {
            $this->phase($state, $deploymentId, $rollback->actor, 'npm_ci', $npmCi);
            $this->phase($state, $deploymentId, $rollback->actor, 'build', $build);
        } else {
            $npmCi();
            $build();
        }
    }

    private function buildCaches(
        DeploymentOptions $rollback,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
        bool $recordPhase = true,
    ): void {
        $build = function () use ($rollback, $runner): void {
            foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
                $runner->run(
                    $this->artisan($rollback, [$command]),
                    $rollback->projectRoot,
                    'building Laravel '.$command,
                );
            }
        };
        $recordPhase
            ? $this->phase($state, $deploymentId, $rollback->actor, 'cache_build', $build)
            : $build();
    }

    private function verifyRelease(
        DeploymentOptions $rollback,
        DeploymentCommandRunner $runner,
        GitReleaseRepository $repository,
        DeploymentStateStore $state,
        string $deploymentId,
        string $expectedSha,
    ): void {
        $this->phase($state, $deploymentId, $rollback->actor, 'verification', function () use (
            $rollback,
            $runner,
            $repository,
            $expectedSha,
        ): void {
            $this->assertReleaseHealthy($rollback, $runner, $repository, $expectedSha);
        });
    }

    private function restoreReleaseRuntime(
        DeploymentOptions $rollback,
        DeploymentCommandRunner $runner,
        DeploymentStateStore $state,
        string $deploymentId,
    ): void {
        $this->installDependencies($rollback, $runner, $state, $deploymentId, false);
        $runner->run(
            $this->artisan($rollback, ['optimize:clear']),
            $rollback->projectRoot,
            'clearing caches during rollback recovery',
        );
        $this->buildCaches($rollback, $runner, $state, $deploymentId, false);
    }

    private function assertReleaseHealthy(
        DeploymentOptions $rollback,
        DeploymentCommandRunner $runner,
        GitReleaseRepository $repository,
        string $expectedSha,
    ): void {
        if ($repository->head() !== $expectedSha) {
            throw new OpsException('Rollback verification found an unexpected Git SHA.');
        }
        $repository->assertClean();
        $runner->run(
            $this->artisan($rollback, ['migrate:status', '--no-ansi']),
            $rollback->projectRoot,
            'checking migration status without changing the database',
        );
        $runner->run(
            [$this->phpBinary(), '-r', $this->healthCheckCode(), $rollback->projectRoot],
            $rollback->projectRoot,
            'checking the health controller internally',
        );
    }

    /** @param list<string> $arguments @return list<string> */
    private function artisan(DeploymentOptions $rollback, array $arguments): array
    {
        return array_merge([$this->phpBinary(), $rollback->projectRoot.'/artisan'], $arguments);
    }

    private function phpBinary(): string
    {
        return (string) (getenv('CHATME_OPS_PHP_BINARY') ?: PHP_BINARY);
    }

    private function healthCheckCode(): string
    {
        return <<<'PHP'
$root = $argv[1];
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$response = $app->make(\App\Http\Controllers\HealthController::class)();
exit(method_exists($response, 'getStatusCode') && $response->getStatusCode() === 200 ? 0 : 1);
PHP;
    }

    private function phase(
        DeploymentStateStore $state,
        string $deploymentId,
        string $actor,
        string $phase,
        callable $operation,
    ): void {
        $state->appendEvent($deploymentId, 'rollback', $phase, 'started', $actor);

        try {
            $operation();
            $state->appendEvent($deploymentId, 'rollback', $phase, 'ok', $actor);
        } catch (\Throwable $exception) {
            try {
                $state->appendEvent($deploymentId, 'rollback', $phase, 'failed', $actor);
            } catch (\Throwable) {
                // Preserve the operational failure; safety cleanup is handled by the outer finally.
            }
            throw $exception;
        }
    }

    private function safetyPhase(
        DeploymentStateStore $state,
        string $deploymentId,
        string $actor,
        string $phase,
        callable $operation,
    ): void {
        $stateError = null;
        try {
            $state->appendEvent($deploymentId, 'rollback', $phase, 'started', $actor);
        } catch (\Throwable $exception) {
            $stateError = $exception;
        }

        $operationError = null;
        try {
            $operation();
        } catch (\Throwable $exception) {
            $operationError = $exception;
        }

        try {
            $state->appendEvent(
                $deploymentId,
                'rollback',
                $phase,
                $operationError === null ? 'ok' : 'failed',
                $actor,
            );
        } catch (\Throwable $exception) {
            $stateError ??= $exception;
        }

        if ($operationError !== null) {
            throw $operationError;
        }
        if ($stateError !== null) {
            throw $stateError;
        }
    }
}
