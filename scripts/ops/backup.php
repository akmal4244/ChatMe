#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatMe\Ops\BackupCreator;
use ChatMe\Ops\OpsException;

require __DIR__.'/bootstrap.php';

$options = getopt('', [
    'backup-root:',
    'project-root::',
    'web-root::',
    'database-driver::',
    'disposable-test-mode',
    'sqlite-database::',
    'storage-private::',
    'storage-public::',
    'release-sha::',
    'migration-state-file::',
]);

try {
    $result = (new BackupCreator)->create(is_array($options) ? $options : []);
    fwrite(STDOUT, json_encode(['status' => 'created'] + $result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (OpsException $exception) {
    fwrite(STDERR, 'Backup failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Backup failed: unexpected internal error.'.PHP_EOL);
    exit(1);
}
