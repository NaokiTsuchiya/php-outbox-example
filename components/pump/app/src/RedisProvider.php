<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Ray\Di\ProviderInterface;

class RedisProvider implements ProviderInterface
{
    public function get(): \Redis
    {
        $redis = new \Redis();
        $redis->connect(
            $_ENV['REDIS_HOST'] ?? 'redis',
            (int)($_ENV['REDIS_PORT'] ?? 6379)
        );
        return $redis;
    }
}
