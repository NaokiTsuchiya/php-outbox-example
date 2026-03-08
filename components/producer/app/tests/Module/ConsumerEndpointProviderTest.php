<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Module;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConsumerEndpointProviderTest extends TestCase
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

    // ── get(): 環境変数から Consumer エンドポイント URL を返す ──

    #[Test]
    public function getReturnsConsumerEndpointFromEnv(): void
    {
        // Given: CONSUMER_ENDPOINT が設定されている
        $_ENV['CONSUMER_ENDPOINT'] = 'http://consumer:8081';
        $provider = new ConsumerEndpointProvider();

        // When
        $result = $provider->get();

        // Then: 環境変数の値がそのまま返される
        $this->assertSame('http://consumer:8081', $result);
    }

    #[Test]
    public function getReturnsCustomEndpointUrl(): void
    {
        // Given: カスタムエンドポイントが設定されている
        $_ENV['CONSUMER_ENDPOINT'] = 'http://localhost:9000';
        $provider = new ConsumerEndpointProvider();

        // When
        $result = $provider->get();

        // Then: カスタム URL が返される
        $this->assertSame('http://localhost:9000', $result);
    }

    #[Test]
    public function getReturnsString(): void
    {
        // Given: 任意のエンドポイント
        $_ENV['CONSUMER_ENDPOINT'] = 'http://example.com';
        $provider = new ConsumerEndpointProvider();

        // When
        $result = $provider->get();

        // Then: string 型が返される
        $this->assertIsString($result);
    }
}
