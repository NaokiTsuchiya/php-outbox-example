<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggerProviderTest extends TestCase
{
    // ── get(): LoggerInterface を返す ──

    #[Test]
    public function getReturnsLoggerInterface(): void
    {
        // Given
        $provider = new LoggerProvider();

        // When
        $result = $provider->get();

        // Then: PSR-3 LoggerInterface の実装が返される
        $this->assertInstanceOf(LoggerInterface::class, $result);
    }

    #[Test]
    public function getReturnsSameTypeOnMultipleCalls(): void
    {
        // Given
        $provider = new LoggerProvider();

        // When: 複数回呼び出す
        $first = $provider->get();
        $second = $provider->get();

        // Then: いずれも LoggerInterface を実装している
        $this->assertInstanceOf(LoggerInterface::class, $first);
        $this->assertInstanceOf(LoggerInterface::class, $second);
    }
}
