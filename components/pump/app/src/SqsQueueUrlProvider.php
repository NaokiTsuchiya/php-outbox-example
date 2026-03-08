<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Ray\Di\ProviderInterface;

/** 環境変数 SQS_QUEUE_URL から SQS キュー URL を提供する */
class SqsQueueUrlProvider implements ProviderInterface
{
    public function get(): string
    {
        return $_ENV['SQS_QUEUE_URL'];
    }
}
