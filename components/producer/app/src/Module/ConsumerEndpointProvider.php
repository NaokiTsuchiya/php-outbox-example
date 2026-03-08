<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Module;

use Ray\Di\ProviderInterface;

/** 環境変数 CONSUMER_ENDPOINT から Consumer エンドポイント URL を提供する */
class ConsumerEndpointProvider implements ProviderInterface
{
    public function get(): string
    {
        return $_ENV['CONSUMER_ENDPOINT'];
    }
}
