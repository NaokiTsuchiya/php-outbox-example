<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;
use Aws\Sqs\SqsClient;
use Psr\Log\LoggerInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

class PumpModule extends AbstractModule
{
    protected function configure(): void
    {
        // ── DB ─────────────────────────────────────────────
        $this->bind(ExtendedPdoInterface::class)
            ->toProvider(ExtendedPdoProvider::class);

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
            ->toProvider(OutboxChannelProvider::class);

        // ── SQS クライアント ────────────────────────────────
        $this->bind(SqsClient::class)
            ->toProvider(SqsClientProvider::class);

        // ── SQS キュー URL ──────────────────────────────────
        $this->bind()
            ->annotatedWith('sqs_queue_url')
            ->toProvider(SqsQueueUrlProvider::class);

        // ── Consumer ────────────────────────────────────────
        $this->bind(ConsumerInterface::class)
            ->to(SqsConsumer::class);

        // ── Position ──────────────────────────────────────────
        $this->bind(ConsumedPositionRepository::class);

        // ── Logger ──────────────────────────────────────────
        $this->bind(LoggerInterface::class)
            ->toProvider(LoggerProvider::class);
    }
}
