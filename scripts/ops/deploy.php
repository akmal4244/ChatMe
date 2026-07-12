#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChatMe\Ops\DeploymentManager;
use ChatMe\Ops\OpsException;

require __DIR__.'/bootstrap.php';

$options = getopt('', [
    'target-sha:',
    'project-root::',
    'state-root:',
    'backup:',
    'web-root::',
    'remote::',
    'release-ref:',
    'actor::',
    'max-processes::',
    'command-timeout::',
]);

try {
    $result = (new DeploymentManager)->deploy(is_array($options) ? $options : []);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (OpsException $exception) {
    fwrite(STDERR, 'Deployment failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Deployment failed: unexpected internal error.'.PHP_EOL);
    exit(1);
}
