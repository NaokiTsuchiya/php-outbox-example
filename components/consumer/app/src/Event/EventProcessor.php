<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Event;

use MyVendor\OutboxConsumer\Mail\MailerInterface;
use Ray\AuraSqlModule\Annotation\Transactional;

class EventProcessor
{
    public function __construct(
        private ProcessedEventRepository $repository,
        private MailerInterface $mailer,
    ) {}

    /**
     * イベントを処理する
     *
     * 冪等性保証: 処理済みイベントID は再処理しない
     */
    #[Transactional]
    public function process(string $eventId, string $type, array $payload): void
    {
        // 処理済みなら再処理しない（at-least-once 配信への対応）
        if ($this->repository->isProcessed($eventId)) {
            return;
        }

        if ($type === 'ORDER_CREATED') {
            $this->mailer->send(
                $payload['user_id'],
                '注文確認',
                sprintf('注文ID: %s, 金額: %s円', $payload['order_id'], $payload['amount']),
            );
        }

        $this->repository->markProcessed($eventId, $type);
    }
}
