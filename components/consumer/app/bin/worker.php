<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use BEAR\Package\Injector;
use MyVendor\OutboxConsumer\Worker\SqsWorker;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

// STDIO と FILE は Swoole のフック対象から除外する（Swoole 6.x で io_uring が使用不可の環境では
// フックされたファイル操作が失敗するため、DI コンテナ初期化を保護する）
Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_STDIO ^ SWOOLE_HOOK_FILE]);

Coroutine\run(function () {
    $injector = Injector::getInstance('MyVendor\OutboxConsumer', 'app', dirname(__DIR__));
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('Consumer worker starting');
    $worker = $injector->getInstance(SqsWorker::class);
    $worker->run();
});
