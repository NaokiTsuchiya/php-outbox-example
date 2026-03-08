<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Event;

use MyVendor\OutboxConsumer\Mail\MailerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EventProcessorTest extends TestCase
{
    private ProcessedEventRepository&MockObject $repository;
    private MailerInterface&MockObject $mailer;
    private EventProcessor $processor;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProcessedEventRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->processor = new EventProcessor($this->repository, $this->mailer);
    }

    // ── process(): 未処理イベント ──

    #[Test]
    public function processHandlesNewEvent(): void
    {
        // Given: 未処理の ORDER_CREATED イベント
        $eventId = 'evt-001';
        $type = 'ORDER_CREATED';
        $payload = ['order_id' => 'ord-001', 'user_id' => 'u1', 'amount' => 3000];

        $this->repository->method('isProcessed')->with($eventId)->willReturn(false);

        // Then: Mailer::send が呼ばれる
        $this->mailer->expects($this->once())->method('send');

        // Then: markProcessed が eventId と type を引数に呼ばれる
        $this->repository->expects($this->once())
            ->method('markProcessed')
            ->with($eventId, $type);

        // When
        $this->processor->process($eventId, $type, $payload);
    }

    // ── process(): 重複イベント（冪等性） ──

    #[Test]
    public function processSkipsDuplicateEvent(): void
    {
        // Given: 処理済みのイベント
        $eventId = 'evt-001';
        $this->repository->method('isProcessed')->with($eventId)->willReturn(true);

        // Then: Mailer は呼ばれない
        $this->mailer->expects($this->never())->method('send');

        // Then: markProcessed も呼ばれない
        $this->repository->expects($this->never())->method('markProcessed');

        // When
        $this->processor->process($eventId, 'ORDER_CREATED', ['order_id' => 'ord-001']);
    }

    // ── process(): 未知の type ──

    #[Test]
    public function processHandlesUnknownType(): void
    {
        // Given: 未知の type のイベント
        $eventId = 'evt-002';
        $type = 'UNKNOWN_EVENT_TYPE';
        $this->repository->method('isProcessed')->with($eventId)->willReturn(false);

        // Then: Mailer は呼ばれない（ORDER_CREATED 以外は送信対象外）
        $this->mailer->expects($this->never())->method('send');

        // Then: markProcessed は呼ばれる（再試行を防ぐため処理済みとしてマーク）
        $this->repository->expects($this->once())
            ->method('markProcessed')
            ->with($eventId, $type);

        // When: 例外なく処理が完了する
        $this->processor->process($eventId, $type, []);
    }
}
