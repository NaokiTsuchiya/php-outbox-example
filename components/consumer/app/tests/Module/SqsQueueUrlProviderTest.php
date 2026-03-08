<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqsQueueUrlProviderTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    // ── get(): 環境変数から SQS キュー URL を返す ──

    #[Test]
    public function getReturnsSqsQueueUrlFromEnv(): void
    {
        // Given: SQS_QUEUE_URL が設定されている
        $_ENV['SQS_QUEUE_URL'] = 'http://localhost:9324/queue/outbox.fifo';
        $provider = new SqsQueueUrlProvider();

        // When
        $result = $provider->get();

        // Then: 環境変数の値がそのまま返される
        $this->assertSame('http://localhost:9324/queue/outbox.fifo', $result);
    }

    #[Test]
    public function getReturnsCustomQueueUrl(): void
    {
        // Given: 別のキュー URL が設定されている
        $_ENV['SQS_QUEUE_URL'] = 'https://sqs.us-east-1.amazonaws.com/123456/myqueue.fifo';
        $provider = new SqsQueueUrlProvider();

        // When
        $result = $provider->get();

        // Then: 設定された URL が返される
        $this->assertSame('https://sqs.us-east-1.amazonaws.com/123456/myqueue.fifo', $result);
    }

    #[Test]
    public function getReturnsString(): void
    {
        // Given
        $_ENV['SQS_QUEUE_URL'] = 'http://localhost:9324/queue/outbox.fifo';
        $provider = new SqsQueueUrlProvider();

        // When
        $result = $provider->get();

        // Then: string 型が返される
        $this->assertIsString($result);
    }
}
