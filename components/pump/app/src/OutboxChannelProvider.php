<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Ray\Di\ProviderInterface;

/** 環境変数 OUTBOX_CHANNEL から Redis チャネル名を提供する */
class OutboxChannelProvider implements ProviderInterface
{
    public function get(): string
    {
        return $_ENV['OUTBOX_CHANNEL'];
    }
}
