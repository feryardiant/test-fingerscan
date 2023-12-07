<?php

use Fingerscan\Command;
use Fingerscan\Device;

set_time_limit(0);

$base_path = dirname(__DIR__);

require($base_path.'/vendor/autoload.php');

try {
    $device = new Device('10.10.3.18');
    $command = new Command($device);

    $command->run($base_path.'/samples', ...array_slice($argv, 1));

    exit(0);
} catch (Throwable $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
