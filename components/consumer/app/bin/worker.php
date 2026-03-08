<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use BEAR\Package\Injector;
use MyVendor\OutboxConsumer\Worker\SqsWorker;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

Coroutine\run(function () {
    $injector = Injector::getInstance('MyVendor\OutboxConsumer', 'app', dirname(__DIR__));
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('Consumer worker starting');
    $worker = $injector->getInstance(SqsWorker::class);
    $worker->run();
});
