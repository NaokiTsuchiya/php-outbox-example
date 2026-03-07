<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MyVendor\OutboxDemo\Bootstrap;
use MyVendor\OutboxDemo\Outbox\OutboxPump;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

Coroutine\run(function () {
    $injector = Bootstrap::getInjector('cli-app');
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('OutboxPump worker starting');
    $pump = $injector->getInstance(OutboxPump::class);
    $pump->run();
});
