<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

class PumpModule extends AbstractModule
{
    protected function configure(): void
    {
        // ── DB ─────────────────────────────────────────────
        $this->bind(ExtendedPdoInterface::class)->toInstance(
            new ExtendedPdo(
                sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $_ENV['DB_HOST'] ?? 'db',
                    $_ENV['DB_NAME'] ?? 'outbox_demo'
                ),
                $_ENV['DB_USER'] ?? 'app',
                $_ENV['DB_PASSWORD'] ?? 'secret',
            )
        );

        // ── Redis（通常用: Singleton）──────────────────────
        $this->bind(\Redis::class)
            ->toProvider(RedisProvider::class)
            ->in(Scope::SINGLETON);

        // ── Redis（SUBSCRIBE用: 都度新規）──────────────────
        $this->bind(\Redis::class)
            ->annotatedWith(Subscriber::class)
            ->toProvider(RedisProvider::class);

        // ── チャネル名 ──────────────────────────────────────
        $this->bind()
            ->annotatedWith('outbox_channel')
            ->toInstance($_ENV['OUTBOX_CHANNEL'] ?? 'outbox:notify');

        // ── Consumer エンドポイント ─────────────────────────
        $this->bind()
            ->annotatedWith('consumer_endpoint')
            ->toInstance($_ENV['CONSUMER_ENDPOINT'] ?? 'http://consumer:8081');

        // ── Consumer ────────────────────────────────────────
        $this->bind(ConsumerInterface::class)
            ->to(HttpConsumer::class);

        // ── Position ──────────────────────────────────────────
        $this->bind(ConsumedPositionRepository::class);

        // ── Logger ──────────────────────────────────────────
        $this->bind(LoggerInterface::class)->toInstance(
            new Logger('pump', [new StreamHandler('php://stderr')])
        );
    }
}
