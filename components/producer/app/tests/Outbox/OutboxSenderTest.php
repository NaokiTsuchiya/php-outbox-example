<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

use Aura\Sql\ExtendedPdoInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OutboxSenderTest extends TestCase
{
    private ExtendedPdoInterface&MockObject $pdo;
    private \Redis&MockObject $redis;
    private OutboxSender $sender;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(ExtendedPdoInterface::class);
        $this->redis = $this->createMock(\Redis::class);
        $this->sender = new OutboxSender($this->pdo, $this->redis);
    }

    #[Test]
    public function sendInsertsRowIntoProducedZero(): void
    {
        $this->pdo->expects($this->once())
            ->method('perform')
            ->with(
                $this->stringContains('INSERT INTO produced_zero'),
                $this->callback(function (array $params): bool {
                    return $params['type'] === 'ORDER_CREATED'
                        && json_decode($params['message'], true) === ['orderId' => 'abc']
                        && strlen($params['id']) > 0;
                })
            );

        $this->sender->send('ORDER_CREATED', ['orderId' => 'abc']);
    }

    #[Test]
    public function notifyPublishesToRedisChannel(): void
    {
        $this->redis->expects($this->once())
            ->method('publish')
            ->with(OutboxChannel::NOTIFY, 'notify');

        $this->sender->notify();
    }
}