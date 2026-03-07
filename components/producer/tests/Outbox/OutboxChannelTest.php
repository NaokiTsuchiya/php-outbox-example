<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OutboxChannelTest extends TestCase
{
    #[Test]
    public function notifyConstantValue(): void
    {
        $this->assertSame('outbox:notify', OutboxChannel::NOTIFY);
    }

    #[Test]
    public function pumpAndSenderShareSameChannelConstant(): void
    {
        $pumpRef = new \ReflectionClass(OutboxPump::class);
        $senderRef = new \ReflectionClass(OutboxSender::class);

        $pumpSource = file_get_contents($pumpRef->getFileName());
        $senderSource = file_get_contents($senderRef->getFileName());

        $this->assertStringContainsString('OutboxChannel::NOTIFY', $pumpSource);
        $this->assertStringContainsString('OutboxChannel::NOTIFY', $senderSource);
        $this->assertStringNotContainsString("'outbox:notify'", $pumpSource);
        $this->assertStringNotContainsString("'outbox:notify'", $senderSource);
    }
}
