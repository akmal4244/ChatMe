<?php

declare(strict_types=1);

namespace ChatMe\Ops;

use Illuminate\Contracts\Console\Kernel;

final class DatabaseConfiguration
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function resolve(string $projectRoot, array $options, bool $testMode): array
    {
        $driverOverride = option($options, 'database-driver');

        if ($testMode && $driverOverride !== null) {
            return self::fromTestInputs($projectRoot, $driverOverride, $options);
        }

        $configured = self::fromLaravel($projectRoot);
        if ($driverOverride !== null && $driverOverride !== $configured['driver']) {
            throw new OpsException('Production database driver override does not match Laravel configuration.');
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function fromTestInputs(string $projectRoot, string $driver, array $options): array
    {
        $driver = strtolower($driver);
        if ($driver === 'sqlite') {
            $database = option($options, 'sqlite-database');
            if ($database === null) {
                throw new OpsException('Disposable SQLite mode requires --sqlite-database.');
            }

            return [
                'driver' => 'sqlite',
                'database' => Path::absolute($database, $projectRoot),
            ];
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new OpsException('Only mysql, mariadb and sqlite database drivers are supported.');
        }

        return self::mysqlFromEnvironment($driver);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromLaravel(string $projectRoot): array
    {
        $autoload = $projectRoot.'/vendor/autoload.php';
        $bootstrap = $projectRoot.'/bootstrap/app.php';
        if (! is_file($autoload) || ! is_file($bootstrap)) {
            throw new OpsException('Laravel bootstrap files are missing from the project root.');
        }

        require_once $autoload;
        $app = require $bootstrap;
        $app->make(Kernel::class)->bootstrap();
        $connection = (string) $app['config']->get('database.default');
        $config = $app['config']->get('database.connections.'.$connection);

        if (! is_array($config)) {
            throw new OpsException('Laravel database configuration is unavailable.');
        }

        $driver = strtolower((string) ($config['driver'] ?? ''));
        if ($driver === 'sqlite') {
            $database = (string) ($config['database'] ?? '');
            if ($database === '' || $database === ':memory:') {
                throw new OpsException('A file-backed SQLite database is required for backup.');
            }

            return [
                'driver' => 'sqlite',
                'database' => Path::absolute($database, $projectRoot),
            ];
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new OpsException('Only mysql, mariadb and sqlite database drivers are supported.');
        }

        return self::validateMysql([
            'driver' => $driver,
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'database' => (string) ($config['database'] ?? ''),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'unix_socket' => (string) ($config['unix_socket'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mysqlFromEnvironment(string $driver): array
    {
        return self::validateMysql([
            'driver' => $driver,
            'host' => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => (string) (getenv('DB_PORT') ?: '3306'),
            'database' => (string) (getenv('DB_DATABASE') ?: ''),
            'username' => (string) (getenv('DB_USERNAME') ?: ''),
            'password' => (string) (getenv('DB_PASSWORD') ?: ''),
            'unix_socket' => (string) (getenv('DB_SOCKET') ?: ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function validateMysql(array $config): array
    {
        foreach (['database', 'username'] as $required) {
            if (($config[$required] ?? '') === '') {
                throw new OpsException('MySQL/MariaDB configuration is incomplete.');
            }
        }

        if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_$.-]*$/', (string) $config['database']) !== 1) {
            throw new OpsException('MySQL/MariaDB database name contains unsupported characters.');
        }

        if (preg_match('/^[1-9][0-9]{0,4}$/', (string) $config['port']) !== 1) {
            throw new OpsException('MySQL/MariaDB port is invalid.');
        }

        return $config;
    }
}

final class DatabaseArtifact
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function export(array $config, string $workingDirectory): string
    {
        $driver = (string) $config['driver'];
        SecureFilesystem::makeDirectory($workingDirectory.'/database');

        if ($driver === 'sqlite') {
            $source = (string) $config['database'];
            if (! is_file($source) || is_link($source)) {
                throw new OpsException('SQLite source must be a regular file-backed database.');
            }

            $relative = 'database/database.sqlite';
            SecureFilesystem::copyFile($source, $workingDirectory.'/'.$relative);

            return $relative;
        }

        $relative = 'database/database.sql';
        $binary = (string) (getenv('CHATME_OPS_MYSQLDUMP_BINARY') ?: ($driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump'));
        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--hex-blob',
            '--skip-lock-tables',
            '--host='.(string) $config['host'],
            '--port='.(string) $config['port'],
            '--user='.(string) $config['username'],
        ];
        if (($config['unix_socket'] ?? '') !== '') {
            $command[] = '--socket='.(string) $config['unix_socket'];
        }
        $command[] = (string) $config['database'];

        ProcessRunner::toFile(
            $command,
            $workingDirectory.'/'.$relative,
            ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
        );

        if (filesize($workingDirectory.'/'.$relative) === 0) {
            throw new OpsException('Database export produced an empty artifact.');
        }

        return $relative;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    public static function importMysql(array $connection, string $database, string $dump): void
    {
        $binary = (string) (getenv('CHATME_OPS_MYSQL_BINARY') ?: ($connection['driver'] === 'mariadb' ? 'mariadb' : 'mysql'));
        $command = [
            $binary,
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
        ];
        if (($connection['unix_socket'] ?? '') !== '') {
            $command[] = '--socket='.(string) $connection['unix_socket'];
        }
        $command[] = $database;

        $discard = tempnam(sys_get_temp_dir(), 'chatme-ops-mysql-');
        if ($discard === false) {
            throw new OpsException('Unable to allocate a temporary command artifact.');
        }

        try {
            ProcessRunner::toFile(
                $command,
                $discard,
                ['MYSQL_PWD' => (string) ($connection['password'] ?? '')],
                $dump,
            );
        } finally {
            @unlink($discard);
        }
    }
}
