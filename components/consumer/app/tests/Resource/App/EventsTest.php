<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Resource\App;

use MyVendor\OutboxConsumer\Event\EventProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EventsTest extends TestCase
{
    private EventProcessor&MockObject $eventProcessor;
    private Events $events;

    protected function setUp(): void
    {
        $this->eventProcessor = $this->createMock(EventProcessor::class);
        $this->events = new Events($this->eventProcessor);
    }

    // ── POST /events: イベント受信 ──

    #[Test]
    public function postAcceptsEvent(): void
    {
        // Given: Pump から送信される ORDER_CREATED イベント
        $id = 'evt-001';
        $type = 'ORDER_CREATED';
        $payload = ['order_id' => 'ord-001', 'user_id' => 'u1', 'amount' => 3000];

        // Then: EventProcessor::process が id, type, payload を引数に呼ばれる
        $this->eventProcessor->expects($this->once())
            ->method('process')
            ->with($id, $type, $payload);

        // When
        $result = $this->events->onPost($id, $type, $payload);

        // Then: {"status": "accepted"} が body に設定される
        $this->assertSame(['status' => 'accepted'], $result->body);
    }
}
