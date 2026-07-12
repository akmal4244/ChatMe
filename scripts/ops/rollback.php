#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatMe\Ops\OpsException;
use ChatMe\Ops\RollbackManager;

require __DIR__.'/bootstrap.php';

$options = getopt('', [
    'deployment-id:',
    'project-root::',
    'state-root:',
    'web-root::',
    'actor::',
    'max-processes::',
    'command-timeout::',
]);

try {
    $result = (new RollbackManager)->rollback(is_array($options) ? $options : []);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (OpsException $exception) {
    fwrite(STDERR, 'Rollback failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Rollback failed: unexpected internal error.'.PHP_EOL);
    exit(1);
}
