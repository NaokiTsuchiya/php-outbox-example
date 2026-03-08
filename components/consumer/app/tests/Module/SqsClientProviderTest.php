<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqsClientProviderTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $_ENV['SQS_ENDPOINT'] = 'http://localhost:9324';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    // ── get(): SqsClient を返す ──

    #[Test]
    public function getReturnsSqsClient(): void
    {
        // Given: SQS_ENDPOINT が設定されている（setUp で設定済み）
        $provider = new SqsClientProvider();

        // When
        $result = $provider->get();

        // Then: SqsClient が返される
        $this->assertInstanceOf(SqsClient::class, $result);
    }

    #[Test]
    public function getCreatesSqsClientWithEnvEndpoint(): void
    {
        // Given: 別のエンドポイントが設定されている
        $_ENV['SQS_ENDPOINT'] = 'http://elasticmq:9324';
        $provider = new SqsClientProvider();

        // When
        $result = $provider->get();

        // Then: SqsClient が返される
        $this->assertInstanceOf(SqsClient::class, $result);
    }
}
