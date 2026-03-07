<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MyVendor\OutboxPump\OutboxPump;
use MyVendor\OutboxPump\PumpModule;
use Psr\Log\LoggerInterface;
use Ray\Di\Injector;
use Swoole\Coroutine;

Coroutine\run(function () {
    $injector = new Injector(new PumpModule());
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('OutboxPump worker starting');
    $pump = $injector->getInstance(OutboxPump::class);
    $pump->run();
});
