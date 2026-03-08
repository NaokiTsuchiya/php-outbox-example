<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ray\Di\ProviderInterface;

/** 標準エラー出力へ書き込む Logger を提供する */
class LoggerProvider implements ProviderInterface
{
    public function get(): LoggerInterface
    {
        return new Logger('consumer', [new StreamHandler('php://stderr')]);
    }
}
