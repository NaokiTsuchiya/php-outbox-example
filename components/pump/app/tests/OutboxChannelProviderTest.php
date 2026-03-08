<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OutboxChannelProviderTest extends TestCase
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

    // ── get(): 環境変数から Outbox チャネル名を返す ──

    #[Test]
    public function getReturnsOutboxChannelFromEnv(): void
    {
        // Given: OUTBOX_CHANNEL が設定されている
        $_ENV['OUTBOX_CHANNEL'] = 'outbox:notify';
        $provider = new OutboxChannelProvider();

        // When
        $result = $provider->get();

        // Then: 環境変数の値がそのまま返される
        $this->assertSame('outbox:notify', $result);
    }

    #[Test]
    public function getReturnsCustomChannelName(): void
    {
        // Given: カスタムチャネル名が設定されている
        $_ENV['OUTBOX_CHANNEL'] = 'custom:channel:name';
        $provider = new OutboxChannelProvider();

        // When
        $result = $provider->get();

        // Then: カスタムチャネル名が返される
        $this->assertSame('custom:channel:name', $result);
    }

    #[Test]
    public function getReturnsString(): void
    {
        // Given
        $_ENV['OUTBOX_CHANNEL'] = 'outbox:notify';
        $provider = new OutboxChannelProvider();

        // When
        $result = $provider->get();

        // Then: string 型が返される
        $this->assertIsString($result);
    }
}
