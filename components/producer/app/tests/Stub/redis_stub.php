<?php

declare(strict_types=1);

// ext-redis がインストールされていない環境でテストを実行するためのスタブ
if (class_exists(\Redis::class)) {
    return;
}

class Redis
{
    public const OPT_READ_TIMEOUT = 3;

    public function connect(string $host, int $port = 6379): bool
    {
        return true;
    }

    public function getHost(): string
    {
        return '';
    }

    public function getPort(): int
    {
        return 6379;
    }

    /** @param string[] $channels */
    public function subscribe(array $channels, callable $callback): bool
    {
        return true;
    }

    /** @param string[] $channels */
    public function unsubscribe(array $channels): bool
    {
        return true;
    }

    public function setOption(int $option, mixed $value): bool
    {
        return true;
    }
}
