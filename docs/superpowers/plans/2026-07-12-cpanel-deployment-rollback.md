# Guarded cPanel Deployment and Rollback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build fail-closed, exact-SHA cPanel deployment and code-only rollback tools that preserve verified backups, never seed or reverse migrations automatically, and always leave Laravel out of maintenance mode.

**Architecture:** Extend the existing standalone PHP operations framework. A deployment runtime owns sanitized command execution, a non-blocking lock, Git release validation, and HMAC-protected append-only state outside the web root. Separate deployment and rollback orchestrators use that runtime; PHPUnit integration tests exercise real temporary Git repositories and verified backup artifacts while substituting only external PHP/Composer/npm/process-count executables.

**Tech Stack:** PHP 8.2, Laravel 12 test runner/PHPUnit 11, native `proc_open`, native `flock`, Git CLI, existing `BackupVerifier` and secure filesystem helpers.

## Global Constraints

- Accept only lowercase full 40-character Git SHAs; rollback accepts a recorded deployment ID, never a free SHA.
- Require clean tracked and untracked Git state, a verified backup whose manifest release SHA equals current `HEAD`, and state/backup/lock paths outside the application and supplied web root.
- Acquire the deployment lock with `LOCK_EX | LOCK_NB` and keep it for the whole operation.
- Fetch `--remote` with prune and require the target to be reachable from the explicitly approved `--release-ref`; reject non-descendant deploy targets.
- Switch with `git switch --detach <sha>` only; never use `git reset --hard` or destructive untracked-file cleanup.
- Run production Composer install, conditional npm clean install/build, forward migrations, Laravel cache rebuild, exact-SHA and internal health checks.
- Never run a generic seeder, homepage adoption, `migrate:rollback`, or an automatic database restore/overwrite.
- Call `artisan up` from a `finally` block after every maintenance attempt, including deployment, automatic code recovery, and rollback failures.
- Bound every child command with the explicit `--command-timeout` (default 900 seconds, maximum 3600), terminate timed-out children, and keep cleanup runnable without stdout/stderr pipe backpressure.
- Keep state mode `0600`, directories `0700`, HMAC-protect immutable metadata and chained append-only events, and never persist command output or credentials.
- Treat rollback as code-only against forward-compatible schema. If recovered previous code fails internal health after migrations, restore and verify target code; never reverse or overwrite the database automatically.
- Do not touch application/auth/seeder files, production, GitHub, or create commits.

---

### Task 1: Deployment runtime, locking, signed state, and Git safety

**Files:**
- Create: `scripts/ops/lib/DeploymentRuntime.php`
- Modify: `scripts/ops/bootstrap.php`
- Test: `tests/Unit/DeploymentRollbackToolTest.php`

**Interfaces:**
- `DeploymentOptions::fromArray(array $options, string $mode): self` validates project/state/web/backup paths, SHA or deployment ID, remote/ref, actor, and process limit.
- `DeploymentLock::acquire(string $stateRoot): self` obtains a non-blocking lock and releases it on `release()`/destruction.
- `DeploymentStateStore` creates immutable HMAC metadata, appends chained event records, and verifies records by deployment ID.
- `DeploymentCommandRunner::run(list<string> $command, string $cwd, string $phase): string` executes without a shell and throws only sanitized phase errors.
- `GitReleaseRepository` exposes `head()`, `assertClean()`, `fetch()`, `assertApprovedTarget()`, `assertForwardDeployment()`, and `switchDetached()`.

- [x] Write focused tests for invalid SHAs, paths inside the web root, active lock refusal, signed state tampering, dirty trees, unapproved targets, and downgrade rejection.
- [x] Run the focused preflight tests and retain their fail-closed assertions.
- [x] Implement the runtime with sanitized process errors, strict option validation, POSIX permission attempts, HMAC metadata, chained JSONL events, real non-blocking `flock`, and exact Git commands.
- [x] Re-run the focused preflight tests and confirm they pass.
- [x] Run `php vendor/bin/pint --test scripts/ops/lib/DeploymentRuntime.php tests/Unit/DeploymentRollbackToolTest.php`.

### Task 2: Guarded deployment orchestration and CLI

**Files:**
- Create: `scripts/ops/lib/DeploymentManager.php`
- Create: `scripts/ops/deploy.php`
- Modify: `scripts/ops/bootstrap.php`
- Test: `tests/Unit/DeploymentRollbackToolTest.php`

**Interfaces:**
- `DeploymentManager::deploy(array $options): array{deployment_id:string,previous_sha:string,target_sha:string,status:string}`.
- CLI accepts `--target-sha`, `--project-root`, `--state-root`, `--backup`, `--web-root`, `--remote`, `--release-ref`, optional `--actor`, optional `--max-processes`, and optional `--command-timeout`.

- [x] Add a success test using a temporary real Git remote and verified SQLite backup; assert exact target checkout, command order, recorded previous/target SHA, and absence of seed/destructive commands.
- [x] Add failure-injection tests for maintenance entry, Composer, build, migration, and cache; each must fail, recover code when needed, call `artisan up`, and keep secrets out of output/state.
- [x] Confirm the incomplete implementation failed the new contracts before completion.
- [x] Implement preflight-after-lock, capacity check, maintenance mode, exact switch, dependency/build/migration/cache phases, CLI exact-SHA/internal health validation, append-only phase events, automatic code-only recovery, and unconditional `artisan up`.
- [x] Re-run deployment-only tests and confirm GREEN.

### Task 3: Recorded deployment-ID rollback

**Files:**
- Modify: `scripts/ops/lib/DeploymentManager.php` (contains the closely coupled `RollbackManager`)
- Create: `scripts/ops/rollback.php`
- Modify: `scripts/ops/bootstrap.php`
- Test: `tests/Unit/DeploymentRollbackToolTest.php`

**Interfaces:**
- `RollbackManager::rollback(array $options): array{deployment_id:string,previous_sha:string,target_sha:string,status:string}`.
- CLI accepts `--deployment-id`, `--project-root`, `--state-root`, `--web-root`, optional `--actor`, optional `--max-processes`, and optional `--command-timeout`; it exposes no SHA override.

- [x] Add tests that reject a missing/arbitrary SHA, tampered state, non-success deployment records, mismatched current HEAD, dirty state, invalid backup, and active lock before maintenance.
- [x] Add rollback success and injected-failure tests proving exact previous checkout, dependency/cache restoration, unconditional `artisan up`, and no forward/reverse migration or database restore command.
- [x] Run rollback-only tests and confirm RED because the manager/CLI were absent.
- [x] Implement signed-record loading, backup re-verification, exact target-HEAD precondition, maintenance window, previous-SHA switch, dependency/build/cache restoration, exact-SHA/internal health validation, append-only rollback events, best-effort return to target on failure, and unconditional `artisan up`.
- [x] Re-run rollback-only tests and confirm GREEN.

### Task 4: Operator runbook and prohibited-command contract

**Files:**
- Create: `docs/operations/deployment-rollback.md`
- Modify: `tests/Feature/ProductionOperationsTest.php`

**Interfaces:**
- The runbook documents preflight, exact commands for deploy/rollback, state layout, failure behavior, separate homepage adoption, evidence capture, and production smoke checks.

- [ ] Add a failing documentation/source-contract test requiring both CLI entry points, exact option examples, explicit no-seed/no-database-rollback wording, and absence of prohibited command strings from deployment source files.
- [ ] Run `php artisan test --do-not-cache-result tests/Feature/ProductionOperationsTest.php` and confirm RED.
- [ ] Write the runbook and minimal contract text.
- [ ] Re-run the feature test and confirm GREEN.

### Task 5: Final verification and review

**Files:**
- Verify only deployment-scoped files from Tasks 1-4 and this plan.

- [x] Run `php artisan test --do-not-cache-result tests/Unit/DeploymentRollbackToolTest.php tests/Unit/BackupRestoreToolTest.php tests/Feature/ProductionOperationsTest.php` (run as three focused invocations for clearer evidence).
- [x] Run Pint in test mode on all changed PHP deployment/test files.
- [x] Run `php -l` on all changed PHP files and `git diff --check --` on deployment-scoped paths.
- [x] Inspect scoped `git status --short` and confirm no auth/seeder/application files were changed by this tranche.
- [x] Request an independent code review against the approved design; fix every Critical/Important finding with reproducing tests for state-I/O-safe cleanup, command timeout/lock release, maintenance-up error priority, and post-migration code compatibility.
- [ ] Do not commit, push, deploy, connect to production, or invoke the tools against a non-fixture path.
