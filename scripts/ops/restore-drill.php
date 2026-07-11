#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatMe\Ops\OpsException;
use ChatMe\Ops\RestoreDrill;

require __DIR__.'/bootstrap.php';

$options = getopt('', [
    'backup:',
    'project-root::',
    'target-root:',
    'confirm:',
    'database::',
]);

try {
    $result = (new RestoreDrill)->restore(is_array($options) ? $options : []);
    fwrite(STDOUT, json_encode(['status' => 'restored'] + $result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (OpsException $exception) {
    fwrite(STDERR, 'Restore drill failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Restore drill failed: unexpected internal error.'.PHP_EOL);
    exit(1);
}
