#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatMe\Ops\BackupVerifier;
use ChatMe\Ops\OpsException;

use function ChatMe\Ops\option;

require __DIR__.'/bootstrap.php';

$options = getopt('', ['backup:']);

try {
    $backup = option(is_array($options) ? $options : [], 'backup');
    if ($backup === null) {
        throw new OpsException('--backup is required.');
    }

    $result = (new BackupVerifier)->verify($backup);
    fwrite(STDOUT, json_encode([
        'status' => 'verified',
        'backup' => str_replace('\\', '/', $result['root']),
        'files' => $result['files'],
        'release_sha' => $result['manifest']['release_sha'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (OpsException $exception) {
    fwrite(STDERR, 'Verification failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Verification failed: unexpected internal error.'.PHP_EOL);
    exit(1);
}
