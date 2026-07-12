<?php

declare(strict_types=1);

namespace ChatMe\Ops;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class OpsException extends RuntimeException {}

final class Path
{
    public static function absolute(string $path, ?string $base = null): string
    {
        if ($path === '') {
            throw new OpsException('A required path is empty.');
        }

        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', $base ?? (getcwd() ?: '.'));

        if (! preg_match('/^(?:[A-Za-z]:\/|\/)/', $path)) {
            $path = rtrim($base, '/').'/'.$path;
        }

        $prefix = '';
        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2)).'/';
            $path = substr($path, 3);
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new OpsException('Path escapes its filesystem root.');
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return rtrim($prefix.implode('/', $segments), '/') ?: $prefix;
    }

    public static function canonicalExisting(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw new OpsException('Required path does not exist.');
        }

        return self::absolute($real);
    }

    public static function canonicalForCreation(string $path): string
    {
        $absolute = self::absolute($path);
        $missing = [];
        $cursor = $absolute;

        while (! file_exists($cursor)) {
            $name = basename(str_replace('/', DIRECTORY_SEPARATOR, $cursor));
            if ($name === '' || $name === '.' || $name === DIRECTORY_SEPARATOR) {
                throw new OpsException('Cannot resolve the target path safely.');
            }

            array_unshift($missing, $name);
            $parent = dirname(str_replace('/', DIRECTORY_SEPARATOR, $cursor));
            $cursor = self::absolute($parent);
        }

        $canonical = self::canonicalExisting($cursor);

        return rtrim($canonical, '/').($missing === [] ? '' : '/'.implode('/', $missing));
    }

    public static function isWithin(string $candidate, string $root): bool
    {
        $candidate = rtrim(self::absolute($candidate), '/');
        $root = rtrim(self::absolute($root), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }

        return $candidate === $root || str_starts_with($candidate.'/', $root.'/');
    }

    public static function relative(string $file, string $root): string
    {
        $file = self::absolute($file);
        $root = rtrim(self::absolute($root), '/');

        if (! self::isWithin($file, $root) || $file === $root) {
            throw new OpsException('File is outside the expected root.');
        }

        return ltrim(substr($file, strlen($root)), '/');
    }

    public static function assertSafeRelative(string $path): void
    {
        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, "\n")
            || str_contains($path, "\r")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || in_array('..', explode('/', $path), true)
        ) {
            throw new OpsException('Backup contains an unsafe relative path.');
        }
    }
}

final class SecureFilesystem
{
    public static function makeDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new OpsException('Refusing to use a symbolic-link directory.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new OpsException('Unable to create a required directory.');
        }

        @chmod($directory, 0700);
    }

    public static function write(string $file, string $contents): void
    {
        self::makeDirectory(dirname($file));
        if (file_put_contents($file, $contents, LOCK_EX) === false) {
            throw new OpsException('Unable to write a backup artifact.');
        }

        @chmod($file, 0600);
    }

    public static function copyFile(string $source, string $destination): void
    {
        if (! is_file($source) || is_link($source)) {
            throw new OpsException('Backup source must be a regular file, not a symbolic link.');
        }

        self::makeDirectory(dirname($destination));
        if (! copy($source, $destination)) {
            throw new OpsException('Unable to copy a backup artifact.');
        }

        @chmod($destination, 0600);
    }

    public static function copyTree(string $source, string $destination): int
    {
        self::makeDirectory($destination);
        if (! file_exists($source)) {
            return 0;
        }

        if (! is_dir($source) || is_link($source)) {
            throw new OpsException('Storage source must be a regular directory, not a symbolic link.');
        }

        $source = Path::canonicalExisting($source);
        $files = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new OpsException('Storage backup refuses symbolic links.');
            }

            $relative = Path::relative($item->getPathname(), $source);
            Path::assertSafeRelative($relative);
            $target = $destination.'/'.str_replace('\\', '/', $relative);

            if ($item->isDir()) {
                self::makeDirectory($target);
            } elseif ($item->isFile()) {
                self::copyFile($item->getPathname(), $target);
                $files++;
            } else {
                throw new OpsException('Storage backup encountered an unsupported filesystem entry.');
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    public static function files(string $root, bool $excludeChecksums = false): array
    {
        $root = Path::canonicalExisting($root);
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new OpsException('Backup verification refuses symbolic links.');
            }

            if (! $item->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', Path::relative($item->getPathname(), $root));
            Path::assertSafeRelative($relative);
            if ($excludeChecksums && $relative === 'SHA256SUMS') {
                continue;
            }

            $files[] = $relative;
        }

        sort($files, SORT_STRING);

        return $files;
    }

    public static function deleteTree(string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}

final class ProcessRunner
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    public static function toFile(array $command, string $output, array $environment = [], ?string $input = null): void
    {
        SecureFilesystem::makeDirectory(dirname($output));
        $descriptors = [
            0 => $input === null ? ['pipe', 'r'] : ['file', $input, 'rb'],
            1 => ['file', $output, 'wb'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(self::platformCommand($command), $descriptors, $pipes, null, self::environment($environment), ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new OpsException('Unable to start a required database or metadata command.');
        }

        if ($input === null && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            @unlink($output);
            throw new OpsException('Required command failed with exit code '.(int) $exitCode.'.');
        }

        if (! is_file($output)) {
            throw new OpsException('Required command did not create its expected artifact.');
        }

        @chmod($output, 0600);
    }

    /**
     * @param  list<string>  $command
     */
    public static function capture(array $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(self::platformCommand($command), $descriptors, $pipes, null, self::environment(), ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new OpsException('Unable to start a required metadata command.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $output === false) {
            throw new OpsException('Required metadata command failed.');
        }

        return trim($output);
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    private static function platformCommand(array $command): array
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

    /**
     * @param  array<string, string>  $additional
     * @return array<string, string>
     */
    private static function environment(array $additional = []): array
    {
        $environment = getenv();
        if (! is_array($environment)) {
            $environment = [];
        }

        return array_merge($environment, $additional);
    }
}

/**
 * @param  array<string, mixed>  $options
 */
function option(array $options, string $name, ?string $default = null): ?string
{
    $value = $options[$name] ?? $default;
    if ($value === false || $value === null) {
        return $default;
    }

    if (! is_string($value)) {
        throw new OpsException('Option --'.$name.' must be supplied once.');
    }

    return $value;
}
