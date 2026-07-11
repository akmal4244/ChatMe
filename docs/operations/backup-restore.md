# ChatMe Backup, Verification and Restore Drill

This runbook creates a deploy checkpoint without placing customer data under the web root. The tooling supports production MySQL/MariaDB and a deliberately explicit SQLite mode for disposable tests.

## Safety contract

- Store backups outside both the application directory and every public web root. For the current cPanel layout, use a directory such as `/home2/akmalmar/secure-backups/chatme`, never anything under `/home2/akmalmar/public_html`.
- Run the commands as the ChatMe account owner with `umask 077`. The tooling creates directories with mode `0700` and files with mode `0600` where the filesystem supports POSIX permissions.
- The scripts never copy `.env` and never put database credentials in command-line arguments, the manifest or command output. MySQL/MariaDB credentials are loaded from Laravel configuration; the password is provided only to the database client process through `MYSQL_PWD`.
- A backup still contains customer data. Treat the database dump and storage payload as confidential even though the manifest is secret-free.
- `SHA256SUMS` detects corruption or untracked files; it does not prove authenticity against an attacker who can rewrite both the payload and checksums. Protect the backup destination and keep an immutable off-host copy.
- A restore drill can only write to a new path whose name contains `restore-drill`. It refuses any path inside the application or backup and refuses an existing target.
- A MySQL/MariaDB restore drill additionally requires a separate, pre-created database whose name contains `_restore_drill`. It refuses the database configured for the running ChatMe application. It never creates, drops or overwrites a database automatically.

## Backup contents

Each successful run atomically renames a `.partial-*` working directory to `chatme-<UTC timestamp>-<random>` and includes:

```text
database/database.sql              # MySQL/MariaDB
database/database.sqlite           # disposable SQLite mode only
storage/app/private/**
storage/app/public/**
metadata/release-sha.txt
metadata/migrations.txt
manifest.json
SHA256SUMS
```

The manifest records the UTC creation time, full Git release SHA, database driver and artifact, migration-state artifact, private/public storage counts, byte size and SHA-256 of every payload file. It intentionally excludes source paths, database names, hosts, usernames, passwords, tokens and application keys.

## Production backup on cPanel

Prerequisites are PHP 8.2+, Git and either `mysqldump` for MySQL or `mariadb-dump` for MariaDB. Confirm there is enough free space for the database plus `storage/app/private` and `storage/app/public`.

```bash
umask 077
APP_ROOT=/home2/akmalmar/public_html/chatme.akmalmarvis.com
BACKUP_ROOT=/home2/akmalmar/secure-backups/chatme

mkdir -p "$BACKUP_ROOT"
chmod 700 /home2/akmalmar/secure-backups "$BACKUP_ROOT"
cd "$APP_ROOT"

php scripts/ops/backup.php \
  --project-root="$APP_ROOT" \
  --backup-root="$BACKUP_ROOT"
```

For a separate document root or alias, add `--web-root=/absolute/public/path`; the script will also reject a backup destination below that path. If the host exposes only `mariadb-dump` while Laravel reports the `mysql` driver, set the binary for that one process:

```bash
CHATME_OPS_MYSQLDUMP_BINARY=mariadb-dump \
php scripts/ops/backup.php \
  --project-root="$APP_ROOT" \
  --backup-root="$BACKUP_ROOT"
```

Success emits one JSON line. Record the exact `backup` path and `release_sha` in the deployment log. A failure exits non-zero, prints a sanitized error, and removes its partial directory.

For a consistent pre-deploy checkpoint, prevent overlapping deployments, enter Laravel maintenance mode, pause or drain queue workers that can write data, create and verify the backup, then always bring the application back up if the deployment is cancelled. `mysqldump` uses a single transaction and includes routines, triggers and events; non-transactional MySQL tables still require a maintenance window.

## Verify immediately

Use the exact path emitted by the backup command:

```bash
BACKUP=/home2/akmalmar/secure-backups/chatme/chatme-YYYYMMDDTHHMMSSZ-xxxxxxxx

php "$APP_ROOT/scripts/ops/verify.php" --backup="$BACKUP"
```

A valid backup emits `{"status":"verified", ...}` and exits `0`. Any missing, extra, modified, symbolic-link or unsafe-path artifact makes verification exit non-zero. Do not deploy if verification fails.

Also verify the destination is not served over HTTP and inspect permissions:

```bash
find "$BACKUP" -type d ! -perm 0700 -print
find "$BACKUP" -type f ! -perm 0600 -print
```

Both commands should produce no output on a POSIX filesystem.

## MySQL/MariaDB restore drill

Perform this on a controlled staging host or during an approved maintenance window. First create a new, empty disposable database through cPanel with a name such as `akmalmar_chatme_restore_drill_20260712`, and grant the configured ChatMe database user access to that database only for the drill. Never reuse the live database name.

```bash
DRILL_ROOT=/home2/akmalmar/restore-drills/chatme-restore-drill-20260712

php "$APP_ROOT/scripts/ops/restore-drill.php" \
  --project-root="$APP_ROOT" \
  --backup="$BACKUP" \
  --target-root="$DRILL_ROOT" \
  --database=akmalmar_chatme_restore_drill_20260712 \
  --confirm=RESTORE-DRILL
```

The tool verifies the backup before writing, imports the SQL dump into only the named disposable database, restores private/public storage beneath the new drill root, rechecks restored storage hashes, and writes `restore-report.json`. It never changes the application's `.env` or points ChatMe at the drill database.

After the command succeeds:

1. Configure a separate staging copy of ChatMe to use the drill database and restored storage; never edit production `.env` for this check.
2. Run `php artisan migrate:status`, health checks, tenant-isolation smoke tests, login, chatbot query and a read-only subscription check.
3. Compare the reported release SHA and migration state with the intended recovery point.
4. Record duration, database size, storage file count and any recovery issue.
5. Remove the drill directory and drop only the explicitly named disposable database after evidence is retained.

If import fails, assume the disposable database may contain a partial import and drop/recreate that disposable database before retrying. The production database is not an allowed target.

## Disposable SQLite test mode

This mode exists for automated/local recovery tests, not production. Every source is explicit and the release/migration metadata can be supplied as fixtures:

```bash
php scripts/ops/backup.php \
  --project-root=/tmp/chatme-fixture \
  --backup-root=/tmp/chatme-fixture-backups \
  --database-driver=sqlite \
  --disposable-test-mode \
  --sqlite-database=/tmp/chatme-fixture/database/test.sqlite \
  --storage-private=/tmp/chatme-fixture/storage/app/private \
  --storage-public=/tmp/chatme-fixture/storage/app/public \
  --release-sha=0123456789abcdef0123456789abcdef01234567 \
  --migration-state-file=/tmp/chatme-fixture/migration-status.txt

php scripts/ops/restore-drill.php \
  --project-root=/tmp/chatme-fixture \
  --backup=/tmp/chatme-fixture-backups/chatme-... \
  --target-root=/tmp/chatme-restore-drill-1 \
  --confirm=RESTORE-DRILL
```

SQLite is restored to `<target-root>/database/database.sqlite`; private and public storage keep their Laravel-relative paths.

## Retention and off-host copies

Start with a documented policy of 7 daily, 4 weekly and 12 monthly verified recovery points, then adjust it to legal, contractual and customer requirements. Retention deletion should be a separate reviewed job, never part of backup creation.

Use a 3-2-1 strategy:

- keep the restricted local copy for fast rollback;
- upload a verified, encrypted copy to a different provider/account;
- enable object lock or immutable retention on at least one off-host copy;
- use a dedicated least-privilege upload credential that cannot read production `.env` or delete historical objects;
- verify the uploaded object's checksum before marking the run successful;
- delete an old local copy only after its off-host copy is verified and the minimum retained set remains intact.

Encrypt before transfer with an approved key-management process. Keep encryption keys outside the hosting account and test key recovery. Do not email or place raw dumps in tickets, chat, Git, `public_html`, shared Drive folders or application logs.

## Scheduling and monitoring

Run backup through a small host-owned wrapper or scheduler with an overlap lock. The scheduled workflow must:

1. acquire a non-blocking lock;
2. run `backup.php` and capture its JSON result outside the web root;
3. run `verify.php` on the emitted path;
4. encrypt and copy the verified backup off-host;
5. verify the remote checksum;
6. alert on any non-zero exit or missing daily success;
7. prune only according to the reviewed retention policy.

Never put `DB_PASSWORD`, `APP_KEY`, API tokens or cloud credentials in the crontab command line. The PHP tooling obtains database credentials from Laravel configuration and does not print them.

## Real recovery and rollback

`restore-drill.php` is intentionally not a production overwrite tool. For an actual incident:

1. declare the incident and stop writes;
2. preserve the failed system and current database as evidence;
3. verify the selected backup and off-host checksum;
4. restore into a new database and new storage path first;
5. run the complete drill validation against a staging application copy;
6. obtain the incident owner's approval for cutover;
7. atomically switch configuration to the validated new database/storage, clear Laravel caches and restart workers;
8. run health, tenant-isolation, authentication, chatbot and payment read-only checks;
9. keep the previous database/storage read-only until the rollback window closes.

For a code-only rollback, do not restore an older database over newer migrations. Deploy the known-good Git SHA, preserve forward-compatible schema/data, run `php artisan migrate:status`, rebuild caches, restart workers and verify the live SHA. Escalate any destructive schema rollback to a separately reviewed recovery plan.

## Required evidence before production deploy

- backup command exited `0` and emitted the intended pre-deploy release SHA;
- verification command exited `0` for that exact backup path;
- backup path is outside `/home2/akmalmar/public_html` and permissions are restricted;
- manifest lists the database artifact, migration state, private storage and public storage;
- an encrypted off-host copy has a verified checksum;
- the latest scheduled restore drill has succeeded and its report is retained;
- restore time is within the agreed recovery-time objective and the recovery point meets the recovery-point objective;
- rollback owner, cutover decision and post-deploy smoke checks are recorded.

